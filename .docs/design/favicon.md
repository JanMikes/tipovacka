# Favicon + app icons

Derived from the logo icon mark — this file does **not** require fresh GPT 5.4 generation. Once you've finalized the logo icon in `logo.md`, use one of the two workflows below to produce every favicon and app-icon size.

## Required outputs

| Asset | Size | Format | Purpose |
|---|---|---|---|
| `favicon.ico` | 16×16 + 32×32 + 48×48 multi-res | ICO | Legacy browser tab icon |
| `favicon.svg` | vector | SVG | Modern browser tab icon (responsive) |
| `apple-touch-icon.png` | 180×180 | PNG | iOS home-screen icon |
| `icon-192.png` | 192×192 | PNG | PWA manifest / Android home screen |
| `icon-512.png` | 512×512 | PNG | PWA manifest / splash / maskable |
| `icon-maskable-512.png` | 512×512 | PNG with ~10% safe-area padding | Android adaptive icon |
| `safari-pinned-tab.svg` | vector, single-color | SVG | Safari pinned tab (monochrome) |
| `manifest.webmanifest` | — | JSON | PWA manifest referencing the above |

## Workflow A — realfavicongenerator.net (recommended)

Fastest path. Produces every size + the manifest + browser-specific config in one pass.

1. Export the **icon-only** variant of the logo at **1024 × 1024 PNG** from Figma. Use the color-on-white version (navy + cyan on `#FFFFFF`), not a transparent version — some platforms expect a background.
2. Upload to [realfavicongenerator.net](https://realfavicongenerator.net).
3. Configure:
   - **iOS:** use the PNG as-is. Background color `#FFFFFF`.
   - **Android / PWA:** background color `#FFFFFF`, theme color `#081e44`, name *Tipovačka*, short name *Tipovačka*, display *standalone*.
   - **Windows tile:** background color `#081e44` (navy). Render the icon in white if the tool offers a monochrome inversion; otherwise skip.
   - **Safari pinned tab:** color `#081e44`. Upload a simplified single-color SVG (see workflow B step 3 if you need to produce one).
   - **Favicon for classic browsers:** generate from the same source, default settings.
4. Download the generated bundle. Place every file at the site's web root (see the routing details in `claude-code-usage.md`).

## Workflow B — manual Figma export (for finer control)

Use this if the auto-simplification in Workflow A damages the mark at 16px, which is common for intricate icons.

1. **Full-detail variant** — export the finished logo icon at 1024 × 1024 (PNG + SVG). Keep the exact color system (`#081e44` primary, `#149AD5` accent, `#FFFFFF` background).
2. **Simplified 16px variant** — duplicate the icon, then simplify: thicker strokes, merged details, remove any element under ~2px at 16px target size. Verify it reads as a distinct silhouette at 16 × 16. Export at 16, 32, 48 PNG.
3. **Single-color SVG variant** — duplicate the icon and flatten to a single solid fill in `#081e44` (no cyan accent). Export as SVG. This is what Safari's pinned-tab uses.
4. **Maskable variant** — duplicate the 1024 × 1024 source, place it inside a circle with ~10% safe-area padding on all sides so Android's rounded-mask crop doesn't clip the icon. Export at 512 × 512 PNG with a solid `#FFFFFF` background.
5. Assemble the `.ico` multi-resolution file using ImageMagick:
   ```
   magick favicon-16.png favicon-32.png favicon-48.png favicon.ico
   ```
6. Write `manifest.webmanifest` by hand (Claude Code will also do this — see `claude-code-usage.md`).

## Optional — GPT 5.4 fallback for a 16px-ready mark

If the main logo icon collapses at 16px and you need a simpler, favicon-only icon that still reads as *Tipovačka*, generate a one-off:

```
ROLE
You are a senior vector brand designer producing a favicon-scale logo mark.

OBJECTIVE
Produce a single, extremely simple logo mark readable at 16 × 16 px for the app Tipovačka. The mark must survive aggressive down-scaling.

CANVAS
1024 × 1024 px. 1:1. Pure white (#FFFFFF) background. Mark centered with 20% padding.

STYLE
- One single geometric form. No composite of multiple elements.
- Solid fill, not stroke. The form must read as one shape, not a cluster.
- Deep navy #081e44 fill. A single #149AD5 accent area permitted only if it covers at least 15% of the mark's bounding area (anything smaller disappears at 16px).
- No gradient, shadow, texture, letter, number.

CONCEPT
A single bold geometric mark drawn from the same family as the primary Tipovačka logo (ascending-bars / trajectory-check / chevron-matchup). Choose the family that simplifies best.

OUTPUT
One finished mark at 1024 × 1024. Then render a 16 × 16 proof below it on the same canvas to verify legibility.
```

## Quality checklist

- Does the 16 × 16 export still read as a distinct silhouette? Hold it at arm's length from the screen — if it becomes a smudge, simplify.
- Does the maskable 512 × 512 survive Android's rounded-crop preview? Use Chrome DevTools → Application → Manifest → "Icon maskable preview".
- Does the Safari pinned-tab monochrome SVG render in `#081e44` solid with no accent color leaking through?
- Does `manifest.webmanifest` validate? Run through the [Chrome Lighthouse PWA audit](https://web.dev/measure/).
