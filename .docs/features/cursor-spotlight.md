# Cursor Spotlight

> **Currently OFF.** `ENABLED = false` in `assets/spotlight.js` — cards no longer react
> to the mouse at all (no glow, no lit/pulsing border). Flip that one constant back to
> `true` to restore everything described below; nothing else needs changing.

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

| Constant           | Current | Notes                                                                                                                                  |
|--------------------|---------|----------------------------------------------------------------------------------------------------------------------------------------|
| `ENABLED`          | `false` | Master switch. `true` → JS adds `.spotlight-on` to `<html>`, which every CSS layer below is gated on. `false` → effect fully off.       |
| `PROXIMITY`        | `true`  | `false` → hover-only (glow + ring only on the hovered card).                                                                            |
| `PROXIMITY_RADIUS` | `300`   | px from a card's edge where its border starts to light up.                                                                              |

The CSS is inert on its own: without `.spotlight-on` on `<html>` neither pseudo-element
layer paints, so disabling in JS leaves no half-effect (a static centered glow on hover)
behind. `position: relative` on the card primitives stays **ungated** — cards must remain
the containing block for their absolutely-positioned children either way.

## Gotchas

- `.card-glass`'s decorative corner wash lives in its `background-image` (NOT `::before`)
  precisely so both pseudo-elements stay free for the spotlight layers. Don't move it back.
- Don't put the ring at `inset: -1px` (over the actual border): `overflow: hidden`
  clips it to nothing. This failure mode is invisible — computed styles look correct.
- `.tip-card.accent` / `.surface-accent` are bright gradient surfaces and stay excluded.
