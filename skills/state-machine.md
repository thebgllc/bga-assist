# State Machine Patterns

## Minimal 3-State Flow

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
    'description' => clienttranslate('${actplayer} must play or pass'),
    'type' => 'activeplayer',
    'possibleactions' => ['playSet', 'pass'],
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

## Multi-Active Voting Example

```php
3 => [
  'name' => 'vote',
  'description' => clienttranslate('All players vote'),
  'type' => 'multipleactiveplayer',
  'possibleactions' => ['voteYes', 'voteNo'],
  'transitions' => ['allVoted' => 4],
],
```

Server flow:
1. gamestate_setAllPlayersMultiactive() when entering vote state.
2. After each vote, gamestate_setPlayerNonMultiactive(playerId, 'allVoted').
3. When last player resolves, transition triggers.

## Required Keys by State Type
- manager: name, type, action, transitions (except final gameEnd).
- activeplayer: name, description, type, possibleactions, transitions.
- multipleactiveplayer: name, description, type, possibleactions, transitions.

## Common Mistakes
- Missing possibleactions in active states.
- Calling gamestate_nextState with a transition name not declared in states.inc.php.
- Using activeplayer where simultaneous decisions require multipleactiveplayer.
