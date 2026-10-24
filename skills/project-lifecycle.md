# Project Lifecycle & Repo Conventions

Conventions distilled from the games that have gone furthest through this
process (Salvage Divers, Top This). Apply these from the start of a new game
repo so they don't need retrofitting later — see Fill the Rows' Ph0 cleanup
pass for what it costs to add `docs/`, a test suite, and a soak test after
the fact instead of from day one.

## Repo layout

At the top level, alongside `modules/`:

```text
docs/
  RULES.md              # canonical player-facing rules — the truth source
  ARCHITECTURE.md        # engine/design notes, if the game has real internal structure
  IMPLEMENTATION_PLAN.md # phased plan (see below) — living doc, updated every phase
  SUCCESS_METRICS.md     # acceptance gates for a tuning simulator, if one exists
  AUTHOR_QUESTIONS.md    # licensed adaptations: open rules questions for the designer
  ASSUMPTIONS.md         # licensed adaptations: every [Hx] assumption made meanwhile
tests/
  bootstrap.php           # modern-framework shim (Table/GameState stand-ins over
                           # in-memory SQLite) — check whether this repo's own
                           # harness/ covers it before hand-rolling a new one
tools/
  sim/                     # tuning simulator, if the game's balance needs numeric
                           # tuning before/alongside BGA UI work (see Phase 0-2 below)
web/
  <slug>-landing.html      # per-game landing page for thebgllc.com
composer.json
phpunit.xml
```

Keep `README.md`'s "Project Layout" section accurate as the repo evolves — a
stale layout diagram pointing at a file that moved or was renamed is worse
than no diagram at all.

## Rules clarifications (licensed adaptations)

When adapting someone else's game, the rulebook will have gaps. **Don't block implementation waiting for the designer's answers.** Classify each question, make a reasonable assumption, and write it down:

- `docs/AUTHOR_QUESTIONS.md`: one entry per question, tagged **OPEN / ASSUMED / CLOSED** and typed **RULES-MISSING** (not covered), **RULES-AMBIGUOUS** (two readings), **RULES-IMPLICIT** (obvious at a physical table, but code has to decide) or **FEEDBACK** (a suggestion for the designer, not a question).
- `docs/ASSUMPTIONS.md`: every assumption gets an ID `[H1]`, `[H2]`, … Reference that ID from its question and from a code comment at the place that implements it, so a designer's answer leads straight to the lines to change.

```php
// [H3] Ties on the military track go to the earlier seat (AUTHOR_QUESTIONS.md Q7, ASSUMED).
```

Adapted from [rbellec/claude-code-bga](https://github.com/rbellec/claude-code-bga) (MIT).

## Deploy discipline

**`docs/`, `tools/`, and `tests/` never deploy to BGA Studio.** They're
dev-only. Keep this true by convention — don't reference them from anything
Studio actually loads — rather than by SFTP-exclude tooling; an `sftp.json`
exclude list is unnecessary overhead unless a specific deploy path actually
needs it.

**Production deploys (private alpha onward) are the developer's.** Alpha
builds are built and deployed from the Studio Control Panel by the developer,
not by BGA admins, so a fix can reach the publisher's alpha tables the same
day. Every production deploy:

1. Run `git diff <latest prod-* tag> -- dbmodel.sql`. Any change needs an
   `upgradeTableDb` block first (skills/database-patterns.md →
   "Post-release schema changes"), plus a heads-up to BGA.
2. Build, check the build dialog for errors, deploy.
3. `git tag prod-<build version>` (e.g. `prod-261003-1412`) and push the tag.

## Phased plan template

Structure `docs/IMPLEMENTATION_PLAN.md` in phases. This shape has proven
itself across multiple games:

- **Phase 0 — Scaffolding.** Studio skeleton, git init, `docs/` started,
  `composer.json` + `phpunit.xml` + `tests/bootstrap.php`, directory
  skeleton.
- **Phase 1 — Core engine (+ tuning simulator, if the game needs one).**
  Build the game logic framework-free where possible, so a Monte Carlo
  simulator can drive it and expose every balance dial before any BGA UI
  work. Get to a running simulator as fast as possible — numbers before art.
- **Phase 2 — Full game loop + economy, still simulator-only.**
  Multi-round/multi-player logic, scoring, and dial-tuning against explicit
  success metrics (`docs/SUCCESS_METRICS.md`) — a scorecard of named
  criteria (e.g. no dominant strategy, decision tension stays alive, healthy
  lead volatility, no dead-weight content, sane game length, skill:luck
  ratio in target band). Don't exit this phase until the scorecard reads
  PASS, or an accepted WARN with written rationale.
- **Phases 3 & 4 — BGA backend + frontend, INTERLEAVED.** Do not build the
  whole backend, then the whole UI. A backend with no UI can't be
  meaningfully exercised in the real BGA environment, and a big-bang
  UI-after-backend hits integration surprises exactly when they're most
  expensive. Work in **vertical slices**: each slice adds a backend
  capability *and* the minimal UI needed to drive and observe it, then
  **deploys to Studio and gets played** before the next slice starts.
  Deploy + play-test is the heartbeat of every slice, from slice 1 —
  especially the riskiest integration points (multiactive states, private
  vs public data, deferred reveals) should be the *first* slice, not the
  last.
- **Phase 5 — UX hardening, soak & release readiness.** Budget real time
  for this; "polish pass" undersells it. Animation/pacing, responsive
  layout, reconnect/zombie/spectator robustness (test zombie takeover
  directly, not just via a soak), a content/copy pass (in-game rules panel,
  tooltip consistency), and a zombie soak test (see below) before calling
  it submission-ready.
- **Phase 6 — Art, content & release.** **Request the BGG ID early** — as soon
  as the short + long descriptions exist, submit the BGG listing. That's all
  BGG requires to issue an ID; art, images, and links are added to the entry
  later. Getting an ID back takes time, so kick this off well before the rest
  of the phase is ready, not bundled in at the end behind art/PnP/landing-page
  work. Once the ID exists, for original (self-published) games, add the BGA
  weblink and box/listing art to the BGG entry and grant BGA a publisher
  license (see the release checklist below for the exact steps — skip this
  sub-step for licensed/adapted games, since the rights holder owns that BGG
  listing). Everything else — final art/palette/`player_colors`, rulebook PDF
  generated from `docs/RULES.md`, PnP kit if applicable, landing page on
  thebgllc.com — fills in around that ID once it's issued. Before submitting
  for alpha: a **final code review and UX review** (see below), then three
  distinct BGA checks — the **project-check tool**, a **dry-run code build**,
  and BGA's **pre-alpha checklist** — then private Alpha, then public Alpha.
  Public Alpha is also the milestone at which the game gets linked from
  thebgllc.com — for licensed adaptations too, as a development example (see
  the last item of the release checklist).

Not every game needs every phase at this weight — a small game may collapse
Phases 1–2 or skip the simulator entirely. But the **interleaving lesson in
Phase 3/4 and the deploy-discipline in Phase 0 apply universally.**

## Zombie soak testing

A soak test drives full games to completion using nothing but each zombie
player's random-legal-move policy, across player counts and any
game-option variants, over many seeds. It should assert:

- the game ends cleanly (no exception, no deadlock, no action-cap blow-out)
- table invariants hold (deck/resource conservation, no data stranded in an
  unexpected location)
- every seat gets scored
- the zombie actually took a "real" action at least once per game
  (otherwise a "passes forever" regression looks like a healthy run)

On failure, print the seed/config and a move trace so the exact game
replays. See `harness/example/` for a worked pattern to adapt.

## Bot persona roster (tuning sims, and soaks where it fits)

A tuning simulator with one "greedy" bot answers one question. The
scorecard in Phase 2 asks five, and each needs a different *policy* at the
table. Build the bots as a roster of playtest personas, each written to
break the game in one specific way, and name the metric each one feeds.
These are policies, not difficulty levels: a Turtle is not a weaker
Min-Maxer, it is a different player.

| Persona | Policy | What it tests | Scorecard metric it feeds |
|---|---|---|---|
| **Aggro / Speedster** | Rush the end trigger, take every high-variance line, maximise tempo | Early-game balance and pacing | Can it end the game before the field has executed a plan? → game-length floor, rush win-rate |
| **Turtle** | Hoard, defend, minimise risk, act only when the payoff is certain | Stagnation and catch-up | Two Turtles at one table: does the game still end, and in how many rounds? → stall rate, need for an end trigger |
| **Min-Maxer** | The skill ceiling: search/expectimax, or the best hand-built heuristic | Dominant strategies | Win-rate vs every other persona; if one line wins regardless of the field, the math needs a redesign → skill:luck band |
| **Chaos Agent** | Random legal move, spite moves, kingmaking (help whoever is losing or hurt whoever is winning) | Resilience, kingmaking exposure | How far does one Chaos seat swing the finishing order of the *other* seats? → noise floor, kingmaking index |
| **Sandboxer** | Chase one theme: collect all of one component type, max one track, ignore VP | Fun factor, dead-weight content | Does a mono-focus line ever score, or is it strictly punished? → content usage, "no dead-weight" criterion |

Run the roster in **mixed tables**, not only mirror matches. The telling
seats are: Turtle+Turtle (stalemate), Aggro vs three Turtles (rush), one
Chaos seat among three Min-Maxers (kingmaking swing on the non-Chaos
seats), and one Sandboxer vs the field (is the theme-chaser ever in it?).
Mirror matches still matter for the Min-Maxer, because a mirror of the
ceiling bot sets the game-length and luck baselines.

Reuse where it already exists: Baseline's `Random → Greedy → Expectimax`
ladder covers Chaos / Min-Maxer-lite / Min-Maxer; Salvage Divers'
`Core/Policy` roster already has the Turtle (`RiskThresholdStop`,
`AlwaysLeave`), the Aggro (`TargetDepthStop`, `EndRushHoarder`), the
Sandboxer (`SingleItemFocus`) and a standings-aware kingmaker
(`StandingsAware`). Add a persona only to answer a scorecard question it
owns; a persona nobody reads a number from is noise.

**Zombies.** The BGA zombie is the Chaos Agent at level 1 (random legal
move) or the Turtle at level 0 (pass/minimal). Pick the one the Zombie Mode
metadata declares and keep every state honest to it. The soak above is an
all-Chaos table, which hides stalls because a random player eventually
stumbles into the end trigger. Where the engine lets a harness seat a
persona, run a **Turtle soak** too (one Turtle seat, rest zombies, then
Turtle+Turtle): if that table does not end within the action cap, a live
pair of conservative humans will not end it either, and the fix is an end
trigger or an incentive, not a zombie tweak.

## Static analysis (optional, advisory)

PHPStan, PHP-CS-Fixer, Rector and PHPMD can complement the harness, but treat them as advice. They must never gate a deploy, because they can't see the framework. The one hard-won rule, from rbellec's Rector incident: Rector's `deadCode` set reads an empty stub `Table::__construct() {}` as the truth and **deletes `parent::__construct()` from `Game.php`**. The game then fails to launch with no error, while PHPStan and PHPMD stay green. If you use Rector:

- skip `RemoveParentDelegatingConstructorRector` and `RemoveParentCallWithoutParentRector`;
- never declare `$bga` / `$gamestate` properties on `Game`, because the framework injects them on the parent;
- read the dry-run diff before any automatic fix, and launch a real Studio table after one.

A PHPStan stub of the framework is a separate thing from the harness's runtime `BgaStubs`, so don't conflate them. Source: [rbellec/claude-code-bga](https://github.com/rbellec/claude-code-bga) (MIT), TECHNICAL_NOTES.md.

## Pre-alpha submission gates

Four distinct checks before submitting for private Alpha — don't collapse
them into one vague "testing pass," each catches different things:

1. **Final code review + UX review, done fresh.** A full top-to-bottom pass
   over the whole repo — not just the diff since the last review — confirming
   everything is clean before it goes in front of BGA reviewers or real
   players. Best done in chat (not Claude Code): read every game-logic file
   and the client JS end to end, re-check that every open code-review finding
   was actually resolved (not just marked closed), and separately walk the UX
   as a player would experience it (onboarding clarity, error states, mobile/
   responsive behavior, end-game flow). Treat this as independent of Phase 5's
   hardening pass — Phase 5 is "make it good," this is "verify it's still
   good" right before it's judged.
2. **BGA's project-check tool.** Studio's own static-analysis/lint pass over
   the game project. Run it and fix everything it flags before moving on.
3. **A dry-run code build.** Confirm the project actually builds clean on
   Studio from the current `master` — catches deploy-path issues (missing
   files, broken autoload, stale references) that a local `composer test`
   pass can't see.
4. **BGA's pre-alpha checklist.** BGA's own submission checklist (metadata,
   options, stats, translations, etc.) — best run by Claude Code, since it's
   a local/tooling-heavy pass through the repo and BGA's requirements docs.
   Include the **Zombie Mode level** (Game Metadata Manager → Metadata tab:
   0 passing / 1 random / 2 greedy / 3 smart). While it is unset, "Request
   ALPHA status" is blocked, and it must honestly match what every state's
   `zombie()` does (skills/state-machine.md → "Zombie Handler Is Required").

   **The Game Metadata Manager is Claude's job too, through Chrome.** Draft
   the text in `docs/METADATA.md` first (short and long description, how the
   online version differs from the box, tips, the Metadata tab's fields),
   then drive the Studio form with the Claude in Chrome tools from the
   control panel (`controlpanelgames?game=<name>`) and paste it in. The
   dry-run build and the "Reload ..." buttons on the control panel are
   scriptable the same way; none of this needs a person at the keyboard.
   Only the metadata *images* (box, banner, icon) wait on the art.

Live zombie check, done from the same browser session: keep
`debug_playOneMove()` and a **bounded** `debug_playToEnd(int $cap = 30)` on
`Game`, both over `$this->bga->debug->playUntil()`. Their auto-generated
buttons in the Studio debug panel pump every state's real `zombie()` through
the actual state machine, so a whole zombie game is two clicks. Do not try
to expel a seat by script: BGA refuses a scripted `quitgame`, and the
clock-expiry "Skip turn of players out of time" control warns but never
expels under automation. The `playToEnd` button opens a parameter dialog
(`debugParamDlgApply`); the cap is deliberate, because the framework's zombie
loop-guard trips when too many zombie turns run in one request.

Studio mechanics for the "Special testing" items, all driven from Chrome
(learned on whatsmywordjcb, 2026-10-21):

- **Minified JS/CSS:** the two buttons on the manage page
  (`studiogame?game=<name>`) back up and OVERWRITE the Studio copy with a
  minified one. Test, then click both **Revert** before the next deploy;
  otherwise the deploy's readback diff fails, or worse, a later Revert
  restores a stale backup over a newer deploy.
- **Dark mode:** the toggle next to Sound in the in-game hamburger menu
  sets `html[data-theme="dark"]` and turns the inherited body text light.
  Any game panel with its own light background must pin its text colour
  (`color: #222` on the panel), or letters and numbers vanish. Check it:
  it is the one checklist item that found a real bug.
- **Spectator:** `tableview?table=N&as=<id>` with the "Test spectator"
  seat's id (`#visitor_player_<id>` in the spectator list), then confirm
  from `gameui.gamedatas` that nothing private is present.
- **In-game replay:** a log entry's "Replay from this move" reloads the
  game iframe at `getGameStandardUrl()` + `replayFrom=<move id>`; the
  button is a no-op when every log line maps to the current move, so run
  a few `playOneMove` first. The **archived replay of a finished game**
  (`gamereview?table=N`) may fail on Studio with "Unable to find game
  archive" for every table; that is Studio's archive generation, record it
  as not verifiable and move on.
- **Express start** on `gamepanel?game=<name>` creates and opens a
  2-seat realtime table in one click; `Express STOP` in the in-game menu
  ends a test table (a stale open table silently blocks the next create).

Only after all four pass: submit for private Alpha, then public Alpha once
private-alpha blockers are cleared.

## Release checklist (Phase 6)

- [ ] **Request the BGG ID early — short + long description is all that's
      required to submit.** Do this as soon as those two are drafted, not
      bundled with art/content at the end of the phase; the ID takes time to
      come back, so it should be in flight well before the rest of this
      checklist is ready. Descriptions must follow BGG's format rules (short
      description ≤85 characters, one sentence, no tagline, no emoji, game
      name omitted, evokes the game rather than listing mechanisms).
- [ ] **Once the BGG ID exists, for original (self-published) games only:**
      add the BGA weblink on the BGG entry's weblink page
      (`boardgamegeek.com/browse/weblink/thing/<id>`), upload box/listing art
      via the BGG entry's images page
      (`boardgamegeek.com/boardgame/<id>/images`), then grant BGA a publisher
      license for the game at boardgamearena.com/gamepublishers. Skip this
      for licensed/adapted games (e.g. an adaptation of another publisher's
      game) — the rights holder owns that BGG listing, so coordinate with
      them instead of touching it directly.
- [ ] Final art direction, palette, `player_colors`
- [ ] Meta art for BGA (icon, box, banner) at required sizes
- [ ] Rulebook PDF generated from `docs/RULES.md` (keep `RULES.md` as the
      one source of truth; regenerate the PDF from it, don't hand-maintain
      both)
- [ ] Print-and-play kit, if the game has one
- [ ] Landing page on thebgllc.com (`web/<slug>-landing.html` in this repo)
      plus a card on the site's game grid — **confirm which repo/deploy
      target is actually the live site before pushing anything there.**
      This is not always obvious from inside a single game repo, and a
      stale or wrong reference here (a past plan doc pointed at a repo that
      turned out to be an unrelated project) can send the next session down
      the wrong path. State the assumption explicitly and get it confirmed
      once, rather than propagating a guess.
- [ ] All four pre-alpha submission gates pass (see above): final code +
      UX review, project-check tool, dry-run build, pre-alpha checklist
- [ ] Private Alpha, then Public Alpha. The first production deploy gets a
      `prod-*` tag; from then on every `dbmodel.sql` change needs
      `upgradeTableDb` (see "Deploy discipline")
- [ ] **The day the game reaches public Alpha, link to it from thebgllc.com**
      — a card on the landing page's game grid pointing at the BGA game page
      (`boardgamearena.com/gamepanel?game=<slug>`), in the
      [thebgllc/thebgllc.com](https://github.com/thebgllc/thebgllc.com) repo
      (local checkout `~/dev/thebgllc-site`, deployed with its `deploy.sh
      --live`). For an original game this is the card the landing-page step
      above already created; just switch its copy from "coming soon" to the
      live link. For a **licensed adaptation of another publisher's game**,
      add the card anyway, but as a development example: name the designer
      and publisher, say plainly that we built the BGA adaptation for them,
      and link straight to BGA — no per-game page on our site, since the game
      is theirs. Public Alpha is the moment because that is when the BGA link
      first works for anyone who opts in as an alpha tester; before that it
      is a dead link to outsiders. Remember the site's `.card:nth-child(N)`
      fade-in rule for the new card. First done for Muster: Raise the Banners
      (Play Monkey Games), 2026-10-24.
