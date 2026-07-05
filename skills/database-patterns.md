# Database Patterns

Use only the database vocabulary implemented in the harness. Do not use raw PDO in game classes.

## Method Vocabulary

Use these methods exactly:

- `DbQuery(string $sql): void`
- `getCollectionFromDB(string $sql, bool $bUniqueValue = false): array`
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

Use `getCollectionFromDB` for list queries.

```php
$cards = $this->getCollectionFromDB(
    "SELECT card_id, card_type, card_number, card_shading FROM card WHERE card_location = 'deck' ORDER BY card_id LIMIT 3"
);
```

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
