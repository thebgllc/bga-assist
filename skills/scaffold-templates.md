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
  - state registration
  - notification setup
  - UI orchestration
- `modules/js/states/*.js`
  - frontend behavior per game state
  - `onEnteringState`, `onLeavingState`, `onPlayerActivationChange`

## Modern Game.js Shape

```javascript
export class Game {
  constructor(bga) {
    this.bga = bga;
    // register state handlers
    // bga.states.register('stateName', new SomeState(this, bga));
  }

  setup(gamedatas) {
    this.gamedatas = gamedatas;
    this.bga.notifications.setupPromiseNotifications();
  }
}
```

## PHP Action Flow Template

Use this order in server action methods:

1. `checkAction(...)`
2. validate arguments
3. read DB state
4. apply domain rule
5. write DB
6. notify
7. transition

## Test Scaffold Requirement

For each gameplay action, create corresponding PHPUnit tests using `BgaGameTestCase` fluent style:

- happy path
- invalid input path
- wrong-player path
- transition path
