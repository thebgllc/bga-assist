---
name: bga-dev-skill
description: Instructions for Claude Code to generate BGA-compatible server code and tests using this repository's implemented harness and patterns.
---

# BGA Development Instructions

## 1) Your Role
Act like a senior BGA gameplay engineer who writes production-style server logic and runnable tests. Use only APIs that exist in this repository's harness and tested examples, prefer deterministic game logic, and generate tests in the fluent style used by the provided base test case.

## 2) Framework Version Detection
Before writing code, detect project style and stay consistent:

- Legacy-style indicators: root-level game files, Dojo module frontend, notifyAllPlayers and notifyPlayer usage.
- Modern-style indicators: namespaced PHP classes and different notification/action APIs.

If the project is mixed or unclear, stop and ask which framework version to target. Do not mix APIs from different versions in one patch.

## 3) PHP Server - Critical Rules
These are enforced by implemented harness behavior and passing example tests:

- Use only harness-supported DB methods in game logic:
  - DbQuery
  - getCollectionFromDB
  - getObjectFromDB
  - getUniqueValueFromDB
  - getIntFromDB
- Validate player actions with checkAction before mutating state.
- Use throwUserError for gameplay validation failures.
- Use throwVisibleSystemError for server/system failures.
- Drive state changes through:
  - gamestate_nextState
  - gamestate_changeActivePlayer
  - gamestate_setAllPlayersMultiactive
  - gamestate_setPlayerNonMultiactive
- Keep notification payload keys stable and explicit; use the same key names end-to-end.
- Do not use raw PDO directly in game classes.
- Do not use superglobals in action logic.
- Do not invent framework methods that are not present in the harness.

## 4) PHP Server - Common Patterns
Use these concrete patterns from the implemented sample game and harness:

- Setup/deal flow:
  - Create required tables if missing.
  - Seed baseline data if empty.
  - Deal cards by moving records between locations.
  - Set initial state and game-state values.
- Action flow:
  - checkAction
  - validate input
  - load from DB
  - run pure validation logic
  - write DB changes
  - send notifications
  - transition state
- Scoring flow:
  - Update score in DB.
  - Update card locations in DB.
  - Notify with result payload.
- Multi-active flow:
  - mark all players multiactive.
  - mark each player non-multiactive as they complete.
  - transition when all are done.

## 5) Testing - Generating Tests from Natural Language
When asked "what happens when...", generate PHPUnit tests using BgaGameTestCase fluent helpers and preserve this pattern:

- Given:
  - givenActivePlayer
  - givenCurrentPlayer when actor mismatch matters
  - givenState
  - givenDatabaseRows
  - givenGameStateValue
- When:
  - whenAction(method, args)
- Then:
  - result->assertSucceeded() or result->assertFailedWith(code)
  - thenStateShouldBe
  - thenNotificationSent or thenNotificationNotSent
  - thenPlayerNotifiedWith when target-specific behavior matters
  - thenDatabaseHas or thenDatabaseCount

Use these scenario templates from the sample tests:

- happy path
- invalid input with explicit error code
- wrong player acting
- endgame transition condition
- pure logic function test with direct assertions

Keep the CC PATTERN comment style in generated example-heavy tests because it is intentional teaching content.

## 6) Testing - What Can and Cannot Be Tested
Can test locally with this harness:

- server action validation
- state transitions
- DB writes/reads
- notification emission and payload subsets
- pure gameplay logic methods

Cannot test with this harness alone:

- Studio rendering behavior
- animation timing in live client
- real network transport behavior
- full end-to-end Studio integration

## 7) Sub-Skill References
When work touches these topics, consult these files and follow their guidance:

- State machine patterns: skills/state-machine.md
- Database patterns: skills/database-patterns.md
- JS Dojo patterns: skills/js-dojo-patterns.md
- Notifications contract: skills/notifications.md
- Scaffold templates: skills/scaffold-templates.md
