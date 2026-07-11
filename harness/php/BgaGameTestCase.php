<?php

declare(strict_types=1);

namespace BgaHarness;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

abstract class BgaGameTestCase extends TestCase
{
    protected BgaStubs $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->game = $this->createGame();
        $this->game->_setPlayers($this->defaultPlayers());
    }

    abstract protected function createGame(): BgaStubs;

    // ── Given ───────────────────────────────────────────────────────────────

    protected function givenActivePlayer(int $playerId): static
    {
        $this->game->_setActivePlayer($playerId);
        $this->game->_setCurrentPlayer($playerId);
        return $this;
    }

    protected function givenCurrentPlayer(int $playerId): static
    {
        $this->game->_setCurrentPlayer($playerId);
        return $this;
    }

    protected function givenState(string $stateName): static
    {
        $this->game->_setState($stateName);
        return $this;
    }

    protected function givenDatabaseRows(string $table, array $rows): static
    {
        $this->game->_getDb()->seedTable($table, $rows);
        return $this;
    }

    protected function givenGameStateValue(string $name, int $value): static
    {
        $this->game->_setGameStateValue($name, $value);
        return $this;
    }

    /**
     * Seed a player_data value before an action.
     * Modern games store cross-state context here instead of player-table columns.
     */
    protected function givenPlayerData(int $playerId, string $key, mixed $value): static
    {
        $this->game->_setPlayerData($playerId, $key, $value);
        return $this;
    }

    /**
     * Seed a fixed dice roll sequence consumed by bga_rand() during the action.
     * Pass values in the order they will be rolled.
     */
    protected function givenDiceRolls(array $rolls): static
    {
        $this->game->_setDiceRolls($rolls);
        return $this;
    }

    // ── When ─────────────────────────────────────────────────────────────────

    /**
     * Invoke a game action and capture success or failure.
     *
     * Catches both classic BgaUserException (throwUserError) and modern
     * global \UserException (throw new UserException('code')) so tests work
     * the same way regardless of framework version.
     */
    protected function whenAction(string $method, array $args = []): ActionResult
    {
        try {
            $result = $this->game->$method(...array_values($args));
            return ActionResult::success($result);
        } catch (BgaUserException $e) {
            return ActionResult::userError($e->getMessage());
        } catch (\UserException $e) {
            return ActionResult::userError($e->getMessage());
        } catch (BgaVisibleSystemException $e) {
            return ActionResult::systemError($e->getMessage());
        }
    }

    // ── Then ─────────────────────────────────────────────────────────────────

    protected function thenStateShouldBe(string $expected): void
    {
        Assert::assertSame($expected, $this->game->_getState());
    }

    protected function thenNotificationSent(string $type, array $dataSubset = []): void
    {
        $this->game->_getNotifications()->assertNotified($type, $dataSubset);
    }

    protected function thenNotificationNotSent(string $type): void
    {
        $this->game->_getNotifications()->assertNotNotified($type);
    }

    protected function thenPlayerNotifiedWith(int $playerId, string $type, array $dataSubset = []): void
    {
        $this->game->_getNotifications()->assertNotifiedPlayer($playerId, $type, $dataSubset);
    }

    protected function thenDatabaseHas(string $table, array $conditions): void
    {
        $this->game->_getDb()->assertRowExists($table, $conditions);
    }

    protected function thenDatabaseCount(string $table, int $count, array $conditions = []): void
    {
        $this->game->_getDb()->assertRowCount($table, $count, $conditions);
    }

    /**
     * Assert a value in the player_data store.
     * Used to verify cross-state combat context, activation counts, etc.
     */
    protected function thenPlayerDataIs(int $playerId, string $key, mixed $expected): void
    {
        $actual = $this->game->_getPlayerData($playerId, $key);
        Assert::assertSame(
            $expected,
            $actual,
            sprintf(
                'Expected player_data[%d][%s] = %s, got %s',
                $playerId, $key, json_encode($expected), json_encode($actual)
            )
        );
    }

    // ── Defaults ─────────────────────────────────────────────────────────────

    protected function defaultPlayers(): array
    {
        return [
            1 => ['player_id' => 1, 'player_name' => 'Alice', 'player_score' => 0],
            2 => ['player_id' => 2, 'player_name' => 'Bob',   'player_score' => 0],
        ];
    }
}

class ActionResult
{
    public bool $succeeded = false;
    public ?string $errorCode = null;
    public mixed $returnValue = null;

    public static function success(mixed $value): self
    {
        $r = new self(); $r->succeeded = true; $r->returnValue = $value; return $r;
    }

    public static function userError(string $code): self
    {
        $r = new self(); $r->succeeded = false; $r->errorCode = $code; return $r;
    }

    public static function systemError(string $message): self
    {
        $r = new self(); $r->succeeded = false; $r->errorCode = $message; return $r;
    }

    public function assertSucceeded(): void
    {
        Assert::assertTrue(
            $this->succeeded,
            'Expected action to succeed but failed with: ' . ($this->errorCode ?? 'unknown')
        );
    }

    public function assertFailedWith(string $errorCode): void
    {
        Assert::assertFalse($this->succeeded, 'Expected action to fail but it succeeded.');
        Assert::assertSame(
            $errorCode, $this->errorCode,
            sprintf('Expected failure code "%s", got "%s".', $errorCode, (string) $this->errorCode)
        );
    }
}
