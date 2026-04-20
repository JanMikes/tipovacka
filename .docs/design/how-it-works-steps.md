# How it works — 3-step illustration set

Three square illustrations for the "Jak to funguje" section on `/`. They must read as a **set** — same line weight, same figure proportions, same rhythm of accent color, same amount of negative space. Generate them in a single GPT 5.4 session so the model carries visual consistency across calls.

## Output requirements

- **Per illustration:** 800 × 800 px (1:1).
- **Background:** pure white (`#FFFFFF`) or transparent PNG.
- **Final delivery:** three separate files, visually locked as one set.

## Session-opening prompt (paste first, before any step prompts)

```
ROLE
You are a senior editorial illustrator producing a three-part illustration set. All three must share an identical visual language.

GLOBAL STYLE SYSTEM (applies to all three scenes in this session)
- Aspect ratio: 1:1. Canvas: 800 × 800 px. Background: #FFFFFF (or transparent).
- Primary color: #081e44 for all major shapes, silhouettes, and outlines.
- Accent color: #149AD5 — used on exactly one focal element per scene.
- No other hues. No third accent.
- Flat vector, uniform thin strokes, rounded corners, minimal volumetric shading.
- Human figures depicted as abstract rounded silhouettes: circle heads, soft shoulders, no facial features, no hair, no detailed clothing.
- One clear focal metaphor per scene, surrounded by generous negative space.
- No text, no letters, no numbers, no team names, no scores anywhere.
- No literal soccer ball, goalposts, pitch, grass, whistle, or referee.
- No casino or gambling iconography.

I will send you three scene briefs in sequence (Step 1, Step 2, Step 3). For each, produce one finished 800 × 800 composition that conforms exactly to the GLOBAL STYLE SYSTEM above. Maintain identical line weight, figure proportions, and negative-space rhythm across all three. Confirm you understand before I send Step 1.
```

Once GPT 5.4 acknowledges, send each step prompt in the same session:

## Step 1 — Create a group

```
STEP 1 SCENE BRIEF
Three stylized abstract human figures (navy, rounded silhouettes, no faces) stand in a loose semicircle in the lower portion of the frame. Above them, a floating rounded rectangular card represents the newly formed group. On the card, a row of four small cyan-blue (#149AD5) circular dots represents a PIN placeholder — this is the single accent element of the scene. The figures and card outlines are navy; the dots are the only cyan.

Render one finished 800 × 800 composition conforming to the GLOBAL STYLE SYSTEM.
```

## Step 2 — Predict the matches

```
STEP 2 SCENE BRIEF
A centered floating match-prediction card viewed head-on. The card shows two opposing abstract team shields (simple rounded geometric shapes, no logos, no letters) flanking a score-entry area — two empty circular slots separated by a thin en-dash. A single abstract navy hand/finger silhouette reaches in from the lower edge and taps one of the score slots; that slot glows in cyan-blue (#149AD5) as the single accent. Two or three tiny navy arrow or checkmark marks orbit the card lightly.

Render one finished 800 × 800 composition conforming to the GLOBAL STYLE SYSTEM. Line weight and figure proportions must match Step 1 exactly.
```

## Step 3 — Climb the leaderboard

```
STEP 3 SCENE BRIEF
Three or four ascending rounded vertical bars form a stylized podium on a thin navy baseline. On top of each bar, a small abstract circular avatar token sits at a different height. The tallest bar and its avatar are cyan-blue (#149AD5); the rest are navy — this is the scene's single accent moment. Tiny celebratory geometric shards (triangles, thin rectangles, narrow diamonds, ~8 pieces in navy) float restrainedly above the winning bar.

Render one finished 800 × 800 composition conforming to the GLOBAL STYLE SYSTEM. Line weight, figure proportions (if figures were used), and negative-space rhythm must match Steps 1 and 2 exactly.
```

## Consistency refinement

After generating all three, send this:

```
Lay the three finished illustrations side by side at equal scale. Verify and, if needed, regenerate any that drift:
  1. Line weight must be identical across all three scenes.
  2. Each scene must contain exactly one cyan-blue accent element.
  3. Negative space around the focal cluster must feel balanced across scenes — none crowded, none starved.
  4. Any human figure must share the same proportions across scenes (head-to-body ratio, shoulder width).
  5. Any card, bar, or UI shape must share the same corner radius across scenes.

If any scene breaks consistency, regenerate only that scene with explicit reference to the other two: "Match the line weight and proportions of Step [N] and Step [M]."
```

## Page layout (post-generation)

In the landing page, lay out in a 3-column grid (collapsing to single column under 768px). Pair each illustration with Czech copy below:

1. *Vytvoř skupinu* — *Založ soukromou skupinu a pozvi kamarády přes odkaz nebo PIN.*
2. *Tipuj zápasy* — *Zadej svůj tip na každý zápas až do výkopu.*
3. *Získej body* — *Správné tipy ti nasbírají body a vyženou tě na špici tabulky.*

Use `#081e44` for the step headline and `#081e44` at 70% opacity for the description. Center-align under the illustration.

## Do not

- Show literal footballs, pitches, goalposts, or match officials.
- Render facial features, hair, or detailed clothing on figures.
- Use more than the two brand colors per scene.
- Render numbers, letters, team names, or scores.
- Add a third accent color for variety — the cyan restraint is the point.
