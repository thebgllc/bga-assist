<?php

declare(strict_types=1);

namespace BgaHarness;

require_once __DIR__ . '/BgaExceptionTypes.php';

abstract class BgaStubs
{
    protected BgaDatabaseFake $db;
    protected BgaNotificationSpy $notifications;

    // ── Modern-framework proxies ───────────────────────────────────────────
    // Game code can use either the modern ($this->notify->all) or the legacy
    // (notifyAllPlayers) API — both are wired to the same notification spy.

    /** Modern notify proxy: $this->notify->all(...) / ->player(...) */
    protected object $notify;

    /** Modern gamestate proxy: $this->gamestate->nextState(...) / ->changeActivePlayer(...) */
    protected object $gamestate;

    /** Modern player_data proxy: $this->player_data->get(pid, key) / ->set(pid, key, val) */
    protected object $player_data;

    // ── Internal state ─────────────────────────────────────────────────────

    private int $activePlayerId = 0;
    private int $currentPlayerId = 0;
    private array $players = [];
    private string $currentState = 'gameSetup';
    private array $gameStateValues = [];
    private array $allowedActions = [];
    private array $transitionMap = [];
    private array $multiactivePlayers = [];

    /** Maps ClassName::class string → state name string for modern transitions */
    private array $stateClassMap = [];

    /** Controlled dice queue for bga_rand() in tests */
    private array $diceQueue = [];

    /** In-memory player_data store: "pid_key" => value */
    private array $playerDataStore = [];

    public function __construct()
    {
        $this->db            = new BgaDatabaseFake();
        $this->notifications = new BgaNotificationSpy();
        $this->_buildProxies();
    }

    // ── Proxy construction ─────────────────────────────────────────────────

    private function _buildProxies(): void
    {
        $stub = $this;

        $this->notify = new class($stub) {
            public function __construct(private BgaStubs $s) {}
            public function all(string $type, string $msg, array $data): void {
                $this->s->notifyAllPlayers($type, $msg, $data);
            }
            public function player(int $id, string $type, string $msg, array $data): void {
                $this->s->notifyPlayer($id, $type, $msg, $data);
            }
        };

        $this->gamestate = new class($stub) {
            public function __construct(private BgaStubs $s) {}
            public function nextState(string $transition): void {
                $this->s->gamestate_nextState($transition);
            }
            public function changeActivePlayer(int $playerId): void {
                $this->s->gamestate_changeActivePlayer($playerId);
            }
            public function setAllPlayersMultiactive(): void {
                $this->s->gamestate_setAllPlayersMultiactive();
            }
            public function setPlayerNonMultiactive(int $playerId, string $nextState): void {
                $this->s->gamestate_setPlayerNonMultiactive($playerId, $nextState);
            }
        };

        // Route through the instance (not a by-value array copy) so the proxy
        // and the _getPlayerData/_setPlayerData test API share one store.
        $this->player_data = new class($stub) {
            public function __construct(private BgaStubs $s) {}
            public function get(int $pid, string $key): mixed {
                return $this->s->_getPlayerData($pid, $key);
            }
            public function set(int $pid, string $key, mixed $value): void {
                $this->s->_setPlayerData($pid, $key, $value);
            }
        };
    }

    // ── Modern bga_rand mock ───────────────────────────────────────────────

    /**
     * Modern BGA framework random roll. Override or use _setDiceRolls() in
     * tests to produce deterministic sequences.
     */
    protected function bga_rand(int $min, int $max): int
    {
        if (!empty($this->diceQueue)) {
            return (int) array_pop($this->diceQueue);
        }
        return rand($min, $max);
    }

    // ── Notification methods (legacy names still work; notify proxy calls these) ──

    // Public so the modern $this->notify proxy (an unrelated anonymous class)
    // can forward into them. Matches real BGA, where notifyAllPlayers is public.
    public function notifyAllPlayers(string $type, string $msg, array $data): void
    {
        $this->notifications->notifyAllPlayers($type, $msg, $data);
    }

    public function notifyPlayer(int $id, string $type, string $msg, array $data): void
    {
        $this->notifications->notifyPlayer($id, $type, $msg, $data);
    }

    // ── Active/current player ──────────────────────────────────────────────

    protected function getActivePlayerId(): int  { return $this->activePlayerId; }
    protected function getCurrentPlayerId(): int { return $this->currentPlayerId; }

    protected function getActivePlayerName(): string
    {
        return $this->getPlayerNameById($this->activePlayerId);
    }

    protected function getPlayerNameById(int $playerId): string
    {
        return $this->players[$playerId]['player_name'] ?? 'Unknown';
    }

    // ── State machine ──────────────────────────────────────────────────────

    // Public so the modern $this->gamestate proxy (an unrelated anonymous class)
    // can forward into them; legacy game code still calls them as $this->gamestate_*.
    public function gamestate_nextState(string $transition): void
    {
        // Accept either a transition key ('next', 'done') or a State::class string
        if (isset($this->stateClassMap[$transition])) {
            $this->currentState = $this->stateClassMap[$transition];
            return;
        }
        if (isset($this->transitionMap[$this->currentState][$transition])) {
            $this->currentState = $this->transitionMap[$this->currentState][$transition];
        }
    }

    public function gamestate_changeActivePlayer(int $playerId): void
    {
        $this->activePlayerId  = $playerId;
        $this->currentPlayerId = $playerId;
    }

    public function gamestate_setAllPlayersMultiactive(): void
    {
        $this->multiactivePlayers = array_keys($this->players);
    }

    public function gamestate_setPlayerNonMultiactive(int $playerId, string $nextState): void
    {
        $this->multiactivePlayers = array_values(
            array_filter($this->multiactivePlayers, static fn (int $id): bool => $id !== $playerId)
        );
        if ($this->multiactivePlayers === []) {
            $this->currentState = $nextState;
        }
    }

    // ── Legacy checkAction (classic framework only) ────────────────────────

    protected function checkAction(string $actionName): bool
    {
        if ($this->currentPlayerId !== $this->activePlayerId) {
            $this->throwUserError('notYourTurn');
        }
        $allowed = $this->allowedActions[$this->currentState] ?? [];
        if ($allowed !== [] && !in_array($actionName, $allowed, true)) {
            $this->throwUserError('actionNotAllowed');
        }
        return true;
    }

    // ── DB methods ─────────────────────────────────────────────────────────

    protected function DbQuery(string $sql): void               { $this->db->DbQuery($sql); }
    protected function getCollectionFromDB(string $sql, bool $u = false): array { return $this->db->getCollectionFromDB($sql, $u); }
    protected function getObjectFromDB(string $sql): ?array     { return $this->db->getObjectFromDB($sql); }
    protected function getUniqueValueFromDB(string $sql): mixed { return $this->db->getUniqueValueFromDB($sql); }
    protected function getIntFromDB(string $sql): int           { return $this->db->getIntFromDB($sql); }

    // ── Game state values ──────────────────────────────────────────────────

    protected function getGameStateValue(string $name): int     { return (int)($this->gameStateValues[$name] ?? 0); }
    protected function setGameStateValue(string $name, int $v): void { $this->gameStateValues[$name] = $v; }
    protected function incGameStateValue(string $name, int $n): int
    {
        $next = $this->getGameStateValue($name) + $n;
        $this->setGameStateValue($name, $next);
        return $next;
    }

    // ── Error helpers ──────────────────────────────────────────────────────

    /**
     * Throw a user-facing gameplay error.
     * Modern game code: throw new \UserException('code')
     * Legacy game code: $this->throwUserError('code')
     * Both are caught by BgaGameTestCase::whenAction.
     */
    protected function throwUserError(string $errorCode): never
    {
        throw new BgaUserException($errorCode);
    }

    protected function throwVisibleSystemError(string $message): never
    {
        throw new BgaVisibleSystemException($message);
    }

    // ── Test control API (_xxx methods) ────────────────────────────────────

    public function _setActivePlayer(int $playerId): void
    {
        $this->activePlayerId  = $playerId;
        $this->currentPlayerId = $playerId;
    }

    public function _setCurrentPlayer(int $playerId): void  { $this->currentPlayerId = $playerId; }
    public function _setState(string $stateName): void      { $this->currentState = $stateName; }
    public function _getState(): string                     { return $this->currentState; }
    public function _getDb(): BgaDatabaseFake               { return $this->db; }
    public function _getNotifications(): BgaNotificationSpy { return $this->notifications; }

    public function _setPlayers(array $players): void
    {
        $this->players = $players;
        $this->multiactivePlayers = [];
        if ($this->activePlayerId === 0 && $players !== []) {
            $first = (int) array_key_first($players);
            $this->activePlayerId  = $first;
            $this->currentPlayerId = $first;
        }
    }

    public function _setAllowedActions(array $byState): void { $this->allowedActions = $byState; }
    public function _setTransitions(array $map): void        { $this->transitionMap = $map; }
    public function _setGameStateValue(string $n, int $v): void { $this->setGameStateValue($n, $v); }

    /**
     * Register modern State::class strings → state name strings so that
     * gamestate->nextState(SomeState::class) resolves correctly in tests.
     *
     *   $game->_registerStateClasses([
     *       PlayerTurn::class => 'playerTurn',
     *       Combat::class     => 'combat',
     *   ]);
     */
    public function _registerStateClasses(array $classToName): void
    {
        $this->stateClassMap = array_merge($this->stateClassMap, $classToName);
    }

    /**
     * Seed a fixed dice sequence consumed by bga_rand().
     * Pass values in the order they will be rolled (FIFO).
     */
    public function _setDiceRolls(array $rolls): void
    {
        $this->diceQueue = array_reverse($rolls);
    }

    /** Read a value from the player_data store (for assertions). */
    public function _getPlayerData(int $pid, string $key): mixed
    {
        return $this->playerDataStore["{$pid}_{$key}"] ?? null;
    }

    /** Seed a player_data value before an action runs. */
    public function _setPlayerData(int $pid, string $key, mixed $value): void
    {
        $this->playerDataStore["{$pid}_{$key}"] = $value;
    }
}
