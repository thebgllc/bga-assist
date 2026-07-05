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
        $this->DbQuery(
            "CREATE TABLE IF NOT EXISTS card (" .
            "card_id INTEGER PRIMARY KEY, " .
            "card_type TEXT, " .
            "card_number INTEGER, " .
            "card_shading TEXT, " .
            "card_location TEXT, " .
            "card_location_arg INTEGER)"
        );

        $existingCards = $this->getIntFromDB('SELECT COUNT(*) FROM card');
        if ($existingCards === 0) {
            $types = ['red', 'green', 'blue'];
            $numbers = [1, 2, 3];
            $shadings = ['solid', 'striped', 'open'];
            $cardId = 1;

            for ($i = 0; $i < 3; $i++) {
                foreach ($types as $type) {
                    foreach ($numbers as $number) {
                        foreach ($shadings as $shading) {
                            $this->DbQuery(sprintf(
                                "INSERT INTO card (card_id, card_type, card_number, card_shading, card_location, card_location_arg) VALUES (%d, '%s', %d, '%s', 'deck', 0)",
                                $cardId,
                                $type,
                                $number,
                                $shading
                            ));
                            $cardId++;
                        }
                    }
                }
            }
        }

        $players = $this->getCollectionFromDB('SELECT player_id FROM player');
        foreach ($players as $player) {
            $playerId = (int) ($player['player_id'] ?? 0);
            if ($playerId <= 0) {
                continue;
            }

            $cardsToDeal = $this->getCollectionFromDB('SELECT card_id FROM card WHERE card_location = "deck" ORDER BY card_id LIMIT 3');
            foreach ($cardsToDeal as $card) {
                $this->DbQuery(sprintf(
                    "UPDATE card SET card_location = 'hand', card_location_arg = %d WHERE card_id = %d",
                    $playerId,
                    (int) $card['card_id']
                ));
            }
        }

        $this->_setState('playerTurn');
        $this->_setGameStateValue('cards_remaining', $this->getIntFromDB("SELECT COUNT(*) FROM card WHERE card_location = 'deck'"));
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
