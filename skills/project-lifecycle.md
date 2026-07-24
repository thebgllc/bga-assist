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

## Deploy discipline

**`docs/`, `tools/`, and `tests/` never deploy to BGA Studio.** They're
dev-only. Keep this true by convention — don't reference them from anything
Studio actually loads — rather than by SFTP-exclude tooling; an `sftp.json`
exclude list is unnecessary overhead unless a specific deploy path actually
needs it.

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
- **Phase 6 — Art, content & release.** Final art/palette/`player_colors`,
  rulebook PDF generated from `docs/RULES.md`, PnP kit if applicable,
  landing page on thebgllc.com, BGG entry (description/images/credits),
  then the release gates: a full Studio pre-submission testing pass,
  private Alpha, public Alpha.

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

## Release checklist (Phase 6)

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
- [ ] BGG entry: short + long description, metadata (weight/luck/strategy/
      player count), images, credits
- [ ] Full Studio pre-submission pass: all player counts, reconnect/zombie/
      spectator, privacy model under real (non-simulated) clients
- [ ] Private Alpha, then Public Alpha
