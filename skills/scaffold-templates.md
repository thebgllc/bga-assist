# Scaffold Templates (Modern Framework)

Generate modern-framework structure first. Do not scaffold legacy layout by default.

Legacy framework exists, but its file shape differs; consult official BGA docs when targeting legacy projects.

## Required Structure

```text
modules/
  php/
    Game.php
    States/
      <StateName>.php
    Managers/
    Models/
  js/
    Game.js
    states/
      <StateName>.js
  templates/
```

## Required Files and Responsibilities

- `modules/php/Game.php`
  - Main game class
  - wiring of managers/models/states
  - high-level actions and state entry points
- `modules/php/States/*.php`
  - one class per complex state
  - state-specific action validation and transitions
- `modules/php/Managers/*.php`
  - data access and domain operations
- `modules/php/Models/*.php`
  - value objects/DTO-style helpers
- `modules/js/Game.js`
  - export class Game
  - notification setup
  - UI orchestration, and (for small/medium games) the state UI itself via the
    class's own `onEnteringState(args, isCurrentPlayerActive)` / `onLeavingState`
- `modules/js/states/*.js` (optional — only for larger games)
  - split frontend behavior into per-state handlers when Game.js gets unwieldy
  - `onEnteringState`, `onLeavingState`, `onPlayerActivationChange`
  - a game like RummyTime keeps all of this inside Game.js and does not split

## Modern Game.js Shape

```javascript
export class Game {
  constructor(bga) {
    this.bga = bga;
  }

  setup(gamedatas) {
    this.gamedatas = gamedatas;
    this.bga.notifications.setupPromiseNotifications();
  }

  // Per-state UI hooks live on the class itself; the framework calls the one
  // matching the current state. (Larger games may instead delegate to separate
  // handler objects in modules/js/states/*.js.)
  onEnteringState(args, isCurrentPlayerActive) { /* render for this state */ }
  onLeavingState() { /* tear down */ }
}
```

## PHP Action Flow Template

**Modern framework** — the action is a `#[PossibleAction]` method on the state class; permission is enforced by the annotation, so do **not** call `checkAction`, and the transition is the returned `State::class`:

1. declare `#[PossibleAction] function actX(<params>, int $activePlayerId): mixed` (params injected by name; `#[JsonParam]`/`#[IntArrayParam]` for complex payloads)
2. re-derive legal state from the DB and validate arguments (trust nothing from the client)
3. apply domain rule
4. write DB (no manual transaction — `throw` to roll back)
5. notify (`$this->game->notify->all` / `->player`)
6. `return NextState::class;` (or `99` to end)

See skills/state-machine.md → "Modern Framework" for full detail.

**Legacy framework** — the action is a `stXxx`/`actXxx` method on the game class:

1. `checkAction(...)`
2. validate arguments
3. read DB state
4. apply domain rule
5. write DB
6. notify
7. transition (`gamestate_nextState`)

## Test Scaffold Requirement

For each gameplay action, create corresponding PHPUnit tests using `BgaGameTestCase` fluent style:

- happy path
- invalid input path
- wrong-player path
- transition path
