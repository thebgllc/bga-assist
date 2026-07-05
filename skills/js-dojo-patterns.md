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

## Testability Rule

Keep core gameplay calculations in pure utility functions and test with Jest.

Pattern from repo:

- `isValidSet(cards)`
- `calculateScore(setCount, streak)`
- `getLegalMoves(cards)`

Write tests using deterministic input/output only; avoid DOM/network in unit tests.
