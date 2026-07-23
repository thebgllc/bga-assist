---
name: bga-dev-skill
description: Instructions for Claude Code to generate BGA-compatible server code and tests using this repository's implemented harness and patterns.
---

# BGA Development Instructions

## 1) Your Role
Act like a senior BGA gameplay engineer who writes production-style server logic and runnable tests. Use only APIs that exist in this repository's harness and tested examples, prefer deterministic game logic, and generate tests in the fluent style used by the provided base test case.

## 2) Framework Version Detection
Modern is the current default — prefer it for new projects. The harness now supports modern-framework game code natively (no custom adapter needed): `$this->notify`, `$this->gamestate`, `$this->player_data`, `throw new \UserException(...)`, and `bga_rand()` all work directly against `BgaStubs`.

Before writing code, detect project style and stay consistent:

- Modern-style indicators (default for new work): namespaced PHP classes, `#[PossibleAction]` methods, one class per state under `modules/php/States/`, `$this->notify->all/->player`, `throw new \UserException`, `$this->player_data->get/set`.
- Legacy-style indicators: root-level game files, Dojo module frontend, `notifyAllPlayers`/`notifyPlayer`, `checkAction`, `$machinestates`.

When starting a new project, scaffold modern. When extending an existing project, match whatever it already uses. If the project is mixed or unclear, stop and ask which framework version to target. Do not mix APIs from different versions in one patch.

## 3) PHP Server - Critical Rules
These are enforced by implemented harness behavior and passing example tests.

**Modern idioms (default — see harness/example/ModernSampleGame.php):**

- Notify with the proxy: `$this->notify->all($type, $msg, $data)` and `$this->notify->player($playerId, $type, $msg, $data)` (not `notifyAllPlayers`/`notifyPlayer`).
- Raise gameplay errors with `throw new \UserException('code')` (global class, no import needed) — not `throwUserError`. The harness catches both.
- Store cross-state/cross-action context in `$this->player_data->get($pid, $key)` / `->set($pid, $key, $value)` — not in ad-hoc player-table columns.
- Drive transitions with `$this->gamestate->nextState($transition)`, `->changeActivePlayer($pid)`, `->setAllPlayersMultiactive()`, `->setPlayerNonMultiactive($pid, $nextState)`.
- Use `$this->bga_rand($min, $max)` for randomness so tests can seed a deterministic sequence via `givenDiceRolls([...])`.
- No `checkAction`: modern actions are `#[PossibleAction]` methods whose permission is enforced by the annotation. Still re-derive legal state from the DB and validate every argument server-side.

The rules below apply to both frameworks unless marked legacy-only:

- Use only harness-supported DB methods in game logic:
  - DbQuery
  - getCollectionFromDB
  - getObjectFromDB
  - getUniqueValueFromDB
  - getIntFromDB
- (Legacy only) Validate player actions with checkAction before mutating state. Modern actions rely on `#[PossibleAction]` instead.
- Raise gameplay validation failures with `throw new \UserException('code')` (modern) or `throwUserError('code')` (legacy).
- Use throwVisibleSystemError for server/system failures.
- Drive state changes through the modern `$this->gamestate->*` proxy, or the legacy method equivalents:
  - gamestate_nextState
  - gamestate_changeActivePlayer
  - gamestate_setAllPlayersMultiactive
  - gamestate_setPlayerNonMultiactive
- Keep notification payload keys stable and explicit; use the same key names end-to-end.
- Do not use raw PDO directly in game classes.
- Do not use superglobals in action logic.
- Do not invent framework methods that are not present in the harness.
- Never open your own DB transaction. BGA wraps each action in a transaction and rolls back on any thrown exception — issuing `START TRANSACTION` implicitly commits it. To abort a half-applied action, just `throw`. (See skills/database-patterns.md → "Transaction Model".)
- Trust nothing from the client. Re-derive the legal state from the DB and re-validate the entire submission server-side, even when the client staged and pre-checked it.
- Deal/move records atomically (single `UPDATE ... ORDER BY RAND() LIMIT n`) rather than SELECT-then-UPDATE; and cast DB values (`(int)`) before arithmetic.

Modern-framework state rules (when §2 detects modern style — see skills/state-machine.md → "Modern Framework"):

- One class per state in `modules/php/States/`; transitions are the returned `State::class` (or `99`). Do not use `possibleactions`/`checkAction`/`$machinestates` — those are legacy-only, and mixing versions is forbidden (§2).
- Every `ACTIVE_PLAYER`/`MULTIPLE_ACTIVE_PLAYER` state must define a `zombie($playerId)` that takes the minimal legal move, or abandoned tables stall.
- `getArgs()` is broadcast to all clients and gets no active-player id — never return a player's hand or other hidden info from it. Private data flows via `getAllDatas()` + `notify->player()`.
average 
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
  - givenPlayerData(pid, key, value) — seed modern player_data context before the action
  - givenDiceRolls([...]) — seed the bga_rand() sequence for deterministic randomness (FIFO)
- When:
  - whenAction(method, args)
- Then:
  - result->assertSucceeded() or result->assertFailedWith(code)
  - thenStateShouldBe
  - thenNotificationSent or thenNotificationNotSent
  - thenPlayerNotifiedWith when target-specific behavior matters
  - thenDatabaseHas or thenDatabaseCount
  - thenPlayerDataIs(pid, key, expected) — assert a modern player_data value after the action

Test double setup (modern): when a game transitions with `$this->gamestate->nextState(SomeState::class)`, register the class→state-name mapping in `createGame()` so transitions resolve in tests:

```php
$game->_registerStateClasses([
    PlayerTurn::class => 'playerTurn',
    Combat::class     => 'combat',
]);
```

See harness/example/ModernSampleGameTest.php for a full worked example using givenDiceRolls, givenPlayerData, and thenPlayerDataIs.

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
- i18n / translation (do it up front): skills/i18n.md
