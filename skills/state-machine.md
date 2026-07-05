# State Machine Patterns

Write state machine code so transitions are explicit, action permissions are enforced, and game states never stall.

## Which Form to Use (Detect First)

There are two entirely different state-machine styles. Pick by the framework version (SKILL §2) and never mix them:

- **Legacy** — a `$machinestates` array in `states.inc.php`, string `possibleactions`, `stXxx` action methods on the game class. Covered in "Required State Types" and the 12-state example below.
- **Modern** — one PHP class per state under `modules/php/States/`, `#[PossibleAction]` methods, transitions expressed by returning the next `State::class`. There is no `$machinestates` array and `states.inc.php` is empty/omitted. Covered in "Modern Framework: One Class Per State" at the end. This is what the current BGA games in this workspace use — prefer it unless the project is clearly legacy.

## Required State Types (Legacy)

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

## Common Failure Modes (Legacy)

- Missing `possibleactions` causes `checkAction` to reject valid actions.
- Calling a transition name that is not declared stalls gameplay.
- Returning from a `game` state action without transition leaves the game stuck.

---

# Modern Framework: One Class Per State

Modern games (the ones in this workspace, e.g. RummyTime) define **one class per state** in `modules/php/States/`, each extending `Bga\GameFramework\States\GameState`. Transitions are expressed by **returning the next `State::class`** (or `99` to end). There is no `$machinestates` array, no `possibleactions` strings, and no manual `checkAction`.

## State Class Shape

```php
namespace Bga\Games\<projectname>\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\Actions\Types\JsonParam;   // for complex params
use Bga\GameFramework\UserException;

class PlayPhase extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id:                40,
            type:              StateType::ACTIVE_PLAYER, // GAME | ACTIVE_PLAYER | MULTIPLE_ACTIVE_PLAYER
            description:       clienttranslate('${actplayer} may play cards or end turn'),
            descriptionMyTurn: clienttranslate('${you} may play cards or end your turn'),
        );
    }
}
```

- `description` uses `${actplayer}` (shown to spectators/waiters); `descriptionMyTurn` uses `${you}` (shown to the acting player). Provide both for active states.
- On a `GAME`-type state, set `updateGameProgression: true` in the constructor for the per-turn cycle state, and implement `getGameProgression(): int` on the game class.

## Transitions Are Return Values

- **`ACTIVE_PLAYER` / `MULTIPLE_ACTIVE_PLAYER`**: each `#[PossibleAction]` method returns the next `State::class`.
- **`GAME`**: implement `onEnteringState(int $activePlayerId): mixed` and **always return** a `State::class` (or `99` / an `EndScore::class`). Returning nothing leaves the game stuck — the modern equivalent of the legacy "game state with no transition" bug.
- End the game with `return 99;` or by returning a terminal game-state class.
- `setupNewGame()` returns the first `State::class`.

```php
public function onEnteringState(int $activePlayerId): mixed
{
    $this->game->giveExtraTime($activePlayerId);
    if (empty($this->game->getCardsInHand($activePlayerId))) {
        return EndScore::class;                 // win detected
    }
    $this->game->activeNextPlayer();
    return PlayPhase::class;
}
```

## Actions: `#[PossibleAction]`

An action is a public method annotated `#[PossibleAction]`. The framework enforces action permissions from the annotation — **do not call `checkAction` yourself**. Parameters are injected by name:

- `int $activePlayerId` (active states) / `int $currentPlayerId` (multiactive) — the acting player.
- Named scalars come from the JS `performAction('actX', {...})` args by matching name.
- Complex payloads: type the param with `#[JsonParam] array $x` (arbitrary JSON) or `#[IntArrayParam] array $ids` (int list).

```php
#[PossibleAction]
public function actPlayMelds(#[JsonParam] array $proposedMelds, int $activePlayerId): mixed
{
    // ... validate, mutate, notify ...
    return NextPlayer::class;
}
```

## Zombie Handler Is Required

Every `ACTIVE_PLAYER` / `MULTIPLE_ACTIVE_PLAYER` state **must** define `zombie($playerId)` that performs the minimal legal move, or an abandoned/eliminated player stalls the table forever. The simplest correct move is usually best (draw-and-finish, or pass):

```php
public function zombie(int $playerId): mixed
{
    return $this->actDraw($playerId);   // take the cheapest legal turn and move on
}
```

## `getArgs()` Is Broadcast — Never Leak Private State

`getArgs()` takes **no** active-player id and its return value is sent to **every** client. Putting a player's hand (or any hidden info) here leaks it to opponents. Read the active id via `$this->game->getActivePlayerId()` only for public facts; deliver private data through `getAllDatas()` (per-player) + `notify->player()`.

```php
public function getArgs(): array
{
    $playerId = (int)$this->game->getActivePlayerId();
    // NEVER return a specific player's hand here — this is broadcast to all.
    return [
        'melds'       => $this->game->getMeldsWithCards(), // public
        'established' => $this->game->isEstablished($playerId),
    ];
}
```

## Trust Nothing From the Client

For rich interactive turns, the client stages all edits locally and submits **once** (see the "stage-on-client, submit-once" pattern). The server must independently re-derive the legal state and re-validate the entire submission — never trust the proposal's shape. RummyTime's `actPlayMelds` re-reads the player's hand and the table from the DB and re-checks every rule before mutating.

## Modern Failure Modes

- `GAME` state's `onEnteringState` returns nothing → game stuck.
- Missing `zombie()` on an active state → abandoned tables never progress.
- Private data placed in `getArgs()` → hand leak to opponents.
- Calling `checkAction`/using `possibleactions` in a modern project → mixing framework versions (SKILL §2 forbids this).
