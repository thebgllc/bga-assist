# BGA Notification Patterns

This skill is derived from real code in the rage project. Every example is
pulled directly from working PHP and JS — not documentation speculation.

---

## The Contract That Must Match Exactly

A notification is a named data packet PHP sends to all clients (or one client).
The name on the PHP side and the handler method name on the JS side must match
by a specific convention. A mismatch produces no error — the notification is
silently dropped and your UI does not update.

### PHP side — modern framework

```php
// $this->notify->all(type, message, data)
$this->notify->all("trump_change", clienttranslate('Trump is now ${trump_html}'), [
    'trump'      => $trump,    // the card object
    'trump_html' => $html,     // pre-rendered HTML for the log message
]);

// $this->notify->player(playerId, type, message, data)
$this->notify->player($playerId, "dealtCards", '', [
    'cards' => $cards,
]);
```

### JS side — modern framework (ES module)

```javascript
// Method name = "notif_" + type — must match exactly, including case
async notif_trump_change(args) {
    this.gamedatas.trump = args.trump;    // args.trump, not args.card
    this.renderTrump(args.trump);
    // args.trump_html exists but is only used in the log — JS doesn't need it
}

async notif_dealtCards(args) {
    this.gamedatas.hand = args.cards;    // args.cards, matches PHP 'cards' key
    this.renderHand();
}
```

### The naming rules

| PHP type string | JS handler name |
|---|---|
| `"trump_change"` | `notif_trump_change` |
| `"dealtCards"` | `notif_dealtCards` |
| `"actionBid"` | `notif_actionBid` |
| `"winningCardNotif"` | `notif_winningCardNotif` |
| `"scoringNotif"` | `notif_scoringNotif` |

The prefix is always `notif_`. After that, the string is copied verbatim —
`trump_change` stays `trump_change`, not `trumpChange`.

---

## The Casing Gotcha (Most Common Silent Bug)

PHP array keys are sent as-is to JS. `notif.args` keys must match what PHP
put in the data array — character for character.

```php
// PHP sends snake_case
$this->notify->all("winningCardNotif", '...', [
    'player_id' => $pid,    // snake_case
    'plus5'     => $plus5,  // no underscore
    'minus5'    => $minus5,
]);
```

```javascript
// JS must read the same keys
async notif_winningCardNotif(args) {
    const pid = args.player_id;   // ✅ snake_case matches
    this.gamedatas.plus5  = args.plus5;
    this.gamedatas.minus5 = args.minus5;
    // args.playerId would silently be undefined
}
```

Never camelCase a key on the JS side that PHP sent as snake_case. There is no
error. The value will be undefined and your handler will silently misrender.

---

## Data Shapes from rage (Reference)

These are the exact payloads for each notification type in rage.
When generating tests or writing new notifications, use these as the canonical
shape.

### `trump_change`
```php
// PHP
$this->notify->all("trump_change", clienttranslate('Trump is now ${trump_html}'), [
    'trump'      => $trump,      // full card row from DB: {id, type, type_arg, location, location_arg}
    'trump_html' => $html,       // pre-rendered HTML string for log only
]);
```
```javascript
// JS — only trump is used; trump_html is consumed by the log renderer automatically
async notif_trump_change(args) {
    this.gamedatas.trump = args.trump;
    this.renderTrump(args.trump);
}
```

### `dealtCards`
```php
// PHP — sent only to the receiving player (notify->player, not ->all)
$this->notify->player($playerId, "dealtCards", '', [
    'cards' => $this->cards->getPlayerHand($playerId),
]);
```
```javascript
async notif_dealtCards(args) {
    this.gamedatas.hand = args.cards;   // keyed by card id
    this.renderHand();
}
```

### `actionBid`
```php
$this->notify->all("actionBid", clienttranslate('${player_name} bids ${bid}'), [
    'player_id'   => $playerId,
    'player_name' => $this->getPlayerNameById($playerId),
    'bid'         => $bid,
]);
```
```javascript
async notif_actionBid(args) {
    const player_id = args.player_id;
    this.gamedatas.players[player_id].bid = args.bid;
    document.getElementById('tricks_' + player_id).innerText = args.bid;
}
```

### `actionPlayCard`
```php
// card has an extra 'color' key when a joker was played
$this->notify->all("actionPlayCard", clienttranslate('${player_name} plays ${card_html}'), [
    'player_id'   => $playerId,
    'player_name' => $this->getPlayerNameById($playerId),
    'card'        => $card,        // full card object, including location_arg = player_id
    'card_html'   => $cardHtml,
]);
```
```javascript
async notif_actionPlayCard(args) {
    const card = args.card;        // args.card, not args.card_id
    // card.location_arg is the player_id who played it
    if (card.type === 'joker') {
        this.gamedatas.players[card.location_arg].joker_color = card.color;
    }
    this.gamedatas.table.push(card);
    this.addCardTo(document.getElementById('table_cards'), card, true);
    await this.bga.gameui.wait(600);
}
```

### `winningCardNotif`
```php
$this->notify->all("winningCardNotif", clienttranslate('${player_name} wins the trick'), [
    'player_id'   => $winnerId,
    'player_name' => $this->getPlayerNameById($winnerId),
    'plus5'       => $plus5CountByPlayer,   // array keyed by player_id
    'minus5'      => $minus5CountByPlayer,
]);
```
```javascript
async notif_winningCardNotif(args) {
    const pid = args.player_id;
    this.gamedatas.plus5  = args.plus5;
    this.gamedatas.minus5 = args.minus5;
    await this.bga.gameui.wait(2000);
    // clear table, increment tricks_taken, update UI
    this.gamedatas.players[pid].tricks_taken++;
    this.updateTricks(pid);
}
```

### `revealBids`
```php
// Only sent for hidden-bidding variants
$this->notify->all("revealBids", clienttranslate('Revealed bids: ${html}'), [
    'bids' => $bids,   // {player_id: bid_amount, ...}
    'html' => $html,   // rendered string for log
]);
```
```javascript
async notif_revealBids(args) {
    for (const player_id in args.bids) {
        this.gamedatas.players[player_id].bid = args.bids[player_id];
        this.updateTricks(player_id);
    }
}
```

---

## Notification Setup — Modern Framework

```javascript
setupNotifications() {
    // One-liner in the modern framework — registers all notif_* methods automatically
    this.bga.notifications.setupPromiseNotifications();
}
```

This replaces the old Dojo pattern of manually calling
`this.notifqueue.subscribe()` for each type. All `async notif_*` methods on
the Game class are registered automatically.

**Do not** call `setSynchronous()` — the modern promise-based notifications
handle sequencing via `await`.

---

## Pre-rendered HTML in Notification Messages

PHP frequently builds an HTML string to embed in the log message:

```php
public function getCardHtml($card): string
{
    $special = isset($this->special_cards[$card["type"]]) ? "special" : "";
    $val     = $special
        ? $this->special_cards[$card["type"]]["short"]
        : $card["type_arg"];
    $color   = isset($card["color"]) ? $card["color"] : "";
    return "<span class=\"card_type {$card['type']} {$color} {$special}\">{$val}</span>";
}

// Used in a notification message template:
$this->notify->all("trump_change", clienttranslate('Trump is now ${trump_html}'), [
    'trump'      => $trump,
    'trump_html' => $this->getCardHtml($trump),
]);
```

The `${trump_html}` in the message template is substituted by BGA's log
renderer using the `trump_html` value from the data array. The JS handler does
not need to use `args.trump_html` at all — it reads `args.trump` (the card
object) to update the UI.

**Pattern:** always name pre-rendered HTML keys with a `_html` suffix so it's
clear which keys are for the log vs which are for UI updates.

---

## What notify->all vs notify->player Sends

```php
// All players receive this (including spectators)
$this->notify->all("trump_change", $msg, $data);

// Only this specific player receives it (used for private hand info)
$this->notify->player($playerId, "dealtCards", '', $data);
```

When a player reloads, `getAllDatas()` rebuilds their state. The
`notify->player` call is for real-time updates during play only. If a player
disconnects and reconnects, they get `getAllDatas()` — not replayed
notifications.

---

## Common Bugs

**Bug 1: JS key doesn't match PHP key**
```php
$this->notify->all("bid", '...', ['player_id' => $id, 'bid_amount' => $bid]);
```
```javascript
async notif_bid(args) {
    const bid = args.bid;         // ❌ undefined — PHP key is 'bid_amount'
    const bid = args.bid_amount;  // ✅
}
```

**Bug 2: Forgetting notify->player for private data**
```php
// ❌ Sends all players' hands to everyone
$this->notify->all("dealtCards", '', ['cards' => $allCards]);

// ✅ Each player only sees their own hand
foreach ($players as $playerId => $player) {
    $this->notify->player($playerId, "dealtCards", '', [
        'cards' => $this->cards->getPlayerHand($playerId),
    ]);
}
```

**Bug 3: Using old notify API in modern framework**
```php
// ❌ Old API — works but inconsistent with modern codebase
$this->notifyAllPlayers("trump_change", $msg, $data);
$this->notifyPlayer($playerId, "dealtCards", '', $data);

// ✅ Modern API
$this->notify->all("trump_change", $msg, $data);
$this->notify->player($playerId, "dealtCards", '', $data);
```

**Bug 4: Synchronous notification handler when awaiting animation**
```javascript
// ❌ Returns before animation completes — next notification fires immediately
notif_winningCardNotif(args) {
    setTimeout(() => { /* clear table */ }, 2000);
}

// ✅ Promise-based — next notification waits for this to resolve
async notif_winningCardNotif(args) {
    await this.bga.gameui.wait(2000);
    document.getElementById('table_cards').innerHTML = '';
}
```
