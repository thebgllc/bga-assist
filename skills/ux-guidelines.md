# BGA UX/UI guidelines (official, v1.0.0, Oct 2025)

Source: BGA's "Game Dev Guidelines — UX Use Case for Games on Board Game Arena" (Canva PDF,
found at https://pdfhost.io/v/AuyAj7fGJz_BGA_-_Guidelines_UX_UI). Also live on the wiki as
https://en.doc.boardgamearena.com/BGA_Studio_Guidelines (reviewers cite its section anchors). Full copy:
`skills/reference/bga-ux-ui-guidelines-v1.0.0.pdf` (+ `.txt`). BGA uses it to judge
ALPHA → BETA → RELEASE. Racetothebottom was declined twice on layout: audit against this
before every public-alpha request.

## Their pre-release checklist (verbatim)
1. Layout centers and scales on all devices.
2. Action bar ≤ 4 buttons, never replaces components.
3. Tooltips: clear, translatable, useful.
4. Buttons follow BGA standards (blue = forward, red = cancel).
5. No blocking popups mid-game.
6. Animations: quick, smooth, informative.
7. All tap/click targets ≥ 32px.
8. Colorblind-safe icons in place.
9. Endgame shows breakdown + replay option.
10. Game state restores cleanly after refresh.

## Rules worth knowing beyond the checklist
- **Layout**: aim for a square main area with everything the active player needs; secondary
  stuff (other players, extra boards) goes below via vertical scroll. Centered, with a
  "no action" margin around it. Mantra: centre = shared actions, top/bottom = global info,
  panels = private resources.
- **Score / whose turn / objectives** must be easy to open (button, tab, in-game panel), but
  NOT on the player panels and need not be always visible.
- **Player panels**: 4 players on mobile must take ≤ 1/4 of the screen. No settings, titles
  or round info there; anchors to other areas are fine.
- **No in-game branding/logos.**
- **Action bar**: play on the board first; the bar is for pass/end turn/confirm, player
  targets, auctions, small resource exchanges. Main forward action centred; disabled ones
  next; cancel/undo on the right, not touching BGA's own buttons. Round-in-progress info goes
  on the table (e.g. just under the action bar), not in the bar.
- **Button colours are mandatory**: `addActionButton` primary = blue (forward),
  secondary = white (optional), alert = red (cancel/undo). Grey = disabled.
- **Disabled buttons** only when the action is relevant now; otherwise hide it.
- **Undo**: discouraged. Prefer a confirm (even a timed one, 5–8 s) for irreversible moves,
  or a single "Restart turn" when a turn has > 2 actions. Never Undo layered on Reset.
- **Popups**: no automatic popups (tutorials only). Between-round/scoring popups must be
  skippable by any click or a timer. Put helper text in the game wiki instead.
- **Zoom buttons**: avoid. If unavoidable: +, −, and a home/reset; the default view must be
  fully playable.
- **Tooltips**: must add info, not repeat the same image at the same size. On mobile they
  open on tap and must close on tap-away.
- **Fonts**: default BGA font unless there's a strong reason; no words baked into images;
  support RTL and diacritics; WCAG AA contrast (4.5:1).
- **Player names** in player colour, with an outline so yellow-on-white still reads; test
  light and dark backgrounds.
- **Sizes**: interactive elements ≥ 32×32 px, ideally 40–44 px; scroll instead of shrinking.
- **Anchors/jump links** for long vertical layouts (example: thoun's bga-jump-to,
  https://thoun.github.io/bga-jump-to/demo/index.html).
- **Background**: thematic, slightly textured/blurred "play mat"; no flat colours; never
  reduces component contrast.
- **Invalid actions** always get a visible reason (shake/grey-out + short message); never
  colour-only.
- **Logs**: every major action, who did what ("X drew 2 cards"), automatic actions too,
  small icons with alt text, group tiny simultaneous actions into one line.
- **Animations**: ~0.5 s typical, 0.8 s max; batch repeated small gains; skippable and
  replay-safe. Use BgaAnimations.
- **Sound**: quieter than BGA default, short, never the only cue, no looping music.
- **Colourblind**: every colour-coded component also has a shape/icon/pattern.
- **Settings** only in BGA's standard settings menu (`this.bga.userPreferences`), never a
  custom menu.
- **Endgame**: scoring revealed step by step with synchronous notifications +
  `displayScoring` over the scoring element; length of the payoff scales with game length.
- **Refresh**: everything, including highlights and selections, comes back from the server.
  No raw exceptions shown to players.

## Lessons from our own reviews (Entropy, Racetothebottom, Byline — 2026-11)
What BGA reviewers actually flagged, and the fixes that got the games through. Check every one
before a public-alpha request.

- **Cap the width on wide screens and centre it** (Entropy declined 2026-11-13, "no wide-screen
  cap"). Give the game root a `max-width` equal to the main row's natural width (Entropy 1412 px,
  Byline 1672 px) with `margin: 0 auto`. Measure at a 2560 px window: every band (offers, player
  boards, tableaux) must stop where the board stops. A full-width band below a capped board is
  the "detached band" a reviewer named.
- **"Mobile = scaled desktop" is a finding.** James keeps `game_interface_width.min` at 740 (never
  suggest lowering it), so BGA scales the layout by ~0.53 on a 390 px phone. Design touch targets
  at **≥ 61 px at design size** to land at 32 px, and give touch/narrow screens their own
  arrangement (one column, the hand inside empty board space, big tiles for tiny printed spaces).
  Detect with `matchMedia('(pointer: coarse)')`, `body.mobile_version` or `innerWidth < 980`.
- **Layouts that build different DOMs must follow a resize.** Deciding once in `setup()` leaves a
  desktop window dragged narrow on the wrong layout. Listen to `resize`, debounce ~500 ms, and
  reload when the choice flips (`getAllDatas` restores the state). Don't offer a layout or
  art-crop preference to paper over a design call: choose automatically (Byline dropped both).
- **No overlays for help.** A first-game primer or a rules reference opened as a full-screen
  modal counts as a popup. Open it inline above the board (dismissible, "don't show again" in a
  real user preference), and put the long-form rules and icon key on the `Gamehelp<game>` wiki
  page (Entropy removed its in-game panels for that).
- **Say why an action does nothing.** When a chosen action cannot start (can't pay, nothing left
  to take, maxed out), warn at selection time ("can't pay" tag with Move anyway / Cancel) and log
  a `fizzled` line when it resolves to nothing. Players filed "bugs" for silent fizzles.
- **Painted-art hit regions cover the whole visual group.** When a header icon, its effect icons
  and a column of spaces are printed in the art, one invisible zone should run the full column
  (carrying the help tooltip), placed first in the DOM so the real spaces sit on top with their
  own tooltips. A zone on the header alone makes hover flicker on and off down the column.
- **Contrast**: player-name outlines and dark ink on dark backgrounds fail 3:1 easily; test
  light and dark mode (`html.dark`).
- **Measure, don't eyeball.** Use the headless preview harness (static UI preview: stub the
  framework, render the real DOM + CSS) at 2560, 1536 and 900 px windows and read
  `getBoundingClientRect()` of the bands and targets; screenshots alone missed every item above.
