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

    protected function whenAction(string $method, array $args = []): ActionResult
    {
        try {
            $result = $this->game->$method(...array_values($args));

            return ActionResult::success($result);
        } catch (BgaUserException $e) {
            return ActionResult::userError($e->getMessage());
        } catch (BgaVisibleSystemException $e) {
            return ActionResult::systemError($e->getMessage());
        }
    }

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

    protected function defaultPlayers(): array
    {
        return [
            1 => ['player_id' => 1, 'player_name' => 'Alice', 'player_score' => 0],
            2 => ['player_id' => 2, 'player_name' => 'Bob', 'player_score' => 0],
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
        $result = new self();
        $result->succeeded = true;
        $result->errorCode = null;
        $result->returnValue = $value;

        return $result;
    }

    public static function userError(string $code): self
    {
        $result = new self();
        $result->succeeded = false;
        $result->errorCode = $code;
        $result->returnValue = null;

        return $result;
    }

    public static function systemError(string $message): self
    {
        $result = new self();
        $result->succeeded = false;
        $result->errorCode = $message;

        return $result;
    }

    public function assertSucceeded(): void
    {
        Assert::assertTrue($this->succeeded, 'Expected action to succeed.');
    }

    public function assertFailedWith(string $errorCode): void
    {
        Assert::assertFalse($this->succeeded, 'Expected action to fail.');
        Assert::assertSame(
            $errorCode,
            $this->errorCode,
            sprintf('Expected failure code "%s", got "%s".', $errorCode, (string) $this->errorCode)
        );
    }
}
