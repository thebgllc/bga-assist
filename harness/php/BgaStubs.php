<?php

declare(strict_types=1);

namespace BgaHarness;

require_once __DIR__ . '/BgaExceptionTypes.php';

abstract class BgaStubs
{
    protected BgaDatabaseFake $db;
    protected BgaNotificationSpy $notifications;

    private int $activePlayerId = 0;
    private int $currentPlayerId = 0;
    private array $players = [];
    private string $currentState = 'gameSetup';
    private array $gameStateValues = [];
    private array $allowedActions = [];
    private array $transitionMap = [];
    private array $multiactivePlayers = [];

    public function __construct()
    {
        $this->db = new BgaDatabaseFake();
        $this->notifications = new BgaNotificationSpy();
    }

    protected function getActivePlayerId(): int
    {
        return $this->activePlayerId;
    }

    protected function getActivePlayerName(): string
    {
        return $this->getPlayerNameById($this->activePlayerId);
    }

    protected function getPlayerNameById(int $playerId): string
    {
        return $this->players[$playerId]['player_name'] ?? 'Unknown';
    }

    protected function getCurrentPlayerId(): int
    {
        return $this->currentPlayerId;
    }

    protected function gamestate_nextState(string $transition): void
    {
        if (isset($this->transitionMap[$this->currentState][$transition])) {
            $this->currentState = $this->transitionMap[$this->currentState][$transition];
        }
    }

    protected function gamestate_changeActivePlayer(int $playerId): void
    {
        $this->activePlayerId = $playerId;
        $this->currentPlayerId = $playerId;
    }

    protected function gamestate_setAllPlayersMultiactive(): void
    {
        $this->multiactivePlayers = array_keys($this->players);
    }

    protected function gamestate_setPlayerNonMultiactive(int $playerId, string $nextState): void
    {
        $this->multiactivePlayers = array_values(
            array_filter(
                $this->multiactivePlayers,
                static fn (int $id): bool => $id !== $playerId
            )
        );

        if ($this->multiactivePlayers === []) {
            $this->currentState = $nextState;
        }
    }

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

    protected function DbQuery(string $sql): void
    {
        $this->db->DbQuery($sql);
    }

    protected function getCollectionFromDB(string $sql, bool $bUniqueValue = false): array
    {
        return $this->db->getCollectionFromDB($sql, $bUniqueValue);
    }

    protected function getObjectFromDB(string $sql): ?array
    {
        return $this->db->getObjectFromDB($sql);
    }

    protected function getUniqueValueFromDB(string $sql): mixed
    {
        return $this->db->getUniqueValueFromDB($sql);
    }

    protected function getIntFromDB(string $sql): int
    {
        return $this->db->getIntFromDB($sql);
    }

    protected function notifyAllPlayers(string $type, string $msg, array $data): void
    {
        $this->notifications->notifyAllPlayers($type, $msg, $data);
    }

    protected function notifyPlayer(int $id, string $type, string $msg, array $data): void
    {
        $this->notifications->notifyPlayer($id, $type, $msg, $data);
    }

    protected function getGameStateValue(string $name): int
    {
        return (int) ($this->gameStateValues[$name] ?? 0);
    }

    protected function setGameStateValue(string $name, int $value): void
    {
        $this->gameStateValues[$name] = $value;
    }

    protected function incGameStateValue(string $name, int $increment): int
    {
        $next = $this->getGameStateValue($name) + $increment;
        $this->setGameStateValue($name, $next);

        return $next;
    }

    protected function createDeck(string $deckId): BgaDeckStub
    {
        return new BgaDeckStub($deckId);
    }

    protected function throwUserError(string $errorCode): never
    {
        throw new BgaUserException($errorCode);
    }

    protected function throwVisibleSystemError(string $message): never
    {
        throw new BgaVisibleSystemException($message);
    }

    public function _setActivePlayer(int $playerId): void
    {
        $this->activePlayerId = $playerId;
    }

    public function _setCurrentPlayer(int $playerId): void
    {
        $this->currentPlayerId = $playerId;
    }

    public function _setPlayers(array $players): void
    {
        $this->players = $players;
        $this->multiactivePlayers = [];
        if ($this->activePlayerId === 0 && $players !== []) {
            $first = (int) array_key_first($players);
            $this->activePlayerId = $first;
            $this->currentPlayerId = $first;
        }
    }

    public function _setState(string $stateName): void
    {
        $this->currentState = $stateName;
    }

    public function _setAllowedActions(array $byState): void
    {
        $this->allowedActions = $byState;
    }

    public function _setTransitions(array $map): void
    {
        $this->transitionMap = $map;
    }

    public function _setGameStateValue(string $name, int $value): void
    {
        $this->setGameStateValue($name, $value);
    }

    public function _getState(): string
    {
        return $this->currentState;
    }

    public function _getDb(): BgaDatabaseFake
    {
        return $this->db;
    }

    public function _getNotifications(): BgaNotificationSpy
    {
        return $this->notifications;
    }
}

class BgaDeckStub
{
    private string $deckId;
    private array $cards = [];

    public function __construct(string $deckId)
    {
        $this->deckId = $deckId;
    }

    public function addCard(array $card): void
    {
        $this->cards[] = $card;
    }

    public function count(): int
    {
        return count($this->cards);
    }

    public function getDeckId(): string
    {
        return $this->deckId;
    }
}
