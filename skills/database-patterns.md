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
