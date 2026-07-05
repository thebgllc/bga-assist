# JS Dojo Patterns

## Module Wrapper

```javascript
define(['dojo', 'dojo/_base/declare', 'ebg/core/gamegui'], function(dojo, declare) {
  return declare('bgagame.setgame', ebg.core.gamegui, {
    constructor: function() {},
    setup: function(gamedatas) {
      this.gamedatas = gamedatas;
      this.setupNotifications();
    }
  });
});
```

## ajaxcall Pattern

```javascript
this.ajaxcall('/setgame/setgame/playSet.html', {
  lock: true,
  card_ids: [1, 2, 3]
}, this, function(result) {
  // success
}, function(isError) {
  if (isError) {
    this.showMessage('Action failed', 'error');
  }
});
```

## Tooltips

```javascript
this.addTooltip('card_42', 'Card title', 'Card details');
this.addTooltipToClass('card-slot', 'Slot title', 'Slot help text');
```

## DOM/Event Helpers
- Use dojo.place for inserting markup.
- Use dojo.query for selection.
- Use dojo.connect or dojo.on for event binding.

## Notification Registration

```javascript
setupNotifications: function() {
  dojo.subscribe('setPlayed', this, 'notif_setPlayed');
},

notif_setPlayed: function(notif) {
  const args = notif.args;
  // update UI
}
```

## Animation

```javascript
dojo.animateProperty({
  node: 'card_42',
  properties: { left: 300, top: 120 },
  duration: 350
}).play();
```
