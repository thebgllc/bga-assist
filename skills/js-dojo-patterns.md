# JS Patterns

Use this file as direct coding instructions for frontend behavior. The harness includes test stubs for legacy globals (`dojo`, `gameui`), while real modern projects can use `bga.*` APIs.

## What Is Implemented in This Repo

From harness files:

- `harness/js/bgaStubs.js` provides test doubles for:
  - `dojo.*`
  - `gameui.*`
  - notification registration/triggering
- `harness/example/sampleUtils.test.js` demonstrates Jest tests for pure utility functions.

Use this to keep UI-independent logic testable.

## Modern Game Class Pattern

When targeting modern framework projects, generate:

```javascript
export class Game {
  constructor(bga) {
    this.bga = bga;
    // bga.states.register('actionPlay', new PlayState(this, bga));
  }

  setup(gamedatas) {
    this.gamedatas = gamedatas;
    this.bga.notifications.setupPromiseNotifications();
  }
}
```

## State Handler Class Shape

```javascript
export class PlayState {
  constructor(game, bga) {
    this.game = game;
    this.bga = bga;
  }

  onEnteringState(args, isCurrentPlayerActive) {}
  onLeavingState() {}
  onPlayerActivationChange(args, isCurrentPlayerActive) {}
}
```

## Action Dispatch Pattern

Use framework actions instead of ad-hoc network code:

```javascript
this.bga.actions.performAction('actPlaySet', { card_ids: [1, 2, 3] });
```

## Notifications Pattern

- Call `bga.notifications.setupPromiseNotifications()` during setup.
- Implement handlers as `async notif_*` when they include async UI work.

```javascript
async notif_setPlayed(args) {
  // update local state
  // await animation or delay if needed
}
```

## Preferences Pattern

Use `bga.userPreferences` for user settings in modern projects.

```javascript
const value = this.bga.userPreferences.get(prefId);
this.bga.userPreferences.set(prefId, nextValue);
```

## Interactive-Turn Patterns (Modern, vanilla DOM)

For turns where the player composes a move (selecting/dragging/rearranging) before committing, stage everything client-side and submit once. Proven patterns from RummyTime:

- **Stage-then-submit-once.** Keep a working copy of the turn's edits in a `this._work` object; mutate it as the player interacts; on commit send the whole thing in a single `performAction`, serializing complex payloads as JSON:

  ```javascript
  this.bga.actions.performAction('actPlayMelds', { proposedMelds: JSON.stringify(proposal) });
  ```

  The server re-validates the entire submission — the client is a convenience, not the source of truth.
- **Undo / Revert via a snapshot stack.** Before each staged mutation, push a structured-clone snapshot (`_pushUndo()`); Undo pops one, Revert restores the turn-start snapshot and clears the stack. Cheap and reliable vs trying to invert each operation.
- **Confirm before discarding staged work.** When an "end turn / draw" escape hatch would throw away selected or staged cards, `window.confirm()` first so a stray click can't cost the player their move.
- **FLIP animation with stable ids.** Give each card a stable `data-card-id` that survives re-render. Record `getBoundingClientRect()` for every card before re-render (First), re-render (Last), then translate each element from its old position to its new one and animate the transform to zero (Invert/Play). This animates moves without tying animation to DOM identity.
- **`ebg.stock` is unavailable** in the ES-module `export class Game`; render cards with vanilla DOM (see the KEY GOTCHA in project notes). These patterns assume vanilla DOM elements you own.

## Vanilla-DOM Gotchas Seen on Studio

- **A `position: absolute; inset: 0` child needs a positioned parent.** A label span inside a `position: static` token stretched over the whole page and swallowed every click on the board; nothing errored. Give the parent `position: relative`. Diagnose with `document.elementFromPoint(x, y)` inside the game iframe (Byline, 2026-10-24).
- **`getAllDatas` is the only source on reload.** If a notification moves pieces that the server keeps in place (Byline's hour discs stay on the board until clean-up), the live view and the reloaded view disagree. Mirror the server's model; mark, don't move.
- **A state's `refresh()` must not reset the selection it renders.** Byline's planning state
  cleared `tokenSel = []` at the top of `refresh()`, and `toggleToken()` called `refresh()`
  after pushing the id, so no token could ever be selected. Reset selections in
  `onEnteringState` (and when the prompt changes mode), never in the re-render.
- **Re-rendering a strip after a notification drops the prompt's highlights.** When a
  `notif_*` handler rebuilds token strips or card rows with `innerHTML`, call the active
  state's `refresh()` afterwards (`refreshActive()`), and keep `activeState` null for
  non-active viewers so that refresh never shows them buttons.
- **The game runs in an iframe** (`/1/<game>?table=N`). From the page, reach it with `document.querySelector('iframe').contentWindow.gameui`; the modern `Game` instance hangs off `gameui.GameModule`. Test seats open with `tableview?table=N&as=<player id>`.

## Testability Rule

Keep core gameplay calculations in pure utility functions and test with Jest.

Pattern from repo:

- `isValidSet(cards)`
- `calculateScore(setCount, streak)`
- `getLegalMoves(cards)`

Write tests using deterministic input/output only; avoid DOM/network in unit tests.
