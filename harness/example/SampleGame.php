<?php

declare(strict_types=1);

namespace BgaExample;

use BgaHarness\BgaStubs;

class SampleGame extends BgaStubs
{
    public function __construct()
    {
        parent::__construct();

        $this->_setAllowedActions([
            'playerTurn' => ['action_playSet', 'action_pass'],
        ]);

        $this->_setTransitions([
            'playerTurn' => [
                'nextPlayer' => 'playerTurn',
                'endGame' => 'endGame',
            ],
        ]);
    }

    public function setupNewGame(): void
    {
        $this->_setState('playerTurn');
        $this->_setGameStateValue('cards_remaining', 81);
    }

    public function action_playSet(array $cardIds): void
    {
        $this->checkAction('action_playSet');

        if (count($cardIds) !== 3) {
            $this->throwUserError('invalidSet');
        }

        $cards = [];
        foreach ($cardIds as $cardId) {
            $row = $this->getObjectFromDB('SELECT * FROM card WHERE card_id = ' . (int) $cardId);
            if ($row === null) {
                $this->throwUserError('invalidSet');
            }
            $cards[] = [
                'type' => (string) ($row['card_type'] ?? ''),
                'number' => (int) ($row['card_number'] ?? 1),
                'shading' => (string) ($row['card_shading'] ?? 'solid'),
            ];
        }

        if (!$this->_checkValidSet($cards)) {
            $this->throwUserError('invalidSet');
        }

        $this->_scoreSet($this->getActivePlayerId(), $cardIds);

        $remaining = $this->incGameStateValue('cards_remaining', -3);
        if ($remaining <= 0) {
            $this->gamestate_nextState('endGame');

            return;
        }

        $this->gamestate_nextState('nextPlayer');
    }

    public function action_pass(): void
    {
        $this->checkAction('action_pass');
        $this->notifyAllPlayers('playerPassed', '', ['player_id' => $this->getActivePlayerId()]);
        $this->gamestate_nextState('nextPlayer');
    }

    public function _checkValidSet(array $cards): bool
    {
        if (count($cards) !== 3) {
            return false;
        }

        foreach (['type', 'number', 'shading'] as $field) {
            $values = array_map(static fn (array $card): mixed => $card[$field] ?? null, $cards);
            $unique = count(array_unique($values));
            if ($unique !== 1 && $unique !== 3) {
                return false;
            }
        }

        return true;
    }

    public function _scoreSet(int $playerId, array $cards): void
    {
        $this->DbQuery('UPDATE player SET player_score = player_score + 1 WHERE player_id = ' . $playerId);
        foreach ($cards as $cardId) {
            $this->DbQuery("UPDATE card SET card_location = 'discard' WHERE card_id = " . (int) $cardId);
        }

        $score = (int) $this->getUniqueValueFromDB('SELECT player_score FROM player WHERE player_id = ' . $playerId);
        $this->notifyAllPlayers('setPlayed', '', [
            'player_id' => $playerId,
            'score' => $score,
            'card_ids' => $cards,
        ]);
    }
}
