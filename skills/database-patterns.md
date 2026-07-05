# Database Patterns

## 1) Deal cards from deck to hand

Raw SQL:
```sql
UPDATE card
SET card_location = 'hand', card_location_arg = 1
WHERE card_location = 'deck'
LIMIT 3;
```

PHP:
```php
$this->DbQuery("UPDATE card SET card_location='hand', card_location_arg=1 WHERE card_location='deck' LIMIT 3");
```

## 2) Move one card between locations

Raw SQL:
```sql
UPDATE card
SET card_location = 'discard', card_location_arg = 0
WHERE card_id = 42;
```

PHP:
```php
$this->DbQuery("UPDATE card SET card_location='discard', card_location_arg=0 WHERE card_id=42");
```

## 3) Increment player score

Raw SQL:
```sql
UPDATE player
SET player_score = player_score + 1
WHERE player_id = 1;
```

PHP:
```php
$this->DbQuery("UPDATE player SET player_score = player_score + 1 WHERE player_id = 1");
```

## 4) Get all cards in a location

Raw SQL:
```sql
SELECT card_id, card_type, card_location_arg
FROM card
WHERE card_location = 'hand' AND card_location_arg = 1;
```

PHP:
```php
$cards = $this->getCollectionFromDB("SELECT card_id, card_type, card_location_arg FROM card WHERE card_location='hand' AND card_location_arg=1");
```

## 5) Get a specific player's hand count

Raw SQL:
```sql
SELECT COUNT(*)
FROM card
WHERE card_location = 'hand' AND card_location_arg = 1;
```

PHP:
```php
$count = (int) $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='hand' AND card_location_arg=1");
```

## 6) Check if player has a specific card

Raw SQL:
```sql
SELECT card_id
FROM card
WHERE card_id = 42 AND card_location = 'hand' AND card_location_arg = 1;
```

PHP:
```php
$card = $this->getObjectFromDB("SELECT card_id FROM card WHERE card_id=42 AND card_location='hand' AND card_location_arg=1");
$hasCard = $card !== null;
```
