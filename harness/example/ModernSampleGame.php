<?php

declare(strict_types=1);

namespace BgaExample;

use BgaHarness\BgaStubs;

/**
 * Example game using MODERN framework idioms:
 *   - $this->notify->all / ->player   (not notifyAllPlayers)
 *   - throw new \UserException('code') (not throwUserError)
 *   - $this->player_data->get/set      (not player-table columns)
 *   - $this->gamestate->nextState      (not gamestate_nextState)
 *   - bga_rand()                       (mockable via _setDiceRolls)
 *
 * The legacy SampleGame.php is preserved for backwards-compat reference.
 * New projects should follow this pattern instead.
 */
class ModernSampleGame extends BgaStubs
{
    public function __construct()
    {
        parent::__construct();

        $this->_setTransitions([
            'playerTurn' => [
                'continue' => 'playerTurn',
                'endGame'  => 'gameEnd',
            ],
        ]);
    }

    public function setupNewGame(): void
    {
        $this->DbQuery(
            "CREATE TABLE IF NOT EXISTS bid (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                amount INTEGER NOT NULL DEFAULT 0
            )"
        );
        $this->_setState('playerTurn');
        $this->setGameStateValue('rounds_remaining', 3);
    }

    /**
     * Modern action — no checkAction; throws \UserException; uses notify proxy.
     * $activePlayerId is the last param (injected by framework in Studio;
     * passed explicitly in tests via whenAction args).
     */
    public function actBid(int $amount, int $activePlayerId): void
    {
        if ($amount < 1 || $amount > 10) {
            throw new \UserException('invalidBidAmount');
        }

        // Mockable in tests via givenDiceRolls([N])
        $bonus = $this->bga_rand(1, 6);
        $total = $amount + $bonus;

        $this->DbQuery(
            "INSERT INTO bid (player_id, amount) VALUES ({$activePlayerId}, {$total})"
        );

        // Store cross-action context in player_data (not a player-table column)
        $this->player_data->set($activePlayerId, 'last_bid', $total);

        // Modern notify
        $this->notify->all('bidPlaced', '', [
            'player_id' => $activePlayerId,
            'amount'    => $total,
            'bonus'     => $bonus,
        ]);

        $remaining = $this->incGameStateValue('rounds_remaining', -1);
        if ($remaining <= 0) {
            $this->gamestate->nextState('endGame');
            return;
        }
        $this->gamestate->nextState('continue');
    }

    public function actPass(int $activePlayerId): void
    {
        $this->notify->all('playerPassed', '', ['player_id' => $activePlayerId]);
        $this->gamestate->nextState('continue');
    }
}
