# OG / social share card

Link-preview image for WhatsApp (dominant in Czechia), Messenger, Facebook, LinkedIn, X, and in-app previews. Background-plate only — wordmark and tagline are composited in code or Figma afterward.

## Output requirements

- **Primary:** 1200 × 630 (1.91:1) — the OpenGraph standard.
- **Square variant:** 1080 × 1080 — for Instagram, WhatsApp status, feed placements.
- **Background:** pure white (`#FFFFFF`) edge-to-edge.
- **Text inside the image:** none. Overlay lives in the compositing step.

## Primary prompt (paste into GPT 5.4)

```
ROLE
You are a senior editorial illustrator producing a link-preview background plate for a premium sports-tech product.

OBJECTIVE
Produce a 1200 × 630 px background image for Tipovačka's OpenGraph card. This image is a background plate — wordmark and tagline will be composited onto it in a separate step. Do not include any text.

CANVAS
1200 × 630 px. 1.91:1. Pure white (#FFFFFF) edge-to-edge.

SPATIAL ZONES (divide the canvas mentally into a 12×6 grid)
- Columns 1–7 (left 55%): overlay safe area. Render only subtle background geometry here — a faint dotted grid in pale cyan at 10% opacity, and one thin diagonal beam sweeping in from the upper-left corner. No focal elements. This area is reserved for the wordmark lockup and tagline.
- Columns 8–12 (right 45%): focal illustration cluster.

FOCAL CLUSTER (right 45%)
Arrange as a loose, gently overlapping composition:
  1. A leaderboard of four ascending rounded vertical bars on a thin navy baseline. Tallest bar filled #149AD5; others filled #081e44.
  2. A small floating score card with two empty circular slots separated by a thin en-dash. Navy outlined, transparent fill.
  3. A trajectory arc sweeping from the lower-right corner upward, terminating in a single glowing #149AD5 dot near the upper-right quadrant.
  4. Three small solid navy avatar circles positioned at different heights on the leaderboard side.

COLOR SYSTEM (strict)
- Background: #FFFFFF
- Primary: #081e44
- Accent (used on exactly: the tallest bar, one avatar token, the trajectory dot): #149AD5
- Pale navy at 10% opacity permitted for the background dotted grid only.
- No other colors.

STYLE
- Flat vector editorial, very lightly dimensional on at most two elements.
- Crisp geometry, uniform thin strokes, rounded corners on cards.
- Premium sports-tech aesthetic. Restrained, not loud.

CONSTRAINTS
- No text. No letters. No numbers. No scores. No team names. No logos.
- Keep the center of the frame (columns 5–8) compositionally light so aggressive platform crops don't destroy the focal cluster.
- No soccer ball, goalposts, or literal pitch.
- No crowd or stadium imagery.
- No casino/betting tropes.

OUTPUT
One final 1200 × 630 composition.
```

## Square variant prompt

```
Using the composition you just produced, regenerate at 1080 × 1080 square. Move the focal illustration cluster to the bottom 55% of the frame. Keep the top 45% as the quiet overlay safe area (only the faint pale-cyan dotted grid). Preserve every color value, stylistic decision, and the element count. Output: one final 1080 × 1080 composition.
```

## Campaign re-versioning

To produce campaign-specific variants (e.g. a tournament-specific share card), keep the base plate unchanged and swap only the HTML/Figma overlay. One background plate supports many campaigns, which is why it's deliberately text-free.

## Overlay composition (post-generation, in Figma or CSS)

Anchor the overlay in the left safe area (columns 1–7):

- **Wordmark lockup:** icon + *Tipovačka* in the top-left of the safe area, ~40px inset.
- **Headline:** *Tipuj zápasy. Poraz kamarády.* — or campaign-specific variant. Color `#081e44`, semibold, vertically centered in the safe area.
- **Domain:** small mono-weight text at bottom-left, `#081e44` at 60% opacity — e.g. `tipovacka.cz`.

## Quality checklist

- Does the center square of the 1200 × 630 (pixels 285–915 horizontal, 0–630 vertical) still crop cleanly on a square platform?
- Is the left 55% actually quiet — no focal elements spilling over?
- Is the cyan accent applied to exactly three elements (tallest bar, one avatar, trajectory dot)?
- Any accidental text, placeholder glyphs, or number forms visible?

If any fail, reply: `REFINE: [issue]. Preserve all other elements.`

## Do not

- Include any text in the image itself.
- Use stock photography or photo textures.
- Show a literal football, whistle, referee, or match uniform.
- Place celebratory crowd imagery — this is a friends-game share, not a national-team broadcast.
