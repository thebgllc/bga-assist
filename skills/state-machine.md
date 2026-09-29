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

- `int $activePlayerId` (active states) / `int $currentPlayerId` (multiactive) — the acting player. These are magic parameters (below), never sent by the client.
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

## Magic Action Parameters

The framework autowires these parameter names, so the client never sends them and cannot forge them. Don't reuse them for client args: `$args`, `$activePlayerId`/`$active_player_id`, `$activePlayerNo`, `$currentPlayerId`/`$current_player_id`, `$currentPlayerNo`.

- **ACTIVE_PLAYER states:** take `int $activePlayerId`, and **never call `getCurrentPlayerId()`** in the act method. `zombie()` runs server-side with no browser behind it, so there is no current player and the call throws. Because the acting player arrives as a param, the zombie can call the act method directly with `return $this->actX($default, $playerId);`.
- **MULTIPLE_ACTIVE_PLAYER states:** the reverse applies. There is no single active player to autowire, so the act method takes `int $currentPlayerId`. Put the shared logic in a helper that takes an explicit `$playerId`, and have both the act method and `zombie()` call it.

```php
#[PossibleAction]
public function actChoose(int $cardId, int $currentPlayerId): mixed
{
    return $this->choose($currentPlayerId, $cardId);
}

public function zombie(int $playerId): mixed
{
    return $this->choose($playerId, $this->game->firstLegalCard($playerId));
}

private function choose(int $playerId, int $cardId): mixed { /* validate, mutate, setPlayerNonMultiactive */ }
```

## Every Active State Must Offer a Legal Action

If the rules can produce a position where the active player has no legal move, the table hard-locks, and the only way out is a timeout or a zombie. Detect that position in the preceding GAME state and resolve it there (auto-place, auto-pass, skip the player) before activating anyone. The zombie soak test does **not** catch this for a live player, because the zombie would have been the one to pass. Write a harness test that reaches the empty position and checks that the GAME state resolved it.

## Change the Active Player Only in GAME States

Call `activeNextPlayer()` / `changeActivePlayer()` only from a GAME state's `onEnteringState`, and return a transition in the same call so the client learns who is active. An act method that changes the active player and stays in its state leaves every client showing the old player. The pattern is a small `NextPlayer` / `NextTurn` GAME state (see the `onEnteringState` example above).

## End of Game: Score in a GAME State, Then 99

State 99 is the framework's own end state, so don't try to hook it or redefine it. Compute final scores in a GAME state of your own (98 by convention, e.g. `EndScore`), write them with `$this->game->bga->playerScore->set($playerId, $score)` (or `->inc`), set end-of-game stats there too, and then `return 99;` (Entropy: `States/EndScore.php`).

## Zombie Handler Is Required

Every `ACTIVE_PLAYER` / `MULTIPLE_ACTIVE_PLAYER` state **must** define `zombie($playerId)` that performs the minimal legal move, or an abandoned/eliminated player stalls the table forever. The simplest correct move is usually best (draw-and-finish, or pass):

```php
public function zombie(int $playerId): mixed
{
    return $this->actDraw($playerId);   // take the cheapest legal turn and move on
}
```

**The Zombie Mode level is required metadata for alpha.** It is set in Game Metadata Manager → Metadata tab: 0 passing, 1 random, 2 greedy, 3 smart. If it is missing, "Request ALPHA status" is blocked. The game declares one number, so every state's `zombie()` must honestly match it. A random choice in one state plus a fixed default in another mixes levels 1 and 0. For level 1, `GameState::getRandomZombieChoice($choices)` picks a random key.

Sources / further reading for the sections above: [rbellec/claude-code-bga](https://github.com/rbellec/claude-code-bga) (MIT), TECHNICAL_NOTES.md and SKILL.md.

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

## Undo / Restart turn

Decide this **before alpha**: `"db_undo_support": true` in gameinfos creates the undo tables only
for tables started after it is set (Oceans found out after alpha had begun). What the BGA Undo
policy forbids is a restore across a *hidden or random reveal* — a draw, an offer refill turning up
the next tile, a die — or across another player's action or a change of active player. It does not
care that notifications were broadcast: `undoRestorePoint()` restores the game database and every
client rebuilds from `setup`. The log is NOT rewound — the earlier lines stay and the framework appends
"<player> takes back their move" for everyone (verified on Studio, Entropy 2026-09-22). So "keep it
client-side so undo works" is the wrong question; the right one is "what has been revealed since the
savepoint".

Policy also says one whole-turn restart, not per-step undo (Studio guideline B.3), and only where
opponents would let you take the move back in real life — a turn that is a cascade of prompts
qualifies; a single clear click does not.

The pattern (Entropy, `States/PlayerTurn.php`, `States/EffectPrompt.php`, `Model/DbWorld.php`):

```php
// the turn state: single active player, savepoint as the turn begins
public function onEnteringState(int $activePlayerId): void
{
    $this->game->globals->set('turnRevealed', 0);   // before the snapshot, so it is inside it
    $this->game->undoSavepoint();                     // stored when this request commits
}

// wherever something hidden is turned up (a draw, an offer refill)
private function reveal(): void { $this->game->globals->set('turnRevealed', 1); }

// the prompt state: offer it in getArgs, refuse it once revealed, restore, and change state AT ONCE
public function getArgs(): array { return [..., 'restartable' => $this->restartable()]; }

#[PossibleAction]
public function actRestartTurn(int $activePlayerId)
{
    if (!$this->restartable()) {
        throw new UserException(clienttranslate('The turn cannot be restarted once a new tile or card has been turned up'));
    }
    $this->game->undoRestorePoint();
    return PlayerTurn::class;   // the globals cache is stale until the state changes
}
```

Client: one `addActionButton(_('Restart turn'), () => performAction('actRestartTurn'), { color: 'alert' })`
when `args.restartable`, last and red (guidelines A.2, C.3). Test shim: `undoSavepoint()` copies
every table (plus globals, scores, stats) and `undoRestorePoint()` puts them back and refuses a
changed active player — then the real action path is under test, not a mock.

## Modern Failure Modes

- `GAME` state's `onEnteringState` returns nothing → game stuck.
- `db_undo_support` switched on after alpha → existing tables error on Undo; decide it before.
- Missing `zombie()` on an active state → abandoned tables never progress.
- `getCurrentPlayerId()` in an ACTIVE_PLAYER act method → the zombie path throws. Use `$activePlayerId`.
- An ACTIVE_PLAYER state with no legal move → hard lock. Resolve the position in the preceding GAME state.
- Active player changed outside a GAME state, or without a transition → clients show the wrong player.
- Zombie Mode level unset, or `zombie()` behaviour that doesn't match it → alpha request blocked, or the declared level misstates what the zombie does.
- Private data placed in `getArgs()` → hand leak to opponents.
- Calling `checkAction`/using `possibleactions` in a modern project → mixing framework versions (SKILL §2 forbids this).
