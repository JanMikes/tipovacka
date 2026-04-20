# Logo — icon mark

Icon mark generation only. The wordmark *Tipovačka* is typeset separately in a vector tool — GPT 5.4 handles Latin text well but still occasionally drops Czech diacritics (`č`) at small sizes, so the safe path is icon-only and wordmark-in-Figma.

## Output requirements

- **Canvas:** 1024 × 1024 px, aspect ratio 1:1.
- **Background:** pure white (`#FFFFFF`) or transparent.
- **Mark occupies:** inner 70% of canvas, centered, with 15% padding on all sides.

## Primary prompt (paste into GPT 5.4)

```
ROLE
You are a senior vector brand designer. You produce a single finished logo mark, not sketches or mood boards.

OBJECTIVE
Design one minimalist, geometric logo icon for Tipovačka, a Czech social football-prediction web app. The icon is the standalone symbol that lives beside the wordmark, which is typeset separately.

CONCEPT
Choose the strongest of these three directions. Do not blend them.
  (A) Trajectory-checkmark: an upward-angled arrow whose tip resolves into a subtle checkmark, suggesting a correct prediction.
  (B) Chevron matchup: two mirrored chevron shapes facing each other with a small geometric mark between them, suggesting head-to-head competition.
  (C) Leaderboard bars: three ascending rounded vertical bars of varying heights, suggesting a podium or ranking.

COMPOSITION
- 1024 × 1024 canvas, 1:1 aspect ratio.
- Mark centered, occupying the inner 70% with 15% padding.
- Single focal symbol. No supporting graphics, no frame, no background objects.

COLOR SYSTEM (use exactly these values)
- Background: #FFFFFF
- Primary fill or stroke: #081e44
- Accent (applied to exactly one element of the mark): #149AD5
- No other colors.

STYLE
- Flat vector. Uniform stroke weights. Clean 45° and 90° geometry.
- No gradients, no drop shadows, no textures, no 3D rendering, no outline strokes inside fills.
- Reads cleanly at 16px favicon size.
- Aesthetic reference: modern European sports-tech brand identity (Linear.app precision meets Premier League broadcast poise).

CONSTRAINTS
- No letters, no numbers, no wordmark, no text of any kind inside the mark.
- No literal footballs, goalposts, pitches, grass, whistles, referees.
- No casino or gambling imagery.
- No national flags.

OUTPUT
Render four distinct variations of the chosen concept on a single sheet, arranged in a 2×2 grid, each variation ~480 × 480 with ~20px gutters. White sheet background. Label nothing.
```

## Continuation prompt (after picking a favorite)

Once you've picked concept variant `N`, continue the session with:

```
Take variant N from the previous grid. Render it as a single, final, production-ready mark on a 1024 × 1024 canvas, centered with 15% padding, on a pure white background. Refine its geometry for pixel-perfect alignment. Preserve the exact color system (#081e44 primary, #149AD5 accent, #FFFFFF background). Output: one isolated, hero-quality icon.
```

## Variant prompts (alternative concept fallbacks)

If the primary run doesn't yield a strong result, use one of these single-concept runs:

**Trajectory-checkmark:**

```
Logo icon: an upward-angled arrow where the tip resolves into a small checkmark motif. #081e44 arrow body, #149AD5 accent on the checkmark tip only. Flat vector, uniform stroke weight, 1024×1024 canvas, white background, centered with 15% padding, no text, single mark.
```

**Chevron matchup:**

```
Logo icon: two mirrored chevron shapes facing each other with a small geometric diamond centered between them. #081e44 chevrons, #149AD5 center diamond. Flat vector, uniform stroke weight, 1024×1024 canvas, white background, centered with 15% padding, no text, single mark.
```

**Leaderboard bars:**

```
Logo icon: three ascending rounded-top vertical bars. Middle bar is tallest and filled #149AD5; flanking bars are shorter and filled #081e44. Uniform spacing. Flat vector, 1024×1024 canvas, white background, centered with 15% padding, no text, single mark.
```

## Wordmark typesetting

Typeset *Tipovačka* yourself in Figma/Illustrator. Suggested typefaces, in priority order: **Inter**, **Space Grotesk**, **General Sans**, **Manrope**. Weight 500–600. Color `#081e44`. Letter-spacing slightly tight (-0.5% to -1%). Place the icon to the left of the wordmark with optical spacing ≈ the height of a lowercase *o*.

Export three lockups:

1. Horizontal lockup (icon + wordmark, primary).
2. Stacked lockup (icon above, wordmark below, centered).
3. Icon-only (favicon, avatar, app icon).

## Quality checklist (apply after generation)

- Does the mark survive being scaled to 16×16 without visual collapse?
- Is the cyan accent applied to exactly one element?
- Is the silhouette recognizable when filled solid navy (single-color version test)?
- No gradients, no drop shadows, no textures present?

If any answer is no, reply to GPT 5.4 with: `REFINE: [specific issue]. Keep everything else unchanged.`
