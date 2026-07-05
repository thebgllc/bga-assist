---
name: bga-dev-skill
description: BGA game development patterns, API reference, and testing conventions for BoardGameArena games. Provides correct method signatures, state machine patterns, and test generation for PHP game logic and JS frontend code.
---

# BGA Development

## Your Role
When working in a BGA project, act as a BGA-experienced developer and follow framework constraints exactly.

Rules:
- Never suggest raw PDO usage in game classes.
- Never use superglobals in action handlers.
- Never invent BGA framework methods.
- When generating tests, always use BgaGameTestCase and the fluent Given/When/Then style.

## File Structure
Every BGA game should include these files:
- gamename.game.php: server-side game logic class.
- gamename.action.php: action endpoints and argument validation.
- gamename.view.php: view integration and template data wiring.
- gamename.js: Dojo frontend class with setup and notification handlers.
- states.inc.php: state machine map.
- material.inc.php: game constants and data definitions.
- dbmodel.sql: schema.
- gameoptions.inc.php and gamepreferences.inc.php: options/preferences.
- stats.inc.php: statistics definitions.

## PHP Server - Critical Rules
Database layer:
- Use only DbQuery, getCollectionFromDB, getObjectFromDB, getUniqueValueFromDB, and getIntFromDB.
- Keep SQL deterministic and explicit.

State machine:
- Validate player actions with checkAction before mutating state.
- Use gamestate_nextState with named transitions from states.inc.php.
- Use activeplayer for single actor turns and multipleactiveplayer for simultaneous actions.

Errors:
- Throw user-facing gameplay errors with throwUserError(errorCode).
- Throw framework-visible system errors with throwVisibleSystemError(message).

## PHP Server - Common Patterns
- Deck movement: update card_location and card_location_arg atomically.
- Scoring: update DB score and emit notification in same action flow.
- Multi-active handling: set all players multiactive, then set each non-multiactive as they resolve.
- Undo-safe actions: check preconditions first, then write DB, then notify.

## JS Frontend - Critical Rules
- Use define([...], function(...) { ... }) Dojo module wrapper.
- Use ajaxcall(url, args, this, onSuccess, onError).
- Register notifications in setupNotifications and route data through notif.args.
- Prefer dojo.query and dojo.place over direct DOM APIs.

## State Machine
- states.inc.php states must include id, name, description, type, and transitions.
- Non-game-end states should include possibleactions.
- Transition names should be descriptive and stable.

## Testing - Natural Language to Tests
When asked scenario questions, generate PHPUnit tests with BgaGameTestCase pattern:
1. givenActivePlayer and givenState setup.
2. givenDatabaseRows and givenGameStateValue setup.
3. whenAction call.
4. thenStateShouldBe, thenNotificationSent, thenDatabaseHas assertions.

When asked for JS utility testing, generate Jest tests that isolate pure logic and avoid UI/network assumptions.

## Testing - Scope
Can test locally:
- Game logic
- State transitions
- DB writes and reads
- Notification payloads

Cannot test without live Studio slot:
- Rendering and animations
- Real network behavior via ajaxcall
- End-to-end Studio UI integration
