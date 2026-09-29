# Database Patterns

Use only the database vocabulary implemented in the harness. Do not use raw PDO in game classes.

## Method Vocabulary

Use these methods exactly:

- `DbQuery(string $sql): void`
- `getCollectionFromDB(string $sql, bool $bUniqueValue = false): array` — keyed by the first column
- `getObjectListFromDB(string $sql, bool $bUniqueValue = false): array` — plain 0-indexed list
- `getObjectFromDB(string $sql): ?array`
- `getUniqueValueFromDB(string $sql): mixed`
- `getIntFromDB(string $sql): int`

## Pattern: Write with DbQuery

Use `DbQuery` for INSERT/UPDATE/DELETE and DDL.

```php
$this->DbQuery(
    "CREATE TABLE IF NOT EXISTS card (" .
    "card_id INTEGER PRIMARY KEY, " .
    "card_type TEXT, " .
    "card_number INTEGER, " .
    "card_shading TEXT, " .
    "card_location TEXT, " .
    "card_location_arg INTEGER)"
);
```

```php
$this->DbQuery("UPDATE card SET card_location = 'discard' WHERE card_id = " . (int) $cardId);
```

## Pattern: Read many rows

`getCollectionFromDB` returns rows **keyed by the first selected column**. Rows that share a first-column value silently overwrite each other — you get fewer rows and no error, which quietly breaks adjacency lists, cycle detection, histories, anything with a repeated id. So:

- put a unique column (the primary key) first, or
- use `getObjectListFromDB($sql)`, a plain 0-indexed list, whenever the first column may repeat.

```php
// Bad: a player with two bids has one row; the earlier bid is gone.
$bids = $this->getCollectionFromDB('SELECT player_id, amount FROM bid ORDER BY id');

// Good: every row kept.
$bids = $this->getObjectListFromDB('SELECT player_id, amount FROM bid ORDER BY id');

// Also good: unique column first, so the key is useful and nothing collapses.
$cards = $this->getCollectionFromDB(
    "SELECT card_id, card_type, card_number, card_shading FROM card WHERE card_location = 'deck' ORDER BY card_id LIMIT 3"
);
```

The harness keys `getCollectionFromDB` the same way, so a collapsed row fails a test rather than a live table (see `ModernSampleGameTest::test_bid_history_keeps_rows_with_a_repeated_first_column`).

If you need key => value shape and query returns two columns, use `bUniqueValue = true`.

```php
$counts = $this->getCollectionFromDB(
    "SELECT card_location_arg, COUNT(*) FROM card WHERE card_location = 'hand' GROUP BY card_location_arg",
    true
);
```

## Pattern: Read one row

Use `getObjectFromDB` when one row is expected.

```php
$row = $this->getObjectFromDB('SELECT * FROM card WHERE card_id = ' . (int) $cardId);
if ($row === null) {
    $this->throwUserError('invalidSet');
}
```

## Pattern: Read one scalar

Use `getUniqueValueFromDB` for scalar values and cast explicitly when needed.

```php
$score = (int) $this->getUniqueValueFromDB('SELECT player_score FROM player WHERE player_id = ' . $playerId);
```

Use `getIntFromDB` for count/int-only reads.

```php
$remaining = $this->getIntFromDB("SELECT COUNT(*) FROM card WHERE card_location = 'deck'");
```

## Pattern: Dealing cards from deck to hand

```php
$cardsToDeal = $this->getCollectionFromDB(
    "SELECT card_id FROM card WHERE card_location = 'deck' ORDER BY card_id LIMIT 3"
);

foreach ($cardsToDeal as $card) {
    $this->DbQuery(sprintf(
        "UPDATE card SET card_location = 'hand', card_location_arg = %d WHERE card_id = %d",
        $playerId,
        (int) $card['card_id']
    ));
}
```

## Pattern: Atomic deal (avoid SELECT-then-UPDATE races)

A separate `SELECT` then `UPDATE` opens a gap where two players can be dealt the same cards. When you don't need the drawn ids in PHP, deal in **one statement** so the selection and the move are atomic:

```php
// Deal HAND_SIZE random cards to one player, atomically.
$this->DbQuery(
    "UPDATE card
     SET card_location = 'hand', card_location_arg = $playerId
     WHERE card_location = 'deck'
     ORDER BY RAND()
     LIMIT " . self::HAND_SIZE
);
```

Drawing a single card where you *do* need the row: `SELECT ... ORDER BY RAND() LIMIT 1`, then `UPDATE ... WHERE card_id = $id`. This is safe because the whole action runs inside one transaction (below) — no other action interleaves.

## Transaction Model: Don't Wrap Actions Yourself

BGA already wraps **every player action in a DB transaction** and rolls back all its mutations if any exception is thrown. Consequences:

- **Never add `START TRANSACTION` / `BEGIN` in game logic.** Issuing one *implicitly commits* the framework's outer transaction, defeating the automatic rollback.
- To abort a partially-applied action, just `throw` (a `UserException` for player-facing failures). Every `DbQuery` already run in that action rolls back cleanly — records can't get stuck in an intermediate location.
- This is what makes "fail loud" safe: on a should-never-happen condition, throw rather than silently building bad state.

```php
// Rebuild the whole table from a validated proposal. No manual transaction —
// if any DbQuery below throws, the limbo UPDATE, the DELETE, and partial
// INSERTs all roll back together.
$this->DbQuery("UPDATE card SET card_location='limbo' WHERE card_id IN ($tableIds)");
$this->DbQuery('DELETE FROM meld');
foreach ($proposedMelds as $spec) {
    $data = $tableCards[$cid] ?? $handCards[$cid] ?? null;
    if (!$data) {
        // Validation already guaranteed this exists; fail loud (and roll back)
        // rather than build a meld silently missing cards.
        throw new UserException(clienttranslate('Internal error: card not found'));
    }
    // ... INSERT meld, UPDATE card locations ...
}
```

## Deck Component Methods (Harness)

In this harness, deck usage is intentionally minimal:

- `createDeck(string $deckId): BgaDeckStub`
- `BgaDeckStub::addCard(array $card): void`
- `BgaDeckStub::count(): int`
- `BgaDeckStub::getDeckId(): string`

Use this stub only for in-memory deck behavior in tests or simplified examples.

## Numeric Cast Pattern

DB values can arrive as strings. Cast numeric fields before arithmetic/comparison.

```php
$players = $this->getCollectionFromDB("SELECT player_id id, player_score score FROM player");
foreach ($players as $id => $player) {
    foreach ($player as $key => $value) {
        if (preg_match('/^-?\\d+$/', (string) $value)) {
            $players[$id][$key] = (int) $value;
        }
    }
}
```

## SQL Injection Warning

Never concatenate unsanitized user input into SQL strings.

Rules:
- Cast numeric IDs with `(int)` before interpolation.
- Validate string inputs against a known allow-list before interpolation.
- If a freeform string must be interpolated, sanitize first (`addslashes`) and document why.

Bad:

```php
$this->getCollectionFromDB("SELECT * FROM card WHERE card_type = '$type'");
```

Good:

```php
if (!in_array($type, ['red', 'green', 'blue'], true)) {
    $this->throwUserError('invalidType');
}
$this->getCollectionFromDB("SELECT * FROM card WHERE card_type = '$type'");
```

## dbmodel.sql: no trailing `--` comments, no apostrophes

BGA splits `dbmodel.sql` into statements with its own parser before MySQL sees it. A trailing comment after a column definition can swallow that column, or everything after it. `CREATE TABLE IF NOT EXISTS` still reports success, and the first symptom is `Unknown column 'x' in 'field list'` at createGame or on the first action. Lattaque lost a Ph1 play-test on 2026-07-27 to `-- 10..1; 0 for Mine and Spy` after a column.

- Comments go on their own lines, never after a column definition. The stock file's whole-line comments are fine. The blanket rule "no `--` at all" is also safe, with longer documentation kept in `docs/` instead.
- No apostrophes anywhere in the file, comments included, because the splitter tracks quotes.

```sql
-- Bad: the comment can take the columns after it with it
  `piece_rank` TINYINT NOT NULL, -- 10..1; 0 for Mine and Spy
-- Good
-- piece_rank: 10..1; 0 for Mine and Spy
  `piece_rank` TINYINT NOT NULL,
```

Enforce it with a test that reads `dbmodel.sql` line by line (Entropy: `tests/SchemaDriftTest.php`).

## Table names: never collide with BGA's own tables

BGA creates its own tables before `dbmodel.sql` runs: `player`, `global`, `stats`, `gamelog`, `moves`, `replaysavepoint`, anything `bga_*`, plus the undo copies when `db_undo_support` is on. If you reuse one of those names, `CREATE TABLE IF NOT EXISTS` does nothing and reports no error, and your columns never exist. Prefixing every table with the game slug (`mygame_moves`, `mygame_board`) rules this out. Distinctive domain names like Entropy's `planet` and `biome` also work, but a generic name like `moves` or `log` does not.

`dbmodel.sql` runs **once, when a table is created**. Redeploying a changed schema does nothing to existing tables. Quit every open instance and start a fresh table. Once the game is live, a schema change needs `upgradeTableDb` instead.

Sources / further reading: [rbellec/claude-code-bga](https://github.com/rbellec/claude-code-bga) (MIT), TECHNICAL_NOTES.md.
