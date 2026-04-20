# Winner celebration — tournament-end hero

Final-results illustration. Appears on the tournament results screen and on the "share my win" screenshot surface. Must look premium — it's the image that ends up in people's group chats.

## Output requirements

- **Widescreen:** 1600 × 900 (16:9).
- **Square variant:** 1080 × 1080 — for mobile portrait and share-card usage.
- **Background:** pure white (`#FFFFFF`) edge-to-edge.

## Primary prompt (paste into GPT 5.4)

```
ROLE
You are a senior editorial illustrator producing a premium final-moment illustration for a sports-tech product.

OBJECTIVE
Produce a single finished celebration illustration for the tournament-winner screen of Tipovačka. Tasteful, confident, share-worthy.

CANVAS
1600 × 900 px. 16:9. Pure white background (#FFFFFF) edge-to-edge.

COMPOSITION
Centered podium arrangement:
  1. Three ascending rounded vertical bars forming a podium. Center bar is tallest and filled #149AD5. Flanking bars are shorter and filled #081e44. Thin navy baseline beneath.
  2. Atop the winning (center) cyan bar: a single abstract circular avatar token in navy, slightly elevated above the bar as if lifted in triumph.
  3. Atop each flanking navy bar: a smaller abstract circular avatar token in navy.
  4. Above the winning avatar, a minimal geometric trophy silhouette floats — a simple stylized cup shape in navy stroke with a single #149AD5 accent highlight (a dot, rim edge, or handle inner). Not ornate, not gilded.
  5. Background: a very subtle radial glow behind the winning bar in #149AD5 at 8–10% opacity, softening smoothly into white at the frame edges. No other background elements.

CONFETTI TREATMENT
Scatter 20–30 small flat geometric shards across the upper two-thirds of the frame — triangles, thin rectangles, narrow diamonds. Mix navy and cyan pieces in roughly equal proportion. Distribute asymmetrically to emphasize the winner without cluttering the composition. Foreground pieces larger, background pieces smaller, to suggest subtle depth.

COLOR SYSTEM (strict)
- Background: #FFFFFF
- Primary: #081e44
- Accent: #149AD5
- Pale cyan (#149AD5 at 8–10%) for the radial glow behind the winning bar only.
- No gold, no yellow, no red, no green. The trophy is navy-and-cyan, not metallic.

STYLE
- Flat vector editorial, very lightly dimensional on the trophy and podium bars.
- Crisp geometry. Uniform thin strokes. Rounded bar tops.
- Premium sports-tech aesthetic.

MOOD
Triumphant but composed. Gracious winner. Share-worthy screenshot.

CONSTRAINTS
- No photorealistic trophy, gold medals, champagne, or cigars.
- No crowd, stadium, stands, spotlights, stage lighting.
- No gold/yellow color — stay strictly within the brand palette.
- No national flags or country symbols.
- No text, numbers, team names, or brand marks.
- Confetti is tasteful, not a Super Bowl halftime explosion.

OUTPUT
One polished final composition at 1600 × 900.
```

## Square variant prompt

```
Using the composition you just produced, recompose to 1080 × 1080 square. Keep the podium, trophy, and confetti distribution intact but tighten the framing so the whole scene fits comfortably with ~10% padding. Preserve every color value (#FFFFFF background, #081e44 primary, #149AD5 accent), the radial glow, and the confetti count. Flanking avatars must remain visible. Output: one finished 1080 × 1080 composition.
```

## Variant — personal winner share

For a "you won!" share aimed at a single user (square format for sharing to group chats):

```
ROLE
Same as primary prompt.

OBJECTIVE
Produce a 1080 × 1080 individual-winner celebration illustration centered on one winning avatar.

CANVAS
1080 × 1080 px. Pure white background.

COMPOSITION
  1. A single tall cyan-blue (#149AD5) rounded vertical bar, centered.
  2. Atop the bar: one abstract navy circular avatar token, slightly elevated.
  3. Above the avatar: a minimal geometric trophy silhouette — simple stylized cup, navy stroke, single #149AD5 accent highlight.
  4. Behind the full composition: a soft radial glow in #149AD5 at 8–10% opacity, fading to white at frame edges.
  5. Confetti: 15–20 small flat geometric shards (triangles, thin rectangles, diamonds) in navy and cyan, scattered restrainedly across the upper two-thirds.

COLOR SYSTEM, STYLE, CONSTRAINTS, MOOD
Identical to the primary prompt. No gold, no crowd, no stadium, no text, no flags.

OUTPUT
One finished 1080 × 1080 composition.
```

## Overlay copy (for page layout)

When composing the results page:

- **Headline:** *Gratulujeme, {nickname}!* (or *Vítěz!* if laying out pre-personalization)
- **Subheadline:** *{nickname} vyhrává turnaj {tournament_name} s {points} body.*
- **Share CTA:** *Sdílet výsledek*
- **Secondary CTA:** *Zobrazit celou tabulku*

Render the nickname in `#149AD5`; the rest of the text in `#081e44`. Position overlay text above or below the illustration — never overlap the illustration itself, so the image stays screenshot-clean.

## Quality checklist

- Is the confetti count restrained (20–30 on widescreen, 15–20 on square)?
- Is the trophy navy-and-cyan, with zero gold/yellow pixels?
- Does the radial glow stay subtle enough not to overpower the podium?
- Are both flanking avatars still present and at visibly lower heights than the winner?
- No text, numbers, or flags visible?

If any fail, reply: `REFINE: [issue]. Preserve all other elements.`

## Do not

- Use realistic trophy, gold medal, champagne, or cigar imagery.
- Render crowds, stands, roaring fans, stadium structures, stage lighting.
- Introduce gold, yellow, red, or green — the palette is strictly navy + cyan + white.
- Include country flags or national symbols.
- Include text, numbers, or team names inside the image.
