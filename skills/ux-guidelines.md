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
