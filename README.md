# bga-dev-skill

CC skill and testing harness for BoardGameArena game development.

## Installation

### Skill only

1. Clone this repo.
2. Add this repo to your Claude Code workspace.
3. Reference SKILL.md in workspace instructions.

### Full harness

1. Clone this repo into your BGA game's development dependencies.
2. Run composer install.
3. Run npm install.
4. Extend BgaGameTestCase in your PHPUnit tests.

## Using with Claude Code

Example prompts now supported:

"What happens if the active player submits an empty card selection?"
- Generates a PHPUnit test using BgaGameTestCase.

"Write tests for the _calculateScore function"
- Generates PHP and Jest-oriented test patterns.

"Generate states.inc.php for a game with a bidding phase and a resolution phase"
- Uses the state-machine skill patterns.

"I am getting a PHP error in my action file. What is wrong?"
- Uses BGA action method and sanitization conventions.

"How do I notify only the active player when they draw a card?"
- Uses notifications skill and exact notifyPlayer patterns.

## Running Tests

```bash
# PHP
./vendor/bin/phpunit

# JS
npm test
```

## Scope Boundaries

Version 1 intentionally excludes:
- MCP server setup
- SFTP deployment tooling
- Live log tailing
- Playwright Studio E2E tests
- Studio API integration
- Game-specific production logic
