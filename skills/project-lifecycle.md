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
   a local/tooling-heavy pass through the repo and BGA's requirements docs,
   not something that needs a person driving Studio's UI.

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
- [ ] Private Alpha, then Public Alpha
