<?php

declare(strict_types=1);

namespace BgaExample;

use BgaHarness\BgaGameTestCase;
use BgaHarness\BgaStubs;

class SampleGameTest extends BgaGameTestCase
{
    protected function createGame(): BgaStubs
    {
        $game = new SampleGame();
        $game->_setState('playerTurn');
        $game->_setGameStateValue('cards_remaining', 12);
        $game->_getDb()->seedTable('player', [
            ['player_id' => 1, 'player_name' => 'Alice', 'player_score' => 0],
            ['player_id' => 2, 'player_name' => 'Bob', 'player_score' => 0],
        ]);

        return $game;
    }

    public function test_valid_set_scores_and_advances_state(): void
    {
        // CC PATTERN: happy path — given setup -> when action -> then state + notification
        $this->givenActivePlayer(1)
            ->givenState('playerTurn')
            ->givenDatabaseRows('card', [
                ['card_id' => 1, 'card_type' => 'red', 'card_number' => 1, 'card_shading' => 'solid', 'card_location' => 'hand', 'card_location_arg' => 1],
                ['card_id' => 2, 'card_type' => 'green', 'card_number' => 2, 'card_shading' => 'striped', 'card_location' => 'hand', 'card_location_arg' => 1],
                ['card_id' => 3, 'card_type' => 'blue', 'card_number' => 3, 'card_shading' => 'open', 'card_location' => 'hand', 'card_location_arg' => 1],
            ]);

        $result = $this->whenAction('action_playSet', ['card_ids' => [1, 2, 3]]);

        $result->assertSucceeded();
        $this->thenNotificationSent('setPlayed', ['player_id' => 1, 'score' => 1]);
        $this->thenDatabaseHas('card', ['card_location' => 'discard', 'card_id' => 1]);
        $this->thenStateShouldBe('playerTurn');
    }

    public function test_invalid_set_rejected_with_error(): void
    {
        // CC PATTERN: invalid action — assert specific BGA error code thrown
        $this->givenActivePlayer(1)
            ->givenState('playerTurn')
            ->givenDatabaseRows('card', [
                ['card_id' => 1, 'card_type' => 'red', 'card_number' => 1, 'card_shading' => 'solid'],
                ['card_id' => 2, 'card_type' => 'red', 'card_number' => 2, 'card_shading' => 'striped'],
                ['card_id' => 3, 'card_type' => 'blue', 'card_number' => 3, 'card_shading' => 'open'],
            ]);

        $result = $this->whenAction('action_playSet', ['card_ids' => [1, 2, 3]]);

        $result->assertFailedWith('invalidSet');
        $this->thenNotificationNotSent('setPlayed');
        $this->thenStateShouldBe('playerTurn');
    }

    public function test_inactive_player_cannot_play(): void
    {
        // CC PATTERN: wrong actor — checkAction should block this
        $this->givenActivePlayer(1)
            ->givenCurrentPlayer(2)
            ->givenState('playerTurn');

        $result = $this->whenAction('action_playSet', ['card_ids' => [4, 5, 6]]);

        $result->assertFailedWith('notYourTurn');
    }

    public function test_last_set_triggers_endgame(): void
    {
        // CC PATTERN: state transition — verify the right transition fires
        $this->givenGameStateValue('cards_remaining', 3)
            ->givenActivePlayer(1)
            ->givenDatabaseRows('card', [
                ['card_id' => 1, 'card_type' => 'red', 'card_number' => 1, 'card_shading' => 'solid'],
                ['card_id' => 2, 'card_type' => 'green', 'card_number' => 2, 'card_shading' => 'striped'],
                ['card_id' => 3, 'card_type' => 'blue', 'card_number' => 3, 'card_shading' => 'open'],
            ]);

        $this->whenAction('action_playSet', ['card_ids' => [1, 2, 3]]);

        $this->thenStateShouldBe('endGame');
    }

    public function test_set_validation_logic(): void
    {
        // CC PATTERN: pure function — no given/when/then needed, just call directly
        $validSet = [
            ['type' => 'red', 'number' => 1, 'shading' => 'solid'],
            ['type' => 'green', 'number' => 2, 'shading' => 'striped'],
            ['type' => 'blue', 'number' => 3, 'shading' => 'open'],
        ];
        $this->assertTrue($this->game->_checkValidSet($validSet));

        $invalidSet = $validSet;
        $invalidSet[2]['type'] = 'red';
        $this->assertFalse($this->game->_checkValidSet($invalidSet));
    }
}
