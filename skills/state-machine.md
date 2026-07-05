# State Machine Patterns

Write state machine code so transitions are explicit, action permissions are enforced, and game states never stall.

## Required State Types

Use only the four supported state types:

- `manager`
- `activeplayer`
- `multipleactiveplayer`
- `game`

## 12-State Example (Auction Cycle + Final Buying)

Use this as a structural template:

```php
$machinestates = [
  1 => [
    'name' => 'gameSetup',
    'type' => 'manager',
    'action' => 'stGameSetup',
    'transitions' => ['next' => 10],
  ],

  10 => [
    'name' => 'roundStart',
    'type' => 'game',
    'action' => 'stRoundStart',
    'transitions' => ['toAuction' => 20],
  ],
  20 => [
    'name' => 'auctionBid',
    'type' => 'activeplayer',
    'description' => clienttranslate('${actplayer} must bid or pass'),
    'possibleactions' => ['actBid', 'actPassBid'],
    'transitions' => ['nextBidder' => 21, 'auctionClosed' => 30],
  ],
  21 => [
    'name' => 'auctionAdvance',
    'type' => 'game',
    'action' => 'stAuctionAdvance',
    'transitions' => ['continueAuction' => 20, 'auctionClosed' => 30],
  ],

  30 => [
    'name' => 'resolveAuction',
    'type' => 'game',
    'action' => 'stResolveAuction',
    'transitions' => ['toFinalBuying' => 40],
  ],
  40 => [
    'name' => 'finalBuying',
    'type' => 'multipleactiveplayer',
    'description' => clienttranslate('All players select final purchases'),
    'possibleactions' => ['actFinalBuy'],
    'transitions' => ['allBought' => 50],
  ],

  50 => [
    'name' => 'finalBuyingResolve',
    'type' => 'game',
    'action' => 'stFinalBuyingResolve',
    'transitions' => ['toAction' => 60],
  ],
  60 => [
    'name' => 'playerAction',
    'type' => 'activeplayer',
    'description' => clienttranslate('${actplayer} must play'),
    'possibleactions' => ['actPlay', 'actPass'],
    'transitions' => ['nextPlayer' => 61, 'endRound' => 70],
  ],
  61 => [
    'name' => 'advancePlayer',
    'type' => 'game',
    'action' => 'stAdvancePlayer',
    'transitions' => ['continueRound' => 60, 'endRound' => 70],
  ],

  70 => [
    'name' => 'scoreRound',
    'type' => 'game',
    'action' => 'stScoreRound',
    'transitions' => ['nextRound' => 80, 'endGame' => 99],
  ],
  80 => [
    'name' => 'prepareNextRound',
    'type' => 'game',
    'action' => 'stPrepareNextRound',
    'transitions' => ['roundStart' => 10, 'endGame' => 99],
  ],

  99 => [
    'name' => 'gameEnd',
    'type' => 'manager',
    'action' => 'stGameEnd',
    'args' => 'argGameEnd',
  ],
];
```

## Rules You Must Enforce

- Every `activeplayer` and `multipleactiveplayer` state must define `possibleactions`.
- Every transition called in PHP must exist in the state's `transitions` map.
- `game` states must always call `nextState` (or equivalent transition logic) before returning.

## multipleactiveplayer Completion Rule

When a player resolves action in a `multipleactiveplayer` state, mark them done:

```php
$this->gamestate_setPlayerNonMultiactive($playerId, 'allBought');
```

Pattern:
- Enter state and activate all relevant players.
- Each player action calls `setPlayerNonMultiactive`.
- Transition fires only after all active players are done.

## Common Failure Modes

- Missing `possibleactions` causes `checkAction` to reject valid actions.
- Calling a transition name that is not declared stalls gameplay.
- Returning from a `game` state action without transition leaves the game stuck.
