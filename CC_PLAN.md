# BGA Dev Skill & Testing Harness — Claude Code Build Plan

## What This Repo Is

A publishable GitHub repo that makes Claude Code (CC) immediately useful for
BoardGameArena game development. Two deliverables in one repo:

1. **SKILL.md** — A CC skill file encoding BGA conventions, API patterns, and
   code generation rules. Users drop this into their own workspace and CC
   becomes BGA-aware without any infrastructure.

2. **Testing Harness** — A PHP stub library (PHPUnit) and JS utility layer
   (Jest) that let CC generate and run meaningful tests against real game logic,
   locally, without a live Studio slot.

An optional third layer — Playwright integration for E2E tests against a live
Studio slot — is scoped as a future v2 and should NOT be built in v1.

---

## Repo Structure to Create

```
bga-dev-skill/
│
├── SKILL.md                          # Primary skill file — CC reads this
│
├── skills/                           # Sub-skills referenced from SKILL.md
│   ├── state-machine.md              # State machine patterns + worked examples
│   ├── database-patterns.md          # DbQuery, getCollectionFromDB, SQL schema
│   ├── js-dojo-patterns.md           # Dojo module patterns, ajaxcall, notifications
│   ├── notifications.md              # notifyAllPlayers / notifyPlayer contracts
│   └── scaffold-templates.md        # Boilerplate for each required BGA file
│
├── harness/
│   ├── php/
│   │   ├── BgaStubs.php              # Mock BGA framework methods
│   │   ├── BgaGameTestCase.php       # Base test class with fluent helpers
│   │   ├── BgaNotificationSpy.php    # Captures and asserts notifications
│   │   ├── BgaDatabaseFake.php       # In-memory DB stand-in for DbQuery etc.
│   │   └── BgaExceptionTypes.php     # BGA exception class stubs
│   ├── js/
│   │   ├── bgaStubs.js               # Mock dojo, gameui, BGA globals
│   │   └── testHelpers.js            # Assertion helpers for game state
│   └── example/
│       ├── SampleGame.php            # Minimal example game using harness
│       ├── SampleGameTest.php        # Annotated tests CC can learn from
│       └── sampleUtils.test.js       # Example Jest tests for JS logic
│
├── composer.json                     # PHPUnit dependency
├── package.json                      # Jest dependency
├── phpunit.xml                       # PHPUnit config pointing at harness + example
├── jest.config.js                    # Jest config
└── README.md                         # Install instructions for end users
```

---

## Build Order

Work through these phases in sequence. Each phase should be fully working and
committed before starting the next. CC should not jump ahead.

### Phase 1 — Repo Scaffolding
### Phase 2 — PHP Stub Library
### Phase 3 — Base Test Case (fluent helpers)
### Phase 4 — SKILL.md (main skill file)
### Phase 5 — Sub-skill files
### Phase 6 — Example game + annotated tests
### Phase 7 — JS stub layer + Jest config
### Phase 8 — README

---

## Phase 1: Repo Scaffolding

Create the directory structure above. Create all files as empty stubs with a
single comment indicating what they will contain. This gives CC the full file
tree to reason about from the start.

Create `composer.json`:
```json
{
  "name": "your-handle/bga-dev-skill",
  "description": "CC skill and testing harness for BoardGameArena game development",
  "require-dev": {
    "phpunit/phpunit": "^10.0"
  },
  "autoload": {
    "psr-4": {
      "BgaHarness\\": "harness/php/",
      "BgaExample\\": "harness/example/"
    }
  }
}
```

Create `package.json`:
```json
{
  "name": "bga-dev-skill",
  "scripts": {
    "test": "jest"
  },
  "devDependencies": {
    "jest": "^29.0.0"
  }
}
```

Create `phpunit.xml` pointing at `harness/` with colors enabled.

---

## Phase 2: PHP Stub Library

This is the most critical phase. The stubs must accurately reflect BGA's actual
API so CC doesn't hallucinate method signatures when generating tests.

### `BgaDatabaseFake.php`

An in-memory SQLite-backed (or array-backed) fake for BGA's DB layer. Must
implement these methods with identical signatures to BGA's real framework:

```php
namespace BgaHarness;

class BgaDatabaseFake {
    private array $tables = [];

    // Executes a query string — for INSERT, UPDATE, DELETE
    public function DbQuery(string $sql): void {}

    // Returns array of rows — equivalent to BGA's getCollectionFromDB
    public function getCollectionFromDB(string $sql, bool $bUniqueValue = false): array {}

    // Returns single row
    public function getObjectFromDB(string $sql): ?array {}

    // Returns single value
    public function getUniqueValueFromDB(string $sql): mixed {}

    // Returns count
    public function getIntFromDB(string $sql): int {}

    // Seed the fake with table data for test setup
    public function seedTable(string $table, array $rows): void {}

    // Assert a row exists matching conditions
    public function assertRowExists(string $table, array $conditions): void {}

    // Assert row count
    public function assertRowCount(string $table, int $expected, array $conditions = []): void {}
}
```

Implementation note: Use PHP's PDO with SQLite `:memory:` as the backing store.
This lets real SQL run against the fake, catching SQL errors in tests.

### `BgaNotificationSpy.php`

Captures all notifications emitted during a test for later assertion:

```php
namespace BgaHarness;

class BgaNotificationSpy {
    private array $notifications = [];

    public function notifyAllPlayers(string $type, string $message, array $data): void {
        $this->notifications[] = [
            'target' => 'all',
            'type' => $type,
            'message' => $message,
            'data' => $data,
        ];
    }

    public function notifyPlayer(int $playerId, string $type, string $message, array $data): void {
        $this->notifications[] = [
            'target' => $playerId,
            'type' => $type,
            'message' => $message,
            'data' => $data,
        ];
    }

    // Assertions
    public function assertNotified(string $type, array $dataSubset = []): void {}
    public function assertNotifiedPlayer(int $playerId, string $type, array $dataSubset = []): void {}
    public function assertNotNotified(string $type): void {}
    public function assertNotificationCount(int $expected): void {}
    public function getNotifications(): array { return $this->notifications; }
    public function reset(): void { $this->notifications = []; }
}
```

### `BgaStubs.php`

The main framework stub. Game classes extend `Table` in BGA — this stub
replaces that base class for testing:

```php
namespace BgaHarness;

abstract class BgaStubs {

    protected BgaDatabaseFake $db;
    protected BgaNotificationSpy $notifications;

    // Player state
    private int $activePlayerId;
    private array $players = [];
    private string $currentState = 'gameSetup';

    public function __construct() {
        $this->db = new BgaDatabaseFake();
        $this->notifications = new BgaNotificationSpy();
    }

    // ── Player management ──────────────────────────────────────────

    protected function getActivePlayerId(): int {}
    protected function getActivePlayerName(): string {}
    protected function getPlayerNameById(int $playerId): string {}
    protected function getCurrentPlayerId(): int {}

    // ── State machine ──────────────────────────────────────────────

    protected function gamestate_nextState(string $transition): void {}
    protected function gamestate_changeActivePlayer(int $playerId): void {}
    protected function gamestate_setAllPlayersMultiactive(): void {}
    protected function gamestate_setPlayerNonMultiactive(int $playerId, string $nextState): void {}
    protected function checkAction(string $actionName): bool {}  // validates vs state machine

    // ── Database pass-throughs ─────────────────────────────────────

    protected function DbQuery(string $sql): void {
        $this->db->DbQuery($sql);
    }
    protected function getCollectionFromDB(string $sql, bool $bUniqueValue = false): array {
        return $this->db->getCollectionFromDB($sql, $bUniqueValue);
    }
    protected function getObjectFromDB(string $sql): ?array {
        return $this->db->getObjectFromDB($sql);
    }
    protected function getUniqueValueFromDB(string $sql): mixed {
        return $this->db->getUniqueValueFromDB($sql);
    }

    // ── Notifications pass-throughs ────────────────────────────────

    protected function notifyAllPlayers(string $type, string $msg, array $data): void {
        $this->notifications->notifyAllPlayers($type, $msg, $data);
    }
    protected function notifyPlayer(int $id, string $type, string $msg, array $data): void {
        $this->notifications->notifyPlayer($id, $type, $msg, $data);
    }

    // ── BGA utility methods ────────────────────────────────────────

    protected function getGameStateValue(string $name): int {}
    protected function setGameStateValue(string $name, int $value): void {}
    protected function incGameStateValue(string $name, int $increment): int {}

    // ── Cards (BGA Deck component stub) ───────────────────────────

    protected function createDeck(string $deckId): BgaDeckStub {}

    // ── Error handling ─────────────────────────────────────────────

    // BGA uses these — tests can assert they are (or aren't) thrown
    protected function throwUserError(string $errorCode): never {
        throw new BgaUserException($errorCode);
    }
    protected function throwVisibleSystemError(string $message): never {
        throw new BgaVisibleSystemException($message);
    }

    // ── Test setup helpers (not in real BGA — test-only) ──────────

    public function _setActivePlayer(int $playerId): void {}
    public function _setPlayers(array $players): void {}
    public function _setState(string $stateName): void {}
    public function _getDb(): BgaDatabaseFake { return $this->db; }
    public function _getNotifications(): BgaNotificationSpy { return $this->notifications; }
}
```

### `BgaExceptionTypes.php`

```php
namespace BgaHarness;

// BGA throws these — stubs so test code can catch them
class BgaUserException extends \Exception {}
class BgaVisibleSystemException extends \Exception {}
class BgaSystemException extends \Exception {}
```

---

## Phase 3: Base Test Case

A fluent test case base class that CC should use as the template for every
generated test. The fluent style is important — it makes CC's output from
natural language queries readable and self-documenting.

```php
namespace BgaHarness;

use PHPUnit\Framework\TestCase;

abstract class BgaGameTestCase extends TestCase {

    protected BgaStubs $game;  // subclasses set this to their game instance

    protected function setUp(): void {
        parent::setUp();
        $this->game = $this->createGame();
        $this->game->_setPlayers($this->defaultPlayers());
    }

    // Subclass implements this to return their game instance
    abstract protected function createGame(): BgaStubs;

    // ── Given (setup) ──────────────────────────────────────────────

    protected function givenActivePlayer(int $playerId): static {
        $this->game->_setActivePlayer($playerId);
        return $this;
    }

    protected function givenState(string $stateName): static {
        $this->game->_setState($stateName);
        return $this;
    }

    protected function givenDatabaseRows(string $table, array $rows): static {
        $this->game->_getDb()->seedTable($table, $rows);
        return $this;
    }

    protected function givenGameStateValue(string $name, int $value): static {
        // seeds a game state variable
        return $this;
    }

    // ── When (action) ──────────────────────────────────────────────

    // Call a player action method — wraps in try/catch for error assertions
    protected function whenAction(string $method, array $args = []): ActionResult {
        try {
            $result = $this->game->$method(...array_values($args));
            return ActionResult::success($result);
        } catch (BgaUserException $e) {
            return ActionResult::userError($e->getMessage());
        } catch (BgaVisibleSystemException $e) {
            return ActionResult::systemError($e->getMessage());
        }
    }

    // ── Then (assertion) ───────────────────────────────────────────

    protected function thenStateShouldBe(string $expected): void {
        // read current state from game and assert
    }

    protected function thenNotificationSent(string $type, array $dataSubset = []): void {
        $this->game->_getNotifications()->assertNotified($type, $dataSubset);
    }

    protected function thenNotificationNotSent(string $type): void {
        $this->game->_getNotifications()->assertNotNotified($type);
    }

    protected function thenPlayerNotifiedWith(int $playerId, string $type, array $dataSubset = []): void {
        $this->game->_getNotifications()->assertNotifiedPlayer($playerId, $type, $dataSubset);
    }

    protected function thenDatabaseHas(string $table, array $conditions): void {
        $this->game->_getDb()->assertRowExists($table, $conditions);
    }

    protected function thenDatabaseCount(string $table, int $count, array $conditions = []): void {
        $this->game->_getDb()->assertRowCount($table, $count, $conditions);
    }

    // ── Defaults ───────────────────────────────────────────────────

    protected function defaultPlayers(): array {
        return [
            1 => ['player_id' => 1, 'player_name' => 'Alice', 'player_score' => 0],
            2 => ['player_id' => 2, 'player_name' => 'Bob',   'player_score' => 0],
        ];
    }
}

// Wraps action results so tests can assert on success or specific error type
class ActionResult {
    public bool $succeeded;
    public ?string $errorCode;
    public mixed $returnValue;

    public static function success(mixed $value): self {}
    public static function userError(string $code): self {}
    public static function systemError(string $message): self {}

    public function assertSucceeded(): void {}
    public function assertFailedWith(string $errorCode): void {}
}
```

---

## Phase 4: SKILL.md

This is what CC reads when working in a BGA game project. It should be opinionated
and direct — written as instructions to CC, not as documentation for humans.

Structure:

```markdown
---
name: bga-dev-skill
description: BGA game development patterns, API reference, and testing conventions
  for BoardGameArena games. Provides correct method signatures, state machine
  patterns, and test generation for PHP game logic and JS frontend code.
---

# BGA Development

## Your Role
When working in a BGA project, you are a BGA-experienced developer who knows
the framework's constraints and idioms. Never suggest raw PDO, never use
superglobals, never call BGA methods that don't exist. When generating tests,
always use BgaGameTestCase and the fluent pattern.

## File Structure (every BGA game has exactly these files)
[list all required files with their purpose]

## PHP Server — Critical Rules
[BGA DB layer — only these methods exist]
[State machine — how transitions work]
[Error throwing — only these two methods]
[What self:: calls are valid]

## PHP Server — Common Patterns
[Card dealing from Deck component]
[Multi-active player handling]
[Scoring patterns]
[Undo-safe action patterns]

## JS Frontend — Critical Rules
[Dojo module declaration pattern]
[ajaxcall signature and error handling]
[Notification handler registration]
[Never use document.querySelector — use dojo.query]

## State Machine
[states.inc.php structure]
[Transition naming conventions]
[Multi-active state patterns]
[When to use activeplayer vs multipleactiveplayer]

## Testing — Generating Tests from Natural Language
When asked "what happens when [scenario]", generate a PHPUnit test using
BgaGameTestCase with this exact pattern:
[worked example]

When asked to test a JS utility function, generate a Jest test with this pattern:
[worked example]

## Testing — What Can and Cannot Be Tested
CAN test: game logic, state transitions, DB writes, notification contents
CANNOT test without a live Studio slot: rendering, animations, ajaxcall network
```

---

## Phase 5: Sub-skill Files

### `skills/state-machine.md`

Full worked example of a states.inc.php for a 3-state game with:
- `gameSetup` → `playerTurn` → `endGame`
- Multi-active voting state example
- All required keys documented with correct types
- Common mistake: forgetting `possibleactions` in non-game-end states

### `skills/database-patterns.md`

Correct SQL and PHP for the 6 most common BGA database operations:
- Dealing cards from deck to hand
- Moving cards between locations
- Incrementing player score
- Getting all cards in a location
- Getting a specific player's hand count
- Checking if a player has a specific card

Each pattern shown as both raw SQL (for `DbQuery`) and as the recommended
`getCollectionFromDB` / `getObjectFromDB` call.

### `skills/js-dojo-patterns.md`

- Correct `define([...], function(...) { ... })` module pattern
- `ajaxcall` with action, args, handler, and error handler
- `addTooltip` and `addTooltipToClass`
- `dojo.place`, `dojo.query`, `dojo.connect` vs `dojo.on`
- How to register a notification handler in `setupNotifications()`
- Animation with `dojo.animateProperty`

### `skills/notifications.md`

The notification contract between PHP and JS — this is where most BGA bugs live.

For each notification type:
- PHP: which method to call, data shape
- JS: how to register the handler, what `notif.args` contains
- Common mistake: PHP sends `card_id`, JS expects `cardId` (casing mismatch)

### `skills/scaffold-templates.md`

Minimal correct boilerplate for each required file. CC should use these as
starting points, not invent its own structure. Include:
- `gamename.game.php` — correct class extension, required method stubs
- `gamename.action.php` — correct action method pattern with sanitization
- `gamename.view.php` — correct view method
- `gamename.js` — correct Dojo define wrapper with required methods
- `states.inc.php` — minimal 2-state example
- `dbmodel.sql` — correct format with player table already included

---

## Phase 6: Example Game + Annotated Tests

A minimal but complete game called `setgame` (a simplified Set card game).
This is the reference implementation CC learns test patterns from.

### `SampleGame.php`

Implements exactly:
- `setupNewGame()` — deals cards to players
- `action_playSet(array $cardIds)` — validates and plays a set
- `action_pass()` — player passes their turn
- `_checkValidSet(array $cards): bool` — pure logic, easily testable
- `_scoreSet(int $playerId, array $cards): void` — updates DB and notifies

### `SampleGameTest.php`

Must include annotated tests for all of these scenarios, with comments
explaining the pattern so CC can adapt them:

```php
// ── Happy path ─────────────────────────────────────────────────────

public function test_valid_set_scores_and_advances_state(): void {
    // CC PATTERN: happy path — given setup → when action → then state + notification
    $this->givenActivePlayer(1)
         ->givenState('playerTurn')
         ->givenDatabaseRows('card', [
             ['card_id' => 1, 'card_type' => 'red',   'card_location' => 'hand', 'card_location_arg' => 1],
             ['card_id' => 2, 'card_type' => 'green',  'card_location' => 'hand', 'card_location_arg' => 1],
             ['card_id' => 3, 'card_type' => 'blue',   'card_location' => 'hand', 'card_location_arg' => 1],
         ]);

    $result = $this->whenAction('action_playSet', ['card_ids' => [1, 2, 3]]);

    $result->assertSucceeded();
    $this->thenNotificationSent('setPlayed', ['player_id' => 1, 'score' => 1]);
    $this->thenDatabaseHas('card', ['card_location' => 'discard', 'card_id' => 1]);
    $this->thenStateShouldBe('playerTurn'); // next player's turn
}

// ── Invalid input ──────────────────────────────────────────────────

public function test_invalid_set_rejected_with_error(): void {
    // CC PATTERN: invalid action — assert specific BGA error code thrown
    $this->givenActivePlayer(1)
         ->givenState('playerTurn')
         ->givenDatabaseRows('card', [/* two matching, one not */]);

    $result = $this->whenAction('action_playSet', ['card_ids' => [1, 2, 3]]);

    $result->assertFailedWith('invalidSet');
    $this->thenNotificationNotSent('setPlayed');
    $this->thenStateShouldBe('playerTurn'); // state unchanged
}

// ── Wrong player ───────────────────────────────────────────────────

public function test_inactive_player_cannot_play(): void {
    // CC PATTERN: wrong actor — checkAction should block this
    $this->givenActivePlayer(1)
         ->givenState('playerTurn');

    // Player 2 tries to act when it's player 1's turn
    $result = $this->whenAction('action_playSet', ['card_ids' => [4, 5, 6]]);

    $result->assertFailedWith('notYourTurn');
}

// ── State transition ───────────────────────────────────────────────

public function test_last_set_triggers_endgame(): void {
    // CC PATTERN: state transition — verify the right transition fires
    $this->givenGameStateValue('cards_remaining', 3)
         ->givenActivePlayer(1);

    $this->whenAction('action_playSet', ['card_ids' => [1, 2, 3]]);

    $this->thenStateShouldBe('endGame');
}

// ── Pure logic ─────────────────────────────────────────────────────

public function test_set_validation_logic(): void {
    // CC PATTERN: pure function — no given/when/then needed, just call directly
    $validSet = [
        ['type' => 'red', 'number' => 1, 'shading' => 'solid'],
        ['type' => 'green', 'number' => 2, 'shading' => 'striped'],
        ['type' => 'blue', 'number' => 3, 'shading' => 'open'],
    ];
    $this->assertTrue($this->game->_checkValidSet($validSet));

    $invalidSet = $validSet;
    $invalidSet[2]['type'] = 'red'; // two reds, not all different or all same
    $this->assertFalse($this->game->_checkValidSet($invalidSet));
}
```

---

## Phase 7: JS Stub Layer

### `harness/js/bgaStubs.js`

Minimal Jest-compatible mocks for BGA globals:

```javascript
// Mocks the dojo global that BGA's JS assumes exists
global.dojo = {
    place: jest.fn(),
    query: jest.fn(() => ({ forEach: jest.fn(), length: 0 })),
    style: jest.fn(),
    addClass: jest.fn(),
    removeClass: jest.fn(),
    connect: jest.fn(),
    on: jest.fn(),
    animateProperty: jest.fn(() => ({ play: jest.fn() })),
    require: jest.fn(),
};

// Mocks the gameui global
global.gameui = {
    player_id: 1,
    gamedatas: {},
    addTooltip: jest.fn(),
    ajaxcall: jest.fn(),
    showMessage: jest.fn(),
};

// BGA notification mock — call triggerNotification in tests
const notificationHandlers = {};
global.bgaNotification = {
    register: (type, handler) => { notificationHandlers[type] = handler; },
    trigger: (type, args) => {
        if (notificationHandlers[type]) notificationHandlers[type]({ args });
    },
};
```

### `jest.config.js`

```javascript
module.exports = {
    testEnvironment: 'node',
    setupFiles: ['./harness/js/bgaStubs.js'],
    testMatch: ['**/*.test.js'],
};
```

### `harness/example/sampleUtils.test.js`

Example Jest tests for extractable game logic:

```javascript
import { isValidSet, calculateScore, getLegalMoves } from '../../src/setgame.utils.js';

describe('isValidSet', () => {
    test('all same color is valid', () => { /* ... */ });
    test('all different colors is valid', () => { /* ... */ });
    test('two same one different is invalid', () => { /* ... */ });
});

describe('calculateScore', () => {
    test('base score for standard set', () => { /* ... */ });
    test('bonus multiplier for 3-in-a-row', () => { /* ... */ });
});
```

---

## Phase 8: README

Structure:

### Installation (two paths)

**Skill only** (no testing infrastructure needed):
```
1. Clone this repo
2. Add to your Claude Code workspace
3. Reference SKILL.md in your workspace settings
```

**Full harness**:
```
1. Clone this repo into your BGA game's dev dependencies
2. composer require --dev your-handle/bga-dev-skill
3. npm install --save-dev bga-dev-skill
4. Extend BgaGameTestCase in your tests
```

### Using with Claude Code

Show 5 example prompts that now work correctly:

```
"What happens if the active player submits an empty card selection?"
→ CC generates a PHPUnit test using BgaGameTestCase

"Write tests for the _calculateScore function"
→ CC generates both a PHP unit test and a Jest test

"Generate the states.inc.php for a game with a bidding phase and a resolution phase"
→ CC uses the state machine skill to produce correct structure

"I'm getting a PHP error on line 47 of my action file — what's wrong?"
→ CC has context about valid BGA action method patterns

"How do I notify only the active player when they draw a card?"
→ CC uses the notifications skill to give the exact notifyPlayer call
```

### Running Tests

```bash
# PHP
./vendor/bin/phpunit

# JS
npm test
```

---

## What CC Should NOT Build (Scope Boundaries)

- No MCP server (CC handles code gen natively)
- No SFTP deployment tooling (out of scope for v1)
- No log tailing (requires live Studio infrastructure)
- No Playwright tests (v2 — note in README as planned)
- No BGA Studio API integration of any kind
- No game-specific logic (harness is game-agnostic)

---

## Definition of Done

Each phase is done when:
- [ ] Files exist and contain real implementation (not stubs)
- [ ] `composer install && ./vendor/bin/phpunit` passes with no errors
- [ ] `npm install && npm test` passes with no errors
- [ ] The example game tests all pass against the example game
- [ ] CC can answer "what happens when a player plays an invalid set" by
      generating a test that actually runs and fails correctly before the
      fix, then passes after

The repo is ready to publish when all phases are complete and the README
install instructions work on a clean machine.
