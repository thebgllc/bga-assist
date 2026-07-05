# BGA Scaffold Templates

## gamename.game.php

```php
class game_name extends Table
{
    public function __construct()
    {
        parent::__construct();
        self::initGameStateLabels([]);
    }

    protected function setupNewGame($players, $options = [])
    {
        // setup logic
    }
}
```

## gamename.action.php

```php
class action_game_name extends APP_GameAction
{
    public function playCard()
    {
        self::setAjaxMode();
        $cardId = (int) self::getArg('card_id', AT_int, true);
        $this->game->action_playCard($cardId);
        self::ajaxResponse();
    }
}
```

## gamename.view.php

```php
class view_game_name_game_name extends game_view
{
    function getGameName()
    {
        return 'game_name';
    }

    function build_page($viewArgs)
    {
        // page setup
    }
}
```

## gamename.js

```javascript
define(['dojo', 'dojo/_base/declare', 'ebg/core/gamegui'], function(dojo, declare) {
  return declare('bgagame.game_name', ebg.core.gamegui, {
    setup: function(gamedatas) {
      this.gamedatas = gamedatas;
      this.setupNotifications();
    },

    setupNotifications: function() {
      dojo.subscribe('cardPlayed', this, 'notif_cardPlayed');
    },

    notif_cardPlayed: function(notif) {}
  });
});
```

## states.inc.php

```php
$machinestates = [
  1 => [
    'name' => 'gameSetup',
    'type' => 'manager',
    'action' => 'stGameSetup',
    'transitions' => ['' => 2],
  ],
  2 => [
    'name' => 'playerTurn',
    'type' => 'activeplayer',
    'description' => clienttranslate('${actplayer} must play a card'),
    'possibleactions' => ['playCard'],
    'transitions' => ['nextPlayer' => 2, 'endGame' => 99],
  ],
  99 => [
    'name' => 'gameEnd',
    'type' => 'manager',
    'action' => 'stGameEnd',
    'args' => 'argGameEnd',
  ],
];
```

## dbmodel.sql

```sql
CREATE TABLE IF NOT EXISTS `player` (
  `player_id` int(10) unsigned NOT NULL,
  `player_score` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```
