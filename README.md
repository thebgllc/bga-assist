# bga-assist

## What This Is
This repository gives you two things for BGA development with Claude Code: a ready-to-use skill file and a local testing harness. The skill file (`SKILL.md`) teaches Claude Code the project conventions and generation patterns, while the harness provides PHP and JS test scaffolding so generated logic can be validated locally. Use the skill alone if you only want better code generation, or use the full harness if you also want runnable tests.

## Installation

### Skill Only (No Testing Infrastructure)

1. Clone or download this repository.
2. Add `SKILL.md` to your Claude Code workspace instructions.

### Full Harness

1. Clone this repo into your BGA game's development dependencies.
2. Install PHP support:

```bash
composer require --dev thebgllc/bga-assist
```

3. Install JS support:

```bash
npm install --save-dev bga-assist
```

4. Extend `BgaGameTestCase` in your PHPUnit tests.

## Using with Claude Code

These prompts are designed to map directly to the tested patterns in `SampleGameTest.php`:

1. "What happens if the active player submits only 2 cards?"
2. "Write tests for the _checkValidSet function"
3. "Generate a states.inc.php for a game with a bidding phase and a resolution phase"
4. "I'm getting a PHP error in my action method - what's wrong?"
5. "How do I notify only the active player when they draw a card?"

## Running Tests

```bash
# PHP
./vendor/bin/phpunit

# JS
npm test
```

## Scope Boundaries

This v1 repo intentionally does not include:

- MCP server setup
- SFTP deployment tooling
- Studio log tailing
- Playwright live Studio E2E tests
- BGA Studio API integration
- Game-specific production implementations
