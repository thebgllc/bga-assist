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
  - getCollectionFromDB (keyed by the first column — repeats silently collapse; see skills/database-patterns.md)
  - getObjectListFromDB (plain list — use it when the first column may repeat)
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

Failures that look like something else (modern framework):

- **Any PHP warning, notice or deprecation corrupts the action's response.** Action endpoints return raw JSON, and anything PHP prints lands in front of it. The client reports a JSON syntax error even though the server action completed and committed. Common causes on PHP 8.2+: `"${var}"` interpolation (write `"{$var}"`), an implicitly nullable parameter `Foo $x = null` (write `?Foo $x = null`), an undefined array key, and a constant defined twice. Using `$this->` for your own methods is always safe. The framework's DB helpers (`DbQuery`, `getCollectionFromDB`, …) are declared static, so the scaffold's `static::DbQuery(...)` is fine too.
- **Constants: `public const X = 1;` on `Game`, not `define()`.** Refer to them as `self::X` inside `Game`, and as `Game::X` in state classes. Constants declared this way are namespaced and autoloaded, and they cannot be redefined. `define()` makes a global constant, and a second include prints "Constant X already defined", which is exactly the warning described above. Keep `material.inc.php` for static tabular data only, and never `include()` it yourself because the framework loads it.
- **`setupNewGame` ordering:** call `$this->reloadPlayersBasicInfos()` right after the player INSERT and `reattributeColorsBasedOnPreferences()`, before any custom INSERT or stat init. The framework caches player infos once per request, so every helper that runs before the reload reads stale colors and order.
- **`$this->bga->playerStats->init($nameOrNames, $value, bool $updateTableStat = false)` takes no player id.** It initialises the stat for every player in one call, so don't loop over players. If you pass a player id, it lands in `$updateTableStat` as `true`, the framework also tries to init a *table* stat of that name, and `setupNewGame` crashes. `inc`/`set` **do** take the player id: `->inc('stars', 1, $playerId)`.

(Sources for the four items above: [rbellec/claude-code-bga](https://github.com/rbellec/claude-code-bga), MIT — TECHNICAL_NOTES.md and php-code-quality.md; checked against the framework's `_ide_helper.php`.)

Modern-framework state rules (when §2 detects modern style — see skills/state-machine.md → "Modern Framework"):

- One class per state in `modules/php/States/`; transitions are the returned `State::class` (or `99`). Do not use `possibleactions`/`checkAction`/`$machinestates` — those are legacy-only, and mixing versions is forbidden (§2).
- Every `ACTIVE_PLAYER`/`MULTIPLE_ACTIVE_PLAYER` state must define a `zombie($playerId)` that takes the minimal legal move, or abandoned tables stall.
- Act methods take the acting player from the magic `int $activePlayerId` param. Never call `getCurrentPlayerId()` in an ACTIVE_PLAYER act method, because the `zombie()` path has no current player and throws. See skills/state-machine.md → "Magic Action Parameters".
- Every ACTIVE_PLAYER state must always offer a legal action. When the rules can leave a player with nothing legal, resolve that position automatically in the preceding GAME state. Change the active player only in GAME states.
- `getArgs()` is broadcast to all clients and gets no active-player id — never return a player's hand or other hidden info from it. Private data flows via `getAllDatas()` + `notify->player()`.
- Decide **undo ("Restart turn") before alpha**: `db_undo_support` in gameinfos only reaches tables created after it is set. The BGA Undo policy's line is a hidden or random reveal since the savepoint (a draw, a refill turning up the next tile, a die) — not whether notifications were broadcast: `undoRestorePoint()` restores the game database and rebuilds every client (the log keeps its lines and gains "<player> takes back their move"). Pattern: `undoSavepoint()` in the turn state's `onEnteringState`, a `turnRevealed` global that every reveal sets, one red "Restart turn" button offered while it is clear, an `actRestartTurn` that refuses once it is set and transitions immediately after restoring (the globals cache is stale until it does). See skills/state-machine.md → "Undo / Restart turn".

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

These four are not out of reach, they are just out of reach *of the harness*. All of them can be
exercised by hand against a live Studio table in Chrome — see section 7. Harness tests remain the
place you assert; the browser is where you find the wiring that silently never ran.

## 7) Testing - Driving a Live Studio Table in Chrome
Use the Claude in Chrome browser tools to smoke-test a deploy end to end. This is slow, stateful and
unassertable, so it never replaces harness tests. It catches what they structurally cannot: a dead
click handler, a notification nobody subscribed to, a card that renders in the wrong column.

Set up a table:

1. `studio.boardgamearena.com/studiogame?game=<gamename>` -> **Play** -> **Create**.
2. Set the player count with the -/+ control, then **Express start**. The empty seats fill with your
   own studio accounts (`jcb0`, `jcb1`, ...) and the game starts immediately.

Drive every seat from the one login:

- Click the red arrow beside a player's name in the top-right player panel. It opens
  `tableview?table=<id>&as=<player_id>` already acting as that player.
- No second login, no incognito window, no separate browser profile — which matters, because an
  agent cannot type a password. Each seat gets its own tab; alternate turns by switching tabs.

Read real state instead of guessing from pixels:

- Where `gameui` lives depends on the URL. On `tableview?table=N&as=…` (the per-seat flow above)
  the game runs in an **iframe**, so reach it with
  `document.querySelector('iframe').contentWindow.gameui`. On the plain game page
  `/1/<gamename>?table=N`, `gameui` is on the top window. Both are right for their own page.
- `gameui.player_id` vs `gameui.getActivePlayerId()` tells you which seat a tab is and whether it is
  that seat's turn.
- `gameui.gamedatas` is that seat's `getAllDatas` payload. Compare it across seats to prove privacy
  holds: own `hand` present, opponents reduced to `handCounts`.

Traps:

- Real-time tables start a ~15s reflection clock. Select the card and click the target promptly, or
  the seat burns its main clock and starts posting warnings into the log.
- The console is noisy with framework errors that are not yours — `ly_metasite.js` throwing
  `Cannot read properties of null (reading 'scrollHeight')` on the chat panel is normal. Filter
  console reads to your own game file before calling anything a bug.
- Never trigger `confirm()` / `alert()`; a modal dialog freezes browser automation entirely.
- **Express start makes a real-time table, and BGA abandons it when no client is left on the game
  page.** This happens when both seats' tabs navigated away, or when one tab toggled `?testuser=`. The log
  reads "All players (with a positive clock) choosed to abandon this game", then "End of game:
  Tie" with scores 0/0. **That looks exactly like a scoring bug and isn't one.** Recreate the table.
  To prevent it, keep one tab per seat and avoid long detours to logaccess or the manage page mid-game.
- **A stale open table silently blocks Create**, even one from a *different* Studio project on the
  same test accounts. The click does nothing and shows no error. Find the stale table (browser history, the
  bottom banner, `/table?table=N`) and use **Express stop** on its config page. Quitting through
  `quitgame.html` is not enough for a game in progress. Check this before concluding that table
  creation is broken.

Debugging aids:

- `/1/<gamename>/<gamename>/logaccess.html?table=N`: full SQL and request logs, with stack traces.
  The "BGA unexpected exceptions logs" view is account-global, not per game.
- Any `public function debug_*()` on `Game` gets an auto-generated button in the
  `#toggleDebugFunctionsPanel`. Drive it by script with `document.getElementById('debug_x').click()`.
- The goToState panel can be scripted too: fill `debugParamDlg-parameter-state-input`, then click
  `debugParamDlgApply`.
- If the end-game stats panel shows "Go premium", click Become premium on the Studio account. It is free in dev.
- A programmatic quit must go through `gameui.ajaxcall('/table/table/quitgame.html',
  {table: N, neutralized: true, s: 'table_quitgame'}, …)` from the game page, because that call attaches the
  CSRF token and a raw `fetch()` is rejected. On the lobby, use `mainsite` instead of `gameui`.

A pass means: the table loads with no error out of your own JS, each action type completes end to
end (log line, deck and counter movement, hand refill, turn passes, progression ticks), and the
board reads correctly from both seats' perspectives.

Sources / further reading: [rbellec/claude-code-bga](https://github.com/rbellec/claude-code-bga)
(MIT), TECHNICAL_NOTES.md and skills/board-game-arena/SKILL.md.

## 8) Sub-Skill References
When work touches these topics, consult these files and follow their guidance:

- State machine patterns: skills/state-machine.md
- Database patterns: skills/database-patterns.md
- JS Dojo patterns: skills/js-dojo-patterns.md
- Notifications contract: skills/notifications.md
- Scaffold templates: skills/scaffold-templates.md
- i18n / translation (do it up front): skills/i18n.md
- Project lifecycle, repo layout, deploy discipline, phased planning, soak
  testing, and the release checklist (do this from Phase 0, not as a
  retrofit): skills/project-lifecycle.md
