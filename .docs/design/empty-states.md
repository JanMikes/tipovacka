# Empty state illustrations

Small illustrations for screens where there's no data yet. They must feel considered and quiet, never sad or apologetic. Generate all four in a single GPT 5.4 session so the set stays visually locked.

## Output requirements

- **Per illustration:** 512 × 512 px (1:1).
- **Background:** transparent PNG (preferred) or pure white.
- **Final delivery:** four separate illustrations, visually locked as one set.

## Session-opening prompt

```
ROLE
You are a senior editorial illustrator producing a four-part empty-state illustration set. All four must share an identical visual language and quiet tone.

GLOBAL STYLE SYSTEM (applies to all four scenes in this session)
- Aspect ratio: 1:1. Canvas: 512 × 512 px. Background: transparent (or #FFFFFF if transparency unavailable).
- Primary color: #081e44 for all structural shapes and outlines.
- Accent color: #149AD5 — used on exactly one small element per scene (a dot, plus, dash, or similar micro-element, never a dominant fill).
- No other hues. No third accent.
- Flat vector, uniform thin strokes, rounded corners.
- Generous negative space — each scene is one small object placed deliberately in the middle of a large quiet canvas.
- Optimistic-neutral tone. Empty is a resting state, not a failure. No frowning faces, no drooping shapes, no broken-cable metaphors.
- No text, no letters, no numbers.

I will send you four scene briefs in sequence. For each, produce one finished 512 × 512 composition that conforms exactly to the GLOBAL STYLE SYSTEM. Maintain identical line weight, object scale, and negative-space ratio across all four. Confirm you understand before I send the first scene.
```

## Scene 1 — Empty leaderboard

```
EMPTY STATE 1 — EMPTY LEADERBOARD

Three abstract vertical bars of equal minimal height sit on a thin horizontal baseline, centered in the frame. The bars are filled navy (#081e44). Above the bars, a single small cyan-blue (#149AD5) filled circle floats playfully, suggesting a marker waiting to be placed. The cyan circle is the scene's only accent. Roughly 60% of the canvas is empty negative space.

Render one finished 512 × 512 composition conforming to the GLOBAL STYLE SYSTEM.
```

Pair in code with:

- Headline: *Tabulka je zatím prázdná*
- Body: *Jakmile první člen zadá tip, objeví se tady žebříček.*
- CTA (group owners only): *Zkopírovat pozvánku*

## Scene 2 — No active tournaments

```
EMPTY STATE 2 — NO ACTIVE TOURNAMENTS

A single floating empty rounded rectangular card centered in the frame. The card is drawn in a navy thin outline with no fill. A thin cyan-blue plus sign sits centered inside the card as the scene's only accent. A very subtle, barely-perceptible soft shadow beneath the card suggests it's floating. No other elements. At least 65% of the canvas is negative space.

Render one finished 512 × 512 composition conforming to the GLOBAL STYLE SYSTEM. Line weight must match Scene 1 exactly.
```

Pair in code with:

- Headline: *Ještě nehraješ v žádném turnaji*
- Body: *Založ si vlastní skupinu nebo se přidej k veřejnému turnaji.*
- Primary CTA: *Vytvořit skupinu*
- Secondary CTA: *Procházet turnaje*

## Scene 3 — No matches scheduled

```
EMPTY STATE 3 — NO MATCHES SCHEDULED

A simple calendar-like rectangular shape rendered in navy thin outline, centered. The top edge of the calendar has a slightly thicker header bar. The calendar body is an empty grid implied by faint thin navy internal lines. A single small cyan-blue (#149AD5) filled dot sits in one of the imaginary date cells — this is the scene's only accent. The grid is quiet; the dot is the entire focal point.

Render one finished 512 × 512 composition conforming to the GLOBAL STYLE SYSTEM. Line weight and object scale must match Scenes 1 and 2.
```

Pair in code with:

- Headline: *Žádné zápasy v plánu*
- Body: *Správce brzy přidá zápasy. Můžeš zatím pozvat další kamarády.*
- CTA (group owners only): *Nahrát zápasy z Excelu*

## Scene 4 — No search results

```
EMPTY STATE 4 — NO SEARCH RESULTS

A simple geometric magnifying glass rendered in navy thin outline, tilted slightly toward the upper-right. Inside the circular lens area, a single cyan-blue (#149AD5) short horizontal dash sits centered, suggesting "nothing found." The dash is the scene's only accent. No exclamation marks, no sad imagery, no broken shapes.

Render one finished 512 × 512 composition conforming to the GLOBAL STYLE SYSTEM. Line weight and object scale must match Scenes 1, 2, and 3.
```

Pair in code with:

- Headline: *Nic jsme nenašli*
- Body: *Zkus upravit vyhledávání nebo projdi celý seznam turnajů.*

## Consistency refinement

After generating all four, send:

```
Lay the four finished illustrations side by side at equal scale. Verify and, if any scene drifts, regenerate only that scene:
  1. Line weight identical across all four.
  2. Object scale roughly consistent — no scene's focal object dramatically larger or smaller than the others.
  3. Each scene contains exactly one small cyan-blue accent. The accent is always a small element (dot, plus, dash) — never a dominant fill.
  4. Negative space ratio roughly consistent across the set (~60–70% empty).
  5. Corner radius consistent on any rounded shape.

Reference: "Match the line weight and scale of Scene [N]."
```

## Do not

- Use sad, drooping, or broken imagery. Empty is neutral, not a failure.
- Add decorative filler to the negative space — restraint earns the charm.
- Include any text, script like "Oops!" or "404", or any fake UI labels.
- Use gradients, textures, drop shadows beyond the one subtle float shadow in Scene 2.
