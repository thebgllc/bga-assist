<?php

declare(strict_types=1);

namespace BgaExample;

use BgaHarness\BgaGameTestCase;
use BgaHarness\BgaStubs;

class ModernSampleGameTest extends BgaGameTestCase
{
    protected function createGame(): BgaStubs
    {
        $game = new ModernSampleGame();
        $game->setupNewGame();
        return $game;
    }

    public function test_valid_bid_stored_and_notified(): void
    {
        // CC PATTERN: happy path — modern notify->all, player_data, bga_rand
        $this->givenActivePlayer(1)
            ->givenState('playerTurn')
            ->givenDiceRolls([3]);  // bga_rand(1,6) returns 3

        $result = $this->whenAction('actBid', [5, 1]);  // amount=5, activePlayerId=1

        $result->assertSucceeded();
        // total = 5 (bid) + 3 (die) = 8
        $this->thenDatabaseHas('bid', ['player_id' => 1, 'amount' => 8]);
        $this->thenNotificationSent('bidPlaced', ['player_id' => 1, 'amount' => 8, 'bonus' => 3]);
        $this->thenPlayerDataIs(1, 'last_bid', 8);
        $this->thenStateShouldBe('playerTurn');
    }

    public function test_bid_below_minimum_rejected(): void
    {
        // CC PATTERN: modern UserException caught as assertFailedWith
        $this->givenActivePlayer(1)->givenState('playerTurn');

        $result = $this->whenAction('actBid', [0, 1]);

        $result->assertFailedWith('invalidBidAmount');
        $this->thenNotificationNotSent('bidPlaced');
        $this->thenDatabaseCount('bid', 0);
    }

    public function test_bid_above_maximum_rejected(): void
    {
        $this->givenActivePlayer(1)->givenState('playerTurn');

        $result = $this->whenAction('actBid', [11, 1]);

        $result->assertFailedWith('invalidBidAmount');
    }

    public function test_last_round_triggers_endgame(): void
    {
        // CC PATTERN: state transition via modern gamestate->nextState
        $this->givenGameStateValue('rounds_remaining', 1)
            ->givenActivePlayer(1)
            ->givenState('playerTurn')
            ->givenDiceRolls([1]);

        $this->whenAction('actBid', [5, 1]);

        $this->thenStateShouldBe('gameEnd');
    }

    public function test_player_data_isolated_per_player(): void
    {
        // CC PATTERN: player_data stores context per player, not shared
        $this->givenActivePlayer(1)->givenState('playerTurn')->givenDiceRolls([2]);
        $this->whenAction('actBid', [3, 1]);  // total = 5

        $this->givenActivePlayer(2)->givenDiceRolls([4]);
        $this->whenAction('actBid', [6, 2]);  // total = 10

        $this->thenPlayerDataIs(1, 'last_bid', 5);
        $this->thenPlayerDataIs(2, 'last_bid', 10);
    }

    public function test_pass_notifies_and_stays_in_turn(): void
    {
        $this->givenActivePlayer(2)->givenState('playerTurn');

        $result = $this->whenAction('actPass', [2]);

        $result->assertSucceeded();
        $this->thenNotificationSent('playerPassed', ['player_id' => 2]);
        $this->thenStateShouldBe('playerTurn');
    }
}
