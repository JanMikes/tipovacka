# 01 — Foundation (Phase 1)

Everything else depends on this. Land it as one coherent change before re-skinning
any page. Source of exact values: [`analysis/ds_tokens-css.md`](analysis/ds_tokens-css.md).

Files touched:
- `assets/styles/app.css` — full rewrite of `@theme` + new `@layer components`.
- `assets/fonts/*.woff2` — self-hosted Montserrat (new).
- `templates/base.html.twig` — dark body, new nav/footer, fonts, theme-color.
- `assets/controllers/*.js` — recolor hard-coded light class strings.
- `importmap.php`, `assets/app.js` — remove dead deps.
- `config/` + a Twig global — brand name.

---

## 1. Design tokens — Tailwind v4 `@theme` rewrite

Replace the current light `@theme` block (`app.css:5–27`) entirely. The new theme
is **dark-first**. Keep the pattern of overriding `navy`/`cyan` palette names, but
add `accent`, status colors, and the supporting scales. Tailwind v4 exposes every
`--color-*` as a utility (`bg-navy-850`, `text-accent-500`, `border-white/10`, …).

```css
@import "tailwindcss";
/* vendor skins re-imported AFTER tailwind so we can override; see §5 */
@import "../vendor/flatpickr/dist/flatpickr.min.css";
@import "../vendor/tom-select/dist/css/tom-select.min.css";

@theme {
  /* ---- Brand ---- */
  --color-brand:        #0f1726;   /* canvas */
  --color-accent:       #4699d0;   /* electric blue — THE accent */

  /* ---- Navy scale (DARK-first: 950 darkest → 100 lightest) ---- */
  --color-navy-950: #070b14;
  --color-navy-900: #0a111e;
  --color-navy-850: #0f1726;   /* ★ primary canvas */
  --color-navy-800: #131d31;
  --color-navy-700: #1b2742;
  --color-navy-600: #243356;
  --color-navy-500: #334670;
  --color-navy-400: #4a5d8a;
  --color-navy-300: #6b7ca3;
  --color-navy-200: #96a3c1;
  --color-navy-100: #c4cddd;

  /* ---- Accent (blue) scale ---- */
  --color-accent-900: #163754;
  --color-accent-800: #1f5176;
  --color-accent-700: #2a6e9e;
  --color-accent-600: #3786bf;
  --color-accent-500: #4699d0;   /* = brand accent */
  --color-accent-400: #65adde;
  --color-accent-300: #8ac3e8;
  --color-accent-200: #b3d8ef;
  --color-accent-100: #dcecf8;

  /* ---- Status ---- */
  --color-win:   #3ed598;   --color-win-2:  #24a978;
  --color-loss:  #ff5d7a;   --color-loss-2: #c8324d;
  --color-draw:  #f5b544;   /* pending / draw / gold */
  --color-live:  #ff3b5c;   /* marketing only */
  /* medal */
  --color-gold:   #f5cd54;  --color-silver: #cfd6e0;  --color-bronze: #d49460;

  /* ---- Surfaces (use as bg-*) ---- */
  --color-surface:    #141e36;  /* card base */
  --color-surface-2:  #1a2544;  /* raised */
  --color-inset:      #0c1321;  /* inputs / wells */

  /* ---- Shadows (deep black, dark-canvas appropriate) ---- */
  --shadow-sm:   0 1px 2px rgba(0,0,0,0.35);
  --shadow-card: 0 8px 24px rgba(0,0,0,0.35);
  --shadow-lg:   0 20px 48px rgba(0,0,0,0.45);
  --shadow-xl:   0 32px 80px rgba(0,0,0,0.55);
  --shadow-glow: 0 0 0 1px rgba(70,153,208,0.35), 0 12px 40px rgba(70,153,208,0.25);
  --shadow-glow-soft: 0 10px 40px rgba(70,153,208,0.18);

  /* ---- Type ---- */
  --font-sans: 'Montserrat', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;

  /* ---- Layout ---- */
  --container-site: 1280px;   /* DS app shells use 1280; was 88rem */
}
```

Also keep, as plain CSS custom properties on `:root` (not `@theme` — they're used
in the component layer, not as utilities), the **glass / gradient / radius / motion**
tokens. Add them right after the Tailwind import:

```css
:root {
  /* foreground on dark */
  --fg-1:#fff; --fg-2:rgba(255,255,255,.72); --fg-3:rgba(255,255,255,.52); --fg-4:rgba(255,255,255,.32);
  /* borders */
  --border-1:rgba(255,255,255,.06); --border-2:rgba(255,255,255,.10);
  --border-3:rgba(255,255,255,.18); --border-accent:rgba(70,153,208,.45);
  /* glass */
  --glass-bg:rgba(255,255,255,.04); --glass-bg-strong:rgba(255,255,255,.07);
  --glass-border:rgba(255,255,255,.10); --glass-border-hi:rgba(255,255,255,.16);
  --glass-inner-hi:inset 0 1px 0 rgba(255,255,255,.08);
  /* gradients */
  --grad-canvas:radial-gradient(120% 80% at 50% 0%, #18233f 0%, #0f1726 55%, #0a111e 100%);
  --grad-accent:linear-gradient(135deg,#65adde 0%,#4699d0 45%,#2a6e9e 100%);
  --grad-headline:linear-gradient(100deg,#fff 0%,#b3d8ef 55%,#4699d0 100%);
  --grad-win:linear-gradient(135deg,#3ed598 0%,#24a978 100%);
  --grad-glow-accent:radial-gradient(60% 80% at 50% 0%, rgba(70,153,208,.35) 0%, rgba(70,153,208,0) 70%);
  /* radii (informational; Tailwind rounded-* still available) */
  --r-md:12px; --r-lg:16px; --r-xl:20px; --r-2xl:24px;
  /* motion */
  --ease-standard:cubic-bezier(.2,0,0,1); --ease-emphatic:cubic-bezier(.2,.8,.2,1);
  --dur-fast:120ms; --dur-base:200ms; --dur-slow:320ms;
}
html { color-scheme: dark; }
```

Old→new mapping highlights (full table in `analysis/ds_tokens-css.md` §5):
`white canvas → navy-850 + --grad-canvas`; `navy-900 text → fg-1 white`;
`cyan-500 CTA → accent-500 + --grad-accent`; `navy-100 borders → border-2/3
(translucent white)`; `navy-50 tint surfaces → surface / glass`;
`shadow-card (navy-tinted) → black, alpha 0.35`. **It is a polarity inversion,
not a 1:1 swap** — read roles, not hues.

> **DO NOT** copy these source bugs from the DS CSS: `--bg-page` (undefined in
> `site.css`), the malformed `.step-num` rule in organizer `app.css` (lines
> ~105–108). The DS files also redefine `.glass`/`.eyebrow`/`.btn-*` with
> conflicting values across `colors_and_type.css` / `site.css` / `app.css`;
> treat the **organizer `app.css` + `colors_and_type.css`** definitions as
> canonical for the app, and `site.css` variants as marketing-only.

---

## 2. Body, canvas, base typography

In `app.css` base layer (and reflected by `base.html.twig` body classes):

```css
@layer base {
  body {
    font-family: var(--font-sans);
    color: var(--fg-1);
    background: var(--color-navy-850);
    background-image: var(--grad-canvas);
    background-attachment: fixed;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
  }
  /* keep existing button cursor rules */
}
```

`base.html.twig` body class becomes e.g. `min-h-screen flex flex-col bg-navy-850
text-white antialiased` (drop `bg-navy-50/40 text-navy-900`). Keep
`data-turbo="false"`.

Numerics: add a `.num` utility (or use Tailwind `tabular-nums`) — **every** score,
points, odds, time, rank, percentage uses `font-variant-numeric: tabular-nums`.

---

## 3. Fonts — self-host Montserrat

1. Convert the provided `~/www/wtips-design-system/project/assets/fonts/Montserrat-*.otf`
   to **woff2** (weights 300/400/500/600/700/800/900). If a converter isn't
   available in the container, download Montserrat woff2 from the Google Fonts
   files (same metrics) — but **self-host** the files under
   `assets/fonts/montserrat/`. Do not keep the Google CDN `@import`.
2. Declare `@font-face` in `app.css` using AssetMapper-resolved URLs.
   With Tailwind/AssetMapper, reference via relative path from `app.css`:

```css
@font-face{font-family:'Montserrat';font-style:normal;font-weight:300;font-display:swap;
  src:url('../fonts/montserrat/montserrat-300.woff2') format('woff2');}
/* repeat for 400,500,600,700,800,900 */
```

3. Remove `theme-color #081e44` → `#0f1726` in `base.html.twig`. Update favicons
   later (Phase 2 polish); not blocking.

Acceptance: page renders Montserrat with **no** network request to
`fonts.googleapis.com` (check Network tab / `grep -r googleapis templates assets`
returns nothing).

---

## 4. `@layer components` — the Wtips component CSS

Port the design system's composite components into one `@layer components` block
in `app.css`. These back the Twig components in `02-components.md`. Keep them
**token-driven** (use the `var(--…)` + Tailwind color vars above). Implement at
least these classes (exact declarations in `analysis/ds_components.md` &
`ds_tokens-css.md`):

- **Nav:** `.wtnav`, `.wtnav .bar`, `.wtnav .brand`, `.brand-mark`, `.brand-name`,
  `.wtnav .primary a` (+`.active`), `.wtnav .icon-btn`, `.wtnav .avatar`,
  `.wtnav .nav-cta`, mobile `.menu-btn` + open state.
- **Footer:** `.wtfoot` (marketing 4-col) + `.app-foot` (mini).
- **Buttons:** `.btn` base, `.btn-primary` (accent gradient + glow),
  `.btn-success` (green gradient), `.btn-ghost`, `.btn-danger`, `.btn-link`,
  sizes `.btn-sm`/`.btn-lg`, `:disabled`, `:active{transform:scale(.98)}`.
  Uniform radius **12px**. Marketing-only: `.btn-light`, `.btn-clear`.
- **Pills/chips:** `.pill` + `.pill-soon`(amber)/`.pill-tipped`(green)/
  `.pill-done`(white)/`.pill-locked`/`.pill-accent`/`.pill-neutral`. Radius 6px.
  (Marketing-only `.pill-live` with pulse ring — used on landing hero only.)
- **Badges:** `.badge` + win/loss/draw/pending/group/organizer/points variants
  (icon + label, radius 6px, weight 500; points bold 700, tabular).
- **Surfaces/cards:** `.card` (solid `--color-surface`, hover lift −2px),
  `.card-glass` (translucent + `blur(18px) saturate(140%)` + inner highlight +
  accent corner-glow `::before`), `.surface-accent` (accent gradient + sheen),
  `.glass` (sticky-header glass).
- **Tip card:** `.tip-card` (+`.light`/`.accent` surface variants),
  `.tip-head`/`.tip-stage`/`.tip-teams`/`.tip-team`/`.tip-vs`, `.flag` (44px coin),
  `.score-input`+`.steppers`+`.colon` (64×46 stepper box), `.final-score`,
  `.result-banner`, `.btn-block`/`.btn-primary-block`/`.btn-edit-block`.
  **(This is the single most important component — see `02` and
  `analysis/ds_components.md` for full markup/CSS.)**
- **Leaderboard:** `.lb`/`.lb-table`/`.lb-thead`/`.lb-tr`(+`.me`), rank colors
  `.rank-1/2/3` (gold/silver/bronze + glow), `.lb-delta` up/down, `.lb-acc`
  (% + thin bar), `.lb-streak`, gap rows. Plus `.podium`/`.pod` (top-3 cards).
- **Stats:** `.stat`/`.stat-lbl`/`.stat-val`/`.stat-meta` (glass stat card).
- **Forms:** `.field`/`.field label` (uppercase 10–11px, letter-spaced, `--fg-3`),
  inputs `bg --color-inset; border --border-2; radius 10px; focus → accent border
  + 0 0 0 3px rgba(70,153,208,.18)`. Error state → `--color-loss`.
  `.num-input` (64px stepper), `.scoring-fields`, `.variant-card`, `.option-card`.
- **PIN inputs:** `.pin-card`, `.pin-inputs` (8 boxes + `—` separator after 4th),
  `.pin-btn` (disabled until 8 filled).
- **Distribution:** `.dist-bar` (1/X/2 segmented blue/gold/red), `.dist-pcts`.
- **Modal:** `.modal-backdrop` (scrim + blur + fadeIn), `.modal-panel`
  (gradient panel, radius 20px, slideUp 300ms emphatic). Reuse the existing
  `<dialog>` + `confirm` controller pattern where possible.
- **Eyebrow / display:** `.eyebrow` (uppercase, letter-spaced, accent),
  `.display-gradient`/`.grad-headline` (text-clip gradient), `.section` rhythm.
- **Atmosphere:** `.bg-radial` (offset accent glows), the marketing hero
  background (radial glows + masked grid). **One accent glow per screen** — it's a
  spotlight, not a brush.

Animations to define: `@keyframes ring-pulse` (live dot — marketing only),
`@keyframes fadeIn`, `@keyframes slideUp`.

> Keep this layer organized with comments mirroring `02-components.md` so each
> Twig component maps to a clearly-labelled CSS block.

---

## 5. Vendor skin reskins (dark)

These are hand-written CSS in `app.css` hard-coded to the light look. Rework to
dark surfaces (read `analysis/cur_frontend-infra.md` §6 for exact line ranges):

- **Flatpickr** (`app.css` ~71–235): dark calendar — panel `--color-inset`/
  `--color-surface`, white text, accent selected day, accent today, translucent
  borders. Used by `datepicker_controller.js`.
- **Tom Select** (`app.css` ~324–419): control bg `--color-inset` (not `#fff`),
  translucent borders, dark dropdown, accent active option, white text. Used by
  `tom_select_controller.js`.
- **`.confirm-dialog` + `::backdrop`** (`app.css` ~237–285): dark glass panel;
  backdrop `rgba(7,11,20,.72)` + blur.
- **Replace `.hero-bg`** (light gradient + navy dot grid) with the dark marketing
  hero background (radial blue glows + dark gradient + masked grid).
- **JS class strings** (won't be caught by template sweeps — fix explicitly):
  - `confirm_controller.js` — builds the dialog with `bg-white text-navy-900
    ring-navy-900/5 bg-navy-50 …`. Rewrite to dark glass classes
    (`bg-surface-2 text-white border-white/10`, danger `bg-loss`, etc.).
  - `datepicker_controller.js` clear button — `text-navy-900/40 …` → light-on-dark.
  - `tom_select_controller.js` option renderer — `text-navy-900/60 text-gray-400`
    → `text-white/70 text-white/40`.
- **`[aria-invalid]` + `label.required::after`**: keep, but map red to
  `--color-loss` for consistency.

---

## 6. Dead-code cleanup (do this in Phase 1)

Per `analysis/cur_frontend-infra.md` §3. Removing these shrinks the surface and
de-risks the reskin:

- **Remove from `importmap.php` + `assets/`:** `alpinejs` (imported in `app.js`,
  zero directives), `leaflet` (+ css + marker PNGs), `glightbox` css.
- **Delete orphaned vendor dirs:** `assets/vendor/konva`, `assets/vendor/chart.js`,
  `assets/vendor/signature_pad` (none referenced). *(If analytics charts are ever
  wanted later, chart.js can be re-added — but it's dead now.)*
- **Delete** `assets/controllers/hello_controller.js` (+ its single demo usage).
- **`assets/app.js`:** drop the Alpine import + `Alpine.start()` (becomes just
  `import './stimulus_bootstrap.js';`).
- **Fix missing icon:** `bin/console ux:icons:import lucide:flag` (referenced in
  `invitation/landing.html.twig`; currently throws in dev). Also import every new
  Lucide icon listed in `02-components.md` before use.
- **Remove unused local icons** only if confident (`chevron-left`, `star`); keep
  `medal` — it's used by the podium.

Acceptance for cleanup: `composer quality` green; app boots; `grep -rn "Alpine\|leaflet\|glightbox\|konva\|chart.js\|signature_pad" assets/ importmap.php` returns nothing meaningful.

---

## 7. Brand name = config-driven (dual deployment)

The brand string ("Wtips" vs "Tipovačka") must not be hard-coded, because `main`
deploys to wtips.cz (Wtips) while the `tipovacka` branch deploys to
tipovacka.thedevs.cz and may keep "Tipovačka". (See repo memory: dual deployment.)

- Add a parameter, e.g. `config/services.yaml`:
  `parameters: { app.brand_name: '%env(default:default_brand:APP_BRAND_NAME)%' }`
  with a Twig global `brand_name` (via `config/packages/twig.php` `globals`) and a
  default of `Wtips`. Set `APP_BRAND_NAME=Tipovačka` in the tipovacka branch env.
- Replace hard-coded "Tipovačka" in `base.html.twig`, footer, emails, privacy
  contact (`kontakt@tipovacka.cz` → brand-derived), `<title>`, OG/Twitter meta,
  `og:site_name`, `apple-mobile-web-app-title`, `site.webmanifest`.
- The **W brand-mark** + wordmark SVGs come from the design system
  (`assets/logo-wtips.svg`, `assets/logo-mark.svg`). For the sticky nav use the
  CSS gradient-square "W" + wordmark (cheap, reliable); keep the SVG mark for
  favicons/share images. Copy both SVGs into `assets/images/logo/`.

---

## Phase-1 acceptance checklist

- [ ] `@theme` rewritten to dark tokens; `bg-*`/`text-*`/`border-*` utilities now
      resolve to Wtips colors.
- [ ] Body is dark navy with `--grad-canvas`; `color-scheme: dark`.
- [ ] Montserrat self-hosted; no Google Fonts request.
- [ ] `@layer components` present with all listed component classes.
- [ ] Flatpickr, Tom Select, confirm-dialog reskinned dark; JS class strings fixed.
- [ ] Dead deps removed; `lucide:flag` + new icons imported.
- [ ] Brand name config-driven; `base.html.twig` nav/footer rebuilt (see `02`).
- [ ] `composer quality` green; app boots; spot-check 3 pages (dashboard, a form,
      a leaderboard) render dark without obvious light-theme leftovers.
- [ ] A short "Deviations" note appended to `03-phases.md` if anything changed.
