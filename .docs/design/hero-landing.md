# Hero — landing page

Above-the-fold visual on `/`. Composition must reserve the left half of the frame for HTML-overlaid headline + CTA so the image never fights the copy.

## Output requirements

- **Desktop:** 1920 × 1080 (16:9).
- **Portrait mobile variant:** 1200 × 1500 (4:5), same visual DNA.
- **Background:** pure white (`#FFFFFF`), edge-to-edge.

## Primary prompt (paste into GPT 5.4)

```
ROLE
You are a senior editorial illustrator producing marketing hero imagery for a premium sports-tech product.

OBJECTIVE
Produce a single finished hero illustration for the homepage of Tipovačka, a Czech social football-prediction web app. The illustration sits behind HTML headline and CTA copy, so the left half of the frame must remain visually quiet.

CANVAS
1920 × 1080 px. 16:9. Pure white background (#FFFFFF), edge-to-edge.

SPATIAL GRID (divide the canvas mentally into a 10×6 grid)
- Columns 1–5 (left half): quiet overlay zone for future HTML text. Fill only with subtle background geometry — a 10% opacity dotted cyan grid, plus one thin diagonal beam sweeping in from the upper-left corner at ~15° angle. Nothing else in this half.
- Columns 6–10 (right half): focal illustration cluster.

FOCAL CLUSTER (right half)
Arrange these elements as a loose, floating, isometric-feeling group, layered with gentle overlaps:
  1. A translucent dashboard panel (rounded rectangle, navy outline, ~5% cyan fill) showing three abbreviated fixture rows. Do not render real text — use thin abstract horizontal placeholder lines.
  2. A stylized leaderboard: four ascending rounded vertical bars. The tallest bar is filled #149AD5; the others are filled #081e44. Bars sit on a thin navy baseline.
  3. A floating score-prediction card: two empty circular slots separated by an en-dash, all in navy outline.
  4. A trajectory arc sweeping diagonally from the lower-right quadrant up toward the upper-right corner, terminating in a small glowing #149AD5 dot.
  5. Three small avatar tokens (solid navy circles) positioned at different vertical heights on the leaderboard side, suggesting ranked participants.

COLOR SYSTEM (strict)
- Background: #FFFFFF
- Primary (shapes, outlines, silhouettes): #081e44
- Accent (tallest bar, trajectory dot, one highlight token): #149AD5
- Pale navy (@10% opacity) for background geometry only.
- No other hues.

STYLE
- Flat vector editorial illustration with very light volumetric shading on one or two elements for depth.
- Crisp geometry. Uniform thin strokes. Rounded corners on UI cards.
- Aesthetic reference: Linear.app / Raycast illustration language meets Premier League broadcast restraint.
- No sketchy hand-drawn texture, no painterly strokes, no 3D rendering.

MOOD
Confident, socially energetic, sophisticated. Friends competing for fun, not a gambling house.

CONSTRAINTS
- No letters, numbers, team names, or brand marks anywhere in the image.
- No literal soccer ball, goalposts, grass, crowd, stadium.
- No neon glow excess. The cyan accent is a brand color, not RGB gamer lighting.
- No casino/betting imagery.

OUTPUT
One final composition. Not a grid. Not variants.
```

## Portrait variant prompt

```
Using the same illustration you just produced, recompose it for a 1200 × 1500 portrait canvas (4:5 aspect). Restructure so the focal cluster sits in the lower 60% and the upper 40% becomes the quiet overlay zone for HTML headline. Preserve every element, color value (#FFFFFF background, #081e44 primary, #149AD5 accent), and stylistic decision. Shift nothing about the element design — only the layout.
```

## Alternative concept — pitch-to-chart

Use this only if the dashboard concept feels too literal or corporate:

```
Same ROLE, CANVAS, COLOR SYSTEM, STYLE, and CONSTRAINTS as the primary hero prompt.

OBJECTIVE
A stylized bird's-eye abstraction of a football pitch rendered in #081e44 thin lines on white, which dissolves at its right edge into a grid of #149AD5 thin lines that morph upward into a rising bar-chart leaderboard. Three solid navy avatar tokens float at different heights above the pitch-to-chart transition.

SPATIAL GRID
- Left half (columns 1–5): quiet overlay zone — only a very faint pale navy dotted grid at 10% opacity.
- Right half (columns 6–10): the pitch-to-chart transition and avatar tokens.

OUTPUT
One final composition.
```

## Overlay copy (for page layout, not the image)

The HTML overlay sitting on top of columns 1–5 will read:

- Headline (H1, `#081e44`, semibold, ~64px desktop): *Tipuj zápasy. Poraz kamarády.*
- Subheadline (`#081e44` at 70% opacity, regular, ~20px): *Založ skupinu, zvi přátele a bojujte o prvenství v tabulce.*
- Primary CTA (filled `#149AD5`, white text): *Vytvořit skupinu*
- Secondary CTA (outline `#081e44`, transparent bg): *Procházet turnaje*

## Quality checklist

- Left half of the frame genuinely quiet — no focal elements leaking across column 5?
- Cyan accent used on no more than three elements total?
- Does the composition still read at 1440 × 810 (common laptop width)?
- No accidental text, fake icons resembling letters, or visible brand glyphs?

If any fail, reply: `REFINE: [issue]. Preserve all other elements.`
