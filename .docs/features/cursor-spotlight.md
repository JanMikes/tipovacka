# Cursor Spotlight

Cards react to the mouse: a soft accent-blue glow inside the hovered card follows the
cursor, and a 1px segment of the card's border lights up under it. With proximity mode
on (the default), borders of cards *near* the cursor glow before hover, scaled by
distance — the Hyperplexed/Linear-style effect.

Two parts, no per-card wiring:

- `assets/spotlight.js` — one delegated, rAF-throttled `pointermove` listener writes the
  pointer position into `--mx`/`--my` (px, relative to each card) and the distance-based
  `--spot-o` (0..1). Recomputes on scroll. Desktop only: everything is gated behind
  `(hover: hover) and (pointer: fine)`.
- `assets/styles/app.css`, section "Cursor spotlight" — the inner glow is the card's
  `::after` (`:hover`-gated), the border light is `::before`: a radial gradient cut to a
  1px ring via `mask-composite: exclude`. The ring sits at `inset: 0` (inside the border)
  because many cards clip children to the padding box with `overflow: hidden`.

## Covered elements

`.card`, `.card-glass`, `.tip-card` (except `.accent`), `.tip-row`, `.stat`,
`.option-card`, `.variant-card` — automatically, site-wide.

Any other element can opt in by adding the `spotlight` class (it must have a visible
border for the ring to read well):

```twig
<div class="spotlight rounded-2xl border border-white/10 bg-white/5 p-6">…</div>
```

Keep the CSS selector lists and `SELECTOR` in `spotlight.js` in sync when adding a new
primitive.

## Toggles

In `assets/spotlight.js`:

| Constant           | Default | Notes                                                          |
|--------------------|---------|----------------------------------------------------------------|
| `PROXIMITY`        | `true`  | `false` → hover-only (glow + ring only on the hovered card).   |
| `PROXIMITY_RADIUS` | `240`   | px from a card's edge where its border starts to light up.     |

## Gotchas

- `.card-glass`'s decorative corner wash lives in its `background-image` (NOT `::before`)
  precisely so both pseudo-elements stay free for the spotlight layers. Don't move it back.
- Don't put the ring at `inset: -1px` (over the actual border): `overflow: hidden`
  clips it to nothing. This failure mode is invisible — computed styles look correct.
- `.tip-card.accent` / `.surface-accent` are bright gradient surfaces and stay excluded.
