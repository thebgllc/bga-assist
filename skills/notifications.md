# Notifications Contract

## Core Rule
Keep PHP payload keys and JS notif.args keys aligned exactly. Do not rename keys between layers.

## notifyAllPlayers

PHP:
```php
$this->notifyAllPlayers('setPlayed', '', [
  'player_id' => $playerId,
  'card_ids' => $cardIds,
  'score' => $score,
]);
```

JS:
```javascript
dojo.subscribe('setPlayed', this, 'notif_setPlayed');

notif_setPlayed: function(notif) {
  const playerId = notif.args.player_id;
  const cardIds = notif.args.card_ids;
  const score = notif.args.score;
}
```

## notifyPlayer

PHP:
```php
$this->notifyPlayer($playerId, 'privateDraw', '', [
  'card_id' => $cardId,
]);
```

JS:
```javascript
dojo.subscribe('privateDraw', this, 'notif_privateDraw');

notif_privateDraw: function(notif) {
  const cardId = notif.args.card_id;
}
```

## Common Bug
PHP sends card_id while JS expects cardId. Keep one naming style and reuse it everywhere.
