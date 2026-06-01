I now have everything needed. Here is the complete report.

---

# wtips Design System — CSS Foundations & Design Tokens Catalog

Source files (all under `/Users/janmikes/www/wtips-design-system/project/`):
- `colors_and_type.css` — **the canonical token source** (`:root` block) + semantic element styles + base utilities
- `pages/site.css` — landing/marketing/app-shell stylesheet (re-defines some utilities with hardcoded values)
- `pages/nav.css` — shared app-style header (`.wtnav`)
- `preview/base.css` — preview-card harness (imports `colors_and_type.css`)
- `ui_kits/organizer-webapp/app.css` — the organizer web-app component kit (the most app-relevant file)

Critical framing note: the system is **dark-first** (`html { color-scheme: dark }`, navy canvas, white-on-dark foreground, glass surfaces). Font is **Montserrat** (Google Fonts CDN, weights 100–900). There is meaningful **duplication/divergence**: tokens live in `colors_and_type.css`, but `site.css` and `app.css` frequently **hardcode raw values** (e.g. `rgba(255,255,255,0.06)` instead of `var(--glass-bg-strong)`) and even **redefine the same class with different values** (notably `.glass`, `.eyebrow`, `.btn-*`). Those divergences are flagged inline below — they matter for the implementation plan.

---

## 1. Complete Token Catalog (verbatim from `colors_and_type.css` `:root`)

### 1.1 Core brand
```
--brand-primary: #0f1726;   /* Deep navy — canvas */
--brand-white:   #ffffff;   /* Secondary — text, logos */
--brand-accent:  #4699d0;   /* Electric blue — primary action, highlights */
```

### 1.2 Primary scale — navy (DARK, 950 = deepest)
```
--navy-950: #070b14;   /* deepest — used for wash edges */
--navy-900: #0a111e;
--navy-850: #0f1726;   /* primary canvas */
--navy-800: #131d31;
--navy-700: #1b2742;
--navy-600: #243356;
--navy-500: #334670;
--navy-400: #4a5d8a;
--navy-300: #6b7ca3;
--navy-200: #96a3c1;
--navy-100: #c4cddd;
```
Note: scale runs dark→light as the number decreases (950 darkest, 100 lightest). There is **no `--navy-50` and no `--navy-000`**. `--navy-850` is the canonical canvas (equals `--brand-primary` `#0f1726`).

### 1.3 Accent scale — blue (900 = darkest)
```
--accent-900: #163754;
--accent-800: #1f5176;
--accent-700: #2a6e9e;
--accent-600: #3786bf;
--accent-500: #4699d0;   /* brand accent */
--accent-400: #65adde;
--accent-300: #8ac3e8;
--accent-200: #b3d8ef;
--accent-100: #dcecf8;
```
`--accent-500 = #4699d0 = --brand-accent`. `--accent-400 #65adde` is used for links and `--fg-accent`.

### 1.4 Semantic — status
```
--status-win:    #3ed598;   /* correct tip / match won */
--status-win-2:  #24a978;
--status-loss:   #ff5d7a;   /* failed tip */
--status-loss-2: #c8324d;
--status-draw:   #f5b544;   /* pending / draw */
--status-live:   #ff3b5c;   /* live match indicator */
--status-info:   var(--accent-500);
```
The `-2` variants are the darker end of the win/loss gradients. `--status-live #ff3b5c` is distinct from `--status-loss #ff5d7a` (live is hotter red). Note: components frequently use lighter **text** tints of these not declared as tokens, e.g. win text `#6fe4b5`, loss text `#ff8ea1`, live text `#ff7a90` / `#ff8ea1`.

### 1.5 Foreground (on dark)
```
--fg-1: #ffffff;                  /* primary text */
--fg-2: rgba(255,255,255,0.72);   /* secondary */
--fg-3: rgba(255,255,255,0.52);   /* tertiary / captions */
--fg-4: rgba(255,255,255,0.32);   /* disabled / placeholder */
--fg-accent: var(--accent-400);
```

### 1.6 Background
```
--bg-canvas:    var(--navy-850);     /* #0f1726 */
--bg-canvas-2:  var(--navy-900);     /* #0a111e */
--bg-surface:   #141e36;             /* card base */
--bg-surface-2: #1a2544;             /* raised */
--bg-inset:     #0c1321;             /* inputs, wells */
--bg-overlay:   rgba(7,11,20,0.72);  /* modal scrim */
```
`site.css` references `var(--bg-page)` (line 12) but **`--bg-page` is never defined** — that is a latent bug (body background falls back to none, relying on `--grad-canvas` not being applied there). Treat `--bg-canvas` as the real canvas token.

### 1.7 Glass
```
--glass-bg:        rgba(255,255,255,0.04);
--glass-bg-strong: rgba(255,255,255,0.07);
--glass-bg-tint:   rgba(70,153,208,0.08);   /* accent-tinted */
--glass-border:    rgba(255,255,255,0.09);
--glass-border-hi: rgba(255,255,255,0.16);
--glass-blur:      18px;
--glass-blur-lg:   32px;
--glass-inner-hi:  inset 0 1px 0 rgba(255,255,255,0.08);
```

### 1.8 Gradients
```
--grad-canvas:       radial-gradient(120% 80% at 50% 0%, #18233f 0%, var(--navy-850) 55%, var(--navy-900) 100%);
--grad-accent:       linear-gradient(135deg, #65adde 0%, #4699d0 45%, #2a6e9e 100%);
--grad-accent-soft:  linear-gradient(135deg, rgba(101,173,222,0.22) 0%, rgba(42,110,158,0.10) 100%);
--grad-headline:     linear-gradient(180deg, #ffffff 0%, #ffffff 40%, #8ac3e8 100%);
--grad-headline-alt: linear-gradient(100deg, #ffffff 0%, #b3d8ef 55%, #4699d0 100%);
--grad-card:         linear-gradient(180deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
--grad-win:          linear-gradient(135deg, #3ed598 0%, #24a978 100%);
--grad-loss:         linear-gradient(135deg, #ff5d7a 0%, #c8324d 100%);
--grad-glow-accent:  radial-gradient(60% 80% at 50% 0%, rgba(70,153,208,0.35) 0%, rgba(70,153,208,0) 70%);
```
`#4699d0` = `rgb(70,153,208)` — the accent RGB triple `70,153,208` recurs in nearly every glow/shadow/tint.

### 1.9 Borders
```
--border-1: rgba(255,255,255,0.06);
--border-2: rgba(255,255,255,0.10);
--border-3: rgba(255,255,255,0.18);
--border-accent: rgba(70,153,208,0.45);
```

### 1.10 Radii
```
--r-xs: 4px;   --r-sm: 8px;    --r-md: 12px;   --r-lg: 16px;
--r-xl: 20px;  --r-2xl: 24px;  --r-3xl: 32px;  --r-pill: 999px;
```
In practice components hardcode radii rather than using these tokens (cards/glass use `16px`, modal `20px`, buttons `12px`, pills `6px`/`999px`).

### 1.11 Spacing (4px base)
```
--s-1: 4px;   --s-2: 8px;   --s-3: 12px;  --s-4: 16px;
--s-5: 20px;  --s-6: 24px;  --s-8: 32px;  --s-10: 40px;
--s-12: 48px; --s-16: 64px; --s-20: 80px; --s-24: 96px;
```

### 1.12 Shadows
```
--shadow-sm:        0 1px 2px rgba(0,0,0,0.35);
--shadow-md:        0 8px 24px rgba(0,0,0,0.35);
--shadow-lg:        0 20px 48px rgba(0,0,0,0.45);
--shadow-xl:        0 32px 80px rgba(0,0,0,0.55);
--shadow-glow:      0 0 0 1px rgba(70,153,208,0.35), 0 12px 40px rgba(70,153,208,0.25);
--shadow-glow-soft: 0 10px 40px rgba(70,153,208,0.18);
--shadow-inset-hi:  inset 0 1px 0 rgba(255,255,255,0.08);
```
Shadows are deep/dark (high black alpha) — appropriate for a dark canvas, opposite of the light app's navy-tinted soft shadows.

### 1.13 Type

Font families:
```
--font-sans:    'Montserrat', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
--font-display: 'Montserrat', ui-sans-serif, system-ui, sans-serif;
--font-mono:    ui-monospace, 'SF Mono', 'Roboto Mono', Menlo, monospace;
```
Imported via: `@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@100;200;300;400;500;600;700;800;900&display=swap');` (top of `colors_and_type.css`).

Font sizes — display (fluid `clamp`):
```
--fs-display-xl: clamp(56px, 8vw, 104px);
--fs-display-lg: clamp(44px, 6vw, 72px);
--fs-display-md: clamp(36px, 5vw, 56px);
```
Headings (fixed):
```
--fs-h1: 40px;  --fs-h2: 32px;  --fs-h3: 24px;
--fs-h4: 20px;  --fs-h5: 17px;  --fs-h6: 14px;
```
Body:
```
--fs-body-lg: 18px;   --fs-body: 15px;   --fs-body-sm: 13px;
--fs-caption: 12px;   --fs-micro: 11px;
```
(`site.css` uses different, fluid heading sizes for `.h-display/.h1/.h2` — see §2.)

Weights:
```
--fw-thin: 100;     --fw-xlight: 200;  --fw-light: 300;
--fw-regular: 400;  --fw-medium: 500;  --fw-semibold: 600;
--fw-bold: 700;     --fw-xbold: 800;   --fw-black: 900;
```
Line heights:
```
--lh-tight: 1.02;  --lh-snug: 1.15;  --lh-normal: 1.35;
--lh-relaxed: 1.55;  --lh-loose: 1.7;
```
Letter spacing:
```
--ls-tighter: -0.04em;  --ls-tight: -0.02em;  --ls-normal: 0em;
--ls-wide: 0.04em;      --ls-wider: 0.08em;   --ls-widest: 0.16em;
```

### 1.14 Motion
```
--ease-standard: cubic-bezier(0.2, 0, 0, 1);
--ease-emphatic: cubic-bezier(0.2, 0.8, 0.2, 1);
--ease-exit:     cubic-bezier(0.4, 0, 1, 1);
--dur-fast:  120ms;   --dur-base: 200ms;
--dur-slow:  320ms;   --dur-xslow: 520ms;
```

### 1.15 Layout (containers)
```
--container-xl: 1280px;   --container-lg: 1120px;   --container-md: 960px;
```
App shells (`.app-shell`, `.wtnav .bar`) use `max-width: 1280px` = `--container-xl`. `site.css` `.container` is `1200px` (a one-off, not tokenized), `.container-narrow` `880px`.

### 1.16 Z-index
```
--z-base: 1;  --z-raised: 10;  --z-nav: 50;  --z-overlay: 100;  --z-modal: 200;  --z-toast: 300;
```
Used literally as `z-index: 50` (nav), `z-index: 200` (modal backdrop) in components rather than via the token.

---

## 2. Utility / Component Classes

### From `colors_and_type.css` (canonical base/typography utilities)

| Class | What it does | Key declarations |
|---|---|---|
| `body` | Dark canvas, Montserrat, fixed radial gradient bg | `color: var(--fg-1); background: var(--bg-canvas); background-image: var(--grad-canvas); background-attachment: fixed; font-size: var(--fs-body); line-height: var(--lh-relaxed)` |
| `.display-xl/.display-lg/.display-md` | Hero display type — black, tight, tighter tracking | `font-family: var(--font-display); font-weight: var(--fw-black); line-height: var(--lh-tight); letter-spacing: var(--ls-tighter)`; sizes `--fs-display-xl/lg/md` |
| `.display-gradient` | Clips `--grad-headline` into text | `background: var(--grad-headline); -webkit-background-clip: text; -webkit-text-fill-color: transparent` |
| `h1/.h1 … h6/.h6` | Heading scale; h6 is an uppercase wide-tracked eyebrow-style label | h1: xbold 40px; h2/h3: bold; h4/h5: semibold; h6: semibold 14px uppercase `ls-wider` `color: var(--fg-2)` |
| `p` | Body text in secondary fg | `color: var(--fg-2); line-height: var(--lh-relaxed)` |
| `.lead` | Larger intro paragraph | `font-size: var(--fs-body-lg); color: var(--fg-2)` |
| `.caption` | Small caption | `font-size: var(--fs-caption); color: var(--fg-3); letter-spacing: var(--ls-wide)` |
| `.eyebrow` | Uppercase accent label (TEXT-only here) | `font-size: var(--fs-caption); font-weight: var(--fw-semibold); letter-spacing: var(--ls-widest); text-transform: uppercase; color: var(--fg-accent)` |
| `.micro` | 11px uppercase wide | `font-size: var(--fs-micro); letter-spacing: var(--ls-wider); text-transform: uppercase` |
| `.mono` | Monospaced tabular | `font-family: var(--font-mono); font-variant-numeric: tabular-nums` |
| `.num-display` | Big scores/odds — display font, black, tabular | `font-family: var(--font-display); font-weight: var(--fw-black); font-variant-numeric: tabular-nums; letter-spacing: var(--ls-tight)` |
| `a` / `a:hover` | Links accent-400 → accent-300 on hover | `color: var(--accent-400); transition: color var(--dur-fast) var(--ease-standard)`; hover `var(--accent-300)` |
| `.glass` | **Canonical glass surface** | see §3 |
| `.glass-strong` | Heavier glass | see §3 |

### From `pages/site.css` (marketing/site shell — NOTE divergent redefinitions)

| Class | What it does | Key declarations |
|---|---|---|
| `.container` | Site container | `max-width: 1200px; margin: 0 auto; padding: 0 24px` |
| `.container-narrow` | Narrow container | `max-width: 880px; padding: 0 24px` |
| `.site-header` | Sticky glass top bar | `position: sticky; top: 0; z-index: 50; backdrop-filter: blur(22px) saturate(160%); background: rgba(10,17,30,0.72); border-bottom: 1px solid rgba(255,255,255,0.06)` |
| `.site-header nav.primary a` | Nav link, weight-shift on hover | base `color: rgba(255,255,255,0.72); font-weight: 300; border-bottom: 2px solid transparent`; hover `color:#fff; font-weight:700`; `.active` adds `border-bottom-color: var(--accent-500)` |
| `.btn` | Base button (site variant) | `display: inline-flex; padding: 11px 18px; border-radius: 12px; font-weight: 700; font-size: 13px; color: #fff; transition: transform/box-shadow/background/border-color 120ms`; hover `transform: translateY(-1px)` |
| `.btn-sm` / `.btn-lg` | Size mods | sm `8px 14px / 12px / r10`; lg `14px 22px / 14px / r14` |
| `.btn-primary` | Accent gradient CTA | `background: linear-gradient(135deg,#65adde,#4699d0 45%,#2a6e9e); box-shadow: 0 8px 20px rgba(70,153,208,0.25), inset 0 1px 0 rgba(255,255,255,0.18)`; hover deepens glow |
| `.btn-success` | Green gradient | `linear-gradient(135deg,#3ed598,#1f9d6e); box-shadow 0 8px 20px rgba(62,213,152,0.25)…` |
| `.btn-ghost` | Translucent outline | `background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.12)`; hover bumps to `0.08 / 0.20` |
| `.btn-link` | Text link button | `background: transparent; color: var(--accent-300)`; hover `var(--accent-200)` |
| `.section/.section-sm/.section-tight` | Vertical rhythm | `96px / 64px / 48px` vertical padding |
| `.eyebrow` (**redefined**) | Pill-style eyebrow (badge, not just text) | `display: inline-block; padding: 5px 12px; background: rgba(70,153,208,0.14); border: 1px solid rgba(70,153,208,0.40); color: var(--accent-300); border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase` |
| `.h-display/.h1/.h2/.h3/.h4` (**redefined**, fluid) | Site heading scale | h-display `clamp(40px,6vw,72px)/900/lh1.0/ls-0.03em`; h1 `clamp(32px,4.5vw,48px)/800`; h2 `clamp(28px,3.5vw,40px)/800`; h3 `24px/700`; h4 `18px/700` |
| `.lead` (**redefined**) | `font-size: clamp(16px,1.6vw,19px); color: rgba(255,255,255,0.72); max-width: 56ch` |
| `.surface` | Opaque card | `background: var(--bg-surface); border: 1px solid rgba(255,255,255,0.10); border-radius: 16px` |
| `.surface-light` | **Light-on-dark card** (white card) | `background: #ffffff; color: #141e36; border: 1px solid rgba(20,30,54,0.08); border-radius: 16px` |
| `.surface-accent` | Accent-gradient card | `background: linear-gradient(135deg,#65adde,#4699d0 50%,#2a6e9e); box-shadow: 0 12px 32px rgba(70,153,208,0.30), inset 0 1px 0 rgba(255,255,255,0.22); color:#fff` |
| `.glass` (**redefined, stronger**) | Site glass | `background: rgba(255,255,255,0.06); blur(22px) saturate(160%); border: 1px solid rgba(255,255,255,0.14); border-radius: 16px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.08)` |
| `.site-footer` | Dark footer | `border-top: 1px solid rgba(255,255,255,0.06); background: #07101e; padding: 56px 0 40px; color: var(--fg-2)`; `.cols` grid `1.4fr repeat(3,1fr)` |
| `.pill` + `.pill-accent/-success/-warn/-neutral/-live` | Status chips | base `padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; ls 0.06em; uppercase`; live has animated ring (`@keyframes ring-pulse` 1.4s, scale 0.7→2.4 fade) |
| `.bg-radial` | Atmospheric corner glows behind content | `::before` two radial gradients (top-right `rgba(70,153,208,0.30)`, bottom-left `rgba(58,103,160,0.25)`) |
| `.divider` | Hairline | `height: 1px; background: rgba(255,255,255,0.08)` |
| `.field` / `.input,.textarea,.select` | Form controls | input `padding: 11px 14px; font-weight: 300; background: rgba(12,19,33,0.85); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px`; focus `border-color: var(--accent-500); box-shadow: 0 0 0 3px rgba(70,153,208,0.18)` |

### From `pages/nav.css` (`.wtnav` app header)

| Class | What it does | Key declarations |
|---|---|---|
| `.wtnav` | Sticky glass app nav | `position: sticky; top:0; z-index:50; background: rgba(15,23,38,0.72); backdrop-filter: blur(22px) saturate(160%); border-bottom: 1px solid rgba(255,255,255,0.08)` |
| `.wtnav .bar` | Inner bar | `max-width: 1280px; padding: 0 32px; height: 72px; gap: 32px` |
| `.wtnav .brand-mark` | Logo square w/ accent gradient | `32×32; border-radius: 10px; background: linear-gradient(135deg,#65adde,#4699d0 45%,#2a6e9e); font-weight: 900; box-shadow: 0 4px 12px rgba(70,153,208,0.35)` |
| `.wtnav .brand-name` | Wordmark | `font-weight: 900; font-size: 19px; letter-spacing: -0.03em; color:#fff` |
| `.wtnav .brand-chip` | Small uppercase chip beside brand | `padding: 4px 9px; border-radius: 999px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); color: rgba(255,255,255,0.72); font-size: 10px; uppercase ls 0.08em` |
| `.wtnav .primary a` | Nav links | `color: rgba(255,255,255,0.72); border-bottom: 2px solid transparent`; hover `#fff`; `.active` `font-weight:600; border-bottom-color:#4699d0` |
| `.wtnav .icon-btn` | 36px icon button | `border-radius: 10px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10)`; hover `0.08`; `.dot` notification = `#ff5d7a` with 2px navy ring |
| `.wtnav .avatar` | 36px gradient avatar | `border-radius: 50%; background: linear-gradient(135deg,#65adde,#2a6e9e); font-weight: 700; border: 1px solid rgba(255,255,255,0.18)` |
| `.wtnav .menu-btn` | Mobile hamburger (hidden ≥900px) | `display: none` → `flex` under 900px; mobile `.primary` becomes dropdown panel `background: rgba(10,17,30,0.95)` |

### From `ui_kits/organizer-webapp/app.css` (THE app component kit — most relevant)

| Class | What it does | Key declarations |
|---|---|---|
| `.app-shell` | Page container | `max-width: 1280px; margin: 0 auto; padding: 0 32px 60px` |
| `.content-wrap` | `padding-top: 28px` |
| `.btn` (app variant) | Base button | `font-weight: 600; font-size: 14px; padding: 12px 22px; border-radius: 12px; transition: all 200ms var(--ease-standard); ls 0.01em` |
| `.btn-primary` | Accent CTA via token gradient | `background: var(--grad-accent); border-color: rgba(255,255,255,0.12); box-shadow: 0 10px 28px rgba(70,153,208,0.25), inset 0 1px 0 rgba(255,255,255,0.18)`; hover `translateY(-1px)` + deeper glow; **active `transform: scale(0.98)`** |
| `.btn-secondary` | Glassy secondary | `background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.14); backdrop-filter: blur(18px)`; hover `0.08 / 0.22` |
| `.btn-ghost` | Text-only | `color: var(--fg-2)`; hover `#fff` |
| `.btn-sm` | `padding: 8px 14px; font-size: 12px; border-radius: 10px` |
| `.btn[disabled]/:disabled` | Disabled state | `opacity: 0.45; cursor: not-allowed; transform: none !important`; hover suppressed |
| `.chip` + `.chip-live/-win/-loss/-pending/-accent/-neutral/-solid` | Status chips (pill, `border-radius: 999px`) | base `padding: 5px 11px; font-size: 11px; font-weight: 600; ls 0.06em`; live=red `#ff3b5c` w/ pulsing `.dot` (`@keyframes wtips-pulse 2s`, opacity 1→0.4); win text `#6fe4b5`; loss text `#ff8ea1`; pending `#f5b544`; accent `#8ac3e8`; solid `background: var(--grad-accent)` |
| `.card` | Opaque card, hover lift | `background: var(--bg-surface); border: 1px solid var(--border-2); border-radius: 16px; box-shadow: var(--shadow-md); transition: all 200ms var(--ease-standard)`; hover `border-color: var(--border-3); transform: translateY(-2px)` |
| `.card-glass` | Glass card w/ accent corner glow | `background: rgba(255,255,255,0.04); blur(18px) saturate(140%); border: 1px solid var(--border-2); box-shadow: inset 0 1px 0 rgba(255,255,255,0.08), var(--shadow-md); overflow: hidden`; `::before` radial accent glow top-right (see §3) |
| `.field` / `.field label` / inputs | Form block | label `font-size: 11px; font-weight: 600; ls 0.14em; uppercase; color: var(--fg-3)`; input `padding: 12px 14px; background: var(--bg-inset); border: 1px solid var(--border-2); border-radius: 10px; color:#fff`; focus `border-color: rgba(70,153,208,0.6); box-shadow: 0 0 0 3px rgba(70,153,208,0.18)` |
| `.avatar` / `-lg` / `-sm` | Round avatar | `32px / 44px / 24px`, `border-radius: 50%; font-weight: 700` |
| `.eyebrow` (**3rd definition**) | `font-size: 11px; font-weight: 600; letter-spacing: 0.16em; uppercase; color: var(--accent-400)` |
| `.num` | `font-variant-numeric: tabular-nums` |
| `.tight` / `.tighter` | `letter-spacing: -0.02em` / `-0.04em` |
| `.grad-headline` | Headline gradient text clip | `background: linear-gradient(100deg,#fff,#b3d8ef 55%,#4699d0); -webkit-background-clip: text; -webkit-text-fill-color: transparent` (= `--grad-headline-alt`) |
| `.modal-backdrop` | Scrim + blur, fade-in | `position: fixed; inset:0; background: rgba(7,11,20,0.72); backdrop-filter: blur(8px); z-index: 200; animation: fadeIn 200ms var(--ease-standard)` |
| `.modal-panel` | Modal card, slide-up | `max-width: 620px; background: linear-gradient(180deg,#1a2544,#141e36); border: 1px solid var(--border-3); border-radius: 20px; box-shadow: var(--shadow-xl); padding: 28px; animation: slideUp 300ms var(--ease-emphatic)` |
| `.stepper/.step-dot/.step-num/.step-label/.step-bar` | Wizard stepper | step-num `.active` `background: var(--accent-500); box-shadow: 0 0 0 4px rgba(70,153,208,0.18)`; `.done` `rgba(70,153,208,0.55)`; step-bar `height: 2px`, `.done` `rgba(70,153,208,0.4)` ⚠ **NOTE: `.step-num` base rule is malformed** — lines 105–107 leave the disabled-button rule unclosed so `.step-num` opener is missing (a real bug in the source) |
| `.option-card` | Selectable option tile | `padding: 14px 16px; border-radius: 12px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1)`; hover `0.05/0.18`; `.selected` `background: rgba(70,153,208,0.12); border-color: rgba(70,153,208,0.55); box-shadow: 0 0 0 3px rgba(70,153,208,0.12)` |
| `.variant-card` (+`::before`, `.variant-check`) | Scoring-variant tile w/ inner frame + check | `background: #0e1730; border-radius: 14px; min-height: 96px; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04)`; `::before` inset 6px inner border; `.selected` accent border + `0 0 0 3px rgba(70,153,208,0.12)`; check turns `var(--accent-400)` |
| `.scoring-fields` | Grouped inputs container | `padding: 6px 16px; background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px` |
| `.field-row` / `.label` / `.sub` | Settings row | row divided by `border-bottom: 1px solid rgba(255,255,255,0.06)`; `.label` `accent-400` uppercase ls 0.16em; `.sub` `13px #fff` |
| `.num-input` | Small numeric box | `width: 64px; padding: 6px 8px; text-align: center; background: var(--bg-inset); border-radius: 8px; font-weight: 700`; focus accent ring |
| `.copy-field` | Input + copy button group | `background: var(--bg-inset); border: 1px solid var(--border-2); border-radius: 10px`; button `background: rgba(70,153,208,0.14); color: var(--accent-300); uppercase`; hover `0.22 / #fff` |
| `.email-chips` / `.email-chip` | Tag-input + removable chips | container `background: var(--bg-inset); min-height: 48px`; chip `background: rgba(70,153,208,0.16); border: 1px solid rgba(70,153,208,0.32); border-radius: 999px` |
| `::-webkit-scrollbar*` | Custom dark scrollbar | `width: 10px; thumb: rgba(255,255,255,0.1)` → hover `0.18`, transparent track |

### From `preview/base.css` (preview harness only — not product UI)
Layout helpers `.stack` `.row` `.wrap` `.grid` (flex/grid `gap: 12px`); token-doc labels `.chip-label` (11px uppercase `ls 0.14em` `fg-3`), `.hex` and `.token` (mono 11px). Body uses `overflow: hidden; padding: 24px` + `--grad-canvas`.

---

## 3. Glass-morphism, Gradient & Glow Recipes (exact)

### Glass recipe — canonical (`colors_and_type.css`)
```css
.glass {
  background: var(--glass-bg);                                  /* rgba(255,255,255,0.04) */
  backdrop-filter: blur(var(--glass-blur)) saturate(140%);     /* blur 18px */
  -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(140%);
  border: 1px solid var(--glass-border);                       /* rgba(255,255,255,0.09) */
  box-shadow: var(--glass-inner-hi), var(--shadow-md);
       /* inset 0 1px 0 rgba(255,255,255,0.08)  +  0 8px 24px rgba(0,0,0,0.35) */
}
.glass-strong {
  background: var(--glass-bg-strong);                           /* rgba(255,255,255,0.07) */
  backdrop-filter: blur(var(--glass-blur-lg)) saturate(160%);  /* blur 32px */
  border: 1px solid var(--glass-border-hi);                    /* rgba(255,255,255,0.16) */
  box-shadow: var(--glass-inner-hi), var(--shadow-lg);
       /* inset highlight  +  0 20px 48px rgba(0,0,0,0.45) */
}
```
**The four-part recipe:** (1) near-transparent white fill, (2) `backdrop-filter: blur + saturate`, (3) 1px translucent-white border, (4) `inset 0 1px 0 rgba(255,255,255,0.08)` inner top highlight (the `--glass-inner-hi` / `--shadow-inset-hi`). Always pair the `-webkit-` prefix.

Divergent glass values across files (important — they are NOT identical):
- `colors_and_type.css .glass`: bg `0.04`, blur `18px`/sat 140%, border `0.09`, no radius set
- `site.css .glass`: bg `0.06`, blur `22px`/sat 160%, border `0.14`, `border-radius: 16px`
- `app.css .card-glass`: bg `0.04`, blur `18px`/sat 140%, border `var(--border-2)` (0.10), `border-radius: 16px`, plus accent corner-glow `::before`
- Headers (`.site-header`, `.wtnav`): bg `rgba(10,17,30,0.72)` / `rgba(15,23,38,0.72)`, blur `22px`/sat 160% — opaque-tinted glass (not white).

### Accent gradient (the primary button / brand gradient)
```css
--grad-accent: linear-gradient(135deg, #65adde 0%, #4699d0 45%, #2a6e9e 100%);
/* = accent-400 → accent-500 → accent-700, 135deg */
```
Always paired in CTAs with: `box-shadow: 0 8–16px 20–36px rgba(70,153,208,0.25–0.35), inset 0 1px 0 rgba(255,255,255,0.18)`.

### Headline gradient text (two variants)
```css
--grad-headline:     linear-gradient(180deg, #ffffff 0%, #ffffff 40%, #8ac3e8 100%);   /* vertical white→accent-300 */
--grad-headline-alt: linear-gradient(100deg, #ffffff 0%, #b3d8ef 55%, #4699d0 100%);   /* diagonal white→accent-200→accent-500 */
```
Applied with the text-clip trio: `-webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; color: transparent;` (`.display-gradient`, `.grad-headline`).

### Glow recipes
- **Accent corner glow (card-glass `::before`):**
  ```css
  background: radial-gradient(60% 80% at 100% 0%, rgba(70,153,208,0.16) 0%, rgba(70,153,208,0) 60%);
  ```
- **Token glow shadow:** `--shadow-glow: 0 0 0 1px rgba(70,153,208,0.35), 0 12px 40px rgba(70,153,208,0.25)` (ring + bloom); soft variant `--shadow-glow-soft: 0 10px 40px rgba(70,153,208,0.18)`.
- **Hero/ambient glow gradient:** `--grad-glow-accent: radial-gradient(60% 80% at 50% 0%, rgba(70,153,208,0.35) 0%, rgba(70,153,208,0) 70%)`.
- **Page atmosphere (`.bg-radial::before`, and organizer `body`):** layered corner radials, e.g. organizer body = `radial-gradient(60% 50% at 80% 10%, rgba(70,153,208,0.18) 0%, transparent 60%), var(--grad-canvas)`.
- **Focus ring (form inputs everywhere):** `box-shadow: 0 0 0 3px rgba(70,153,208,0.18)` + `border-color: var(--accent-500)` or `rgba(70,153,208,0.6)`.

---

## 4. Motion & Interaction-State Rules

**Easings/durations (tokens):** standard `cubic-bezier(0.2,0,0,1)` (most transitions), emphatic `cubic-bezier(0.2,0.8,0.2,1)` (modal slide-up, overshoot), exit `cubic-bezier(0.4,0,1,1)`. Durations `fast 120ms / base 200ms / slow 320ms / xslow 520ms`.

**Interaction states (the consistent pattern):**
- **Hover (buttons):** `transform: translateY(-1px)` + deepen accent glow shadow. Ghost/secondary buttons: raise white bg alpha (`0.04→0.08`) and border alpha (`0.12/0.14→0.20/0.22`).
- **Pressed/active:** `.btn-primary:active { transform: scale(0.98); }` (only defined in `app.css`).
- **Focus:** accent border + `0 0 0 3px rgba(70,153,208,0.18)` outer ring; inputs set `outline: none` and rely on the ring.
- **Disabled:** `opacity: 0.45; cursor: not-allowed; transform: none !important`, hover effects suppressed.
- **Selected (option/variant cards):** accent-tinted bg `rgba(70,153,208,0.12)` + accent border `rgba(70,153,208,0.55)` + `0 0 0 3px rgba(70,153,208,0.12)` ring.
- **Links:** `accent-400 → accent-300` over `--dur-fast`.
- **Nav links:** weight animates `300→700` (site) / `500→600` (wtnav) and an accent underline appears on `.active` (`border-bottom-color: var(--accent-500)` / `#4699d0`).
- **Cards:** hover `border-color: var(--border-3)` + `translateY(-2px)`.

**Live / pulse animations:**
- `@keyframes wtips-pulse` (app.css): `0%,100% opacity:1; 50% opacity:0.4` over `2s infinite` — used by `.chip-live .dot` (a 6px `#ff3b5c` dot).
- `@keyframes ring-pulse` (site.css): `0% scale(0.7) opacity:1 → 100% scale(2.4) opacity:0` over `1.4s cubic-bezier(0.2,0,0,1) infinite` — expanding ring on `.pill-live .ring` (`#ff5d7a` core + `rgba(255,93,122,0.7)` ring).
- `@keyframes fadeIn` (modal backdrop, 200ms) and `@keyframes slideUp` (`translateY(20px)→0`, 300ms emphatic) for modals.

**Counters / numeric:** no JS counter animation in CSS; numeric emphasis is purely typographic via `.num-display` / `.num` / `.mono` using `font-variant-numeric: tabular-nums` + Montserrat Black, so digits stay aligned during JS count-up.

---

## 5. Proposed Token-Mapping Table (current app → wtips)

Current app theme lives in `/Users/janmikes/www/tipovacka/assets/styles/app.css` (`@theme` block, Tailwind v4 `@import "tailwindcss"`). It is **LIGHT-first** (white canvas, navy text, cyan CTA, navy-tinted soft shadows). wtips is **DARK-first** (navy canvas, white text, blue accent, black deep shadows). This is a polarity inversion, not a 1:1 swap — the mapping below preserves *role*, and flags where semantics flip.

| Current app token | Current value | Role today | → wtips equivalent | wtips value | Notes / polarity caveat |
|---|---|---|---|---|---|
| *(implicit page bg = white)* | `#fff` | Page canvas (light) | `--bg-canvas` / `--navy-850` | `#0f1726` | **Polarity flips.** Light canvas becomes dark navy + `--grad-canvas` radial. |
| `--color-navy-900` | `#081e44` | Primary text + dark nav surface | Split by role: as **text on light** → `--fg-1` `#fff` (now text is white). As **dark surface** → `--bg-canvas-2`/`--navy-900` `#0a111e` | — | One token did double duty (text AND surface); in wtips these diverge because canvas is dark. |
| `--color-navy-800` | `#0b2552` | Dark surfaces / nav | `--navy-800` | `#131d31` | wtips navy-800 is much darker/desaturated; not a hue match, role match only. |
| `--color-navy-700` | `#0f2d5f` | Secondary dark text / accents | `--fg-2` (secondary text) | `rgba(255,255,255,0.72)` | On dark, secondary text is translucent white, not navy. |
| `--color-navy-500` | `#23478a` | Mid navy | `--navy-500` | `#334670` | Role match (mid navy). |
| `--color-navy-200` | `#b6c4de` | Light borders/dividers (on light) | `--border-2` / `--border-3` | `rgba(255,255,255,0.10)` / `0.18` | Borders become translucent-white on dark instead of light-navy. |
| `--color-navy-100` | `#d9e2f1` | Hairline borders, card borders | `--border-1` | `rgba(255,255,255,0.06)` | Used a lot (`border: 1px solid var(--color-navy-100)`). Map to translucent-white border tokens. |
| `--color-navy-50` | `#eef2f9` | Light tint backgrounds (hover rows, chips) | `--bg-surface` / `rgba(255,255,255,0.04)` | `#141e36` / glass fill | Light "tint" surfaces become dark raised surfaces or low-alpha white glass. |
| `--color-cyan-500` | `#149ad5` | Primary CTA / focus ring / active | `--accent-500` (`--brand-accent`) | `#4699d0` | **Primary accent remap** cyan→electric blue. CTA = `--grad-accent`, focus ring `rgba(70,153,208,0.18)`. |
| `--color-cyan-600` | `#0f84b8` | CTA hover / pressed | `--accent-600` / `--accent-700` | `#3786bf` / `#2a6e9e` | Gradient stop end; hover deepens via shadow not color in wtips. |
| `--color-cyan-400` | `#3eb5e6` | Highlights, icon fills, links | `--accent-400` / `--fg-accent` | `#65adde` | Links use `accent-400→accent-300` hover. |
| `--color-cyan-100` | `#d6f0fa` | Soft accent backgrounds (chips, active tabs) | `--glass-bg-tint` / `rgba(70,153,208,0.12–0.16)` | `rgba(70,153,208,0.08)` | Light cyan wash → low-alpha accent tint on dark. |
| `--shadow-card` | `0 1px 2px rgba(8,30,68,0.04), 0 2px 8px -2px rgba(8,30,68,0.06)` | Resting card shadow (navy-tinted, soft) | `--shadow-sm` / `--shadow-md` | `0 1px 2px rgba(0,0,0,0.35)` / `0 8px 24px rgba(0,0,0,0.35)` | **Tint flips navy→pure black, alpha much higher** (0.04→0.35) because shadows on dark need more depth. |
| `--shadow-card-hover` | `0 10px 30px -10px rgba(8,30,68,0.18)` | Hover-lift shadow | `--shadow-lg` (+ optional `--shadow-glow-soft`) | `0 20px 48px rgba(0,0,0,0.45)` (+ `0 10px 40px rgba(70,153,208,0.18)`) | wtips adds an *accent glow* option for emphasis cards/CTAs. |
| `--container-site` | `88rem` (= 1408px) | Site max-width | `--container-xl` | `1280px` | wtips app shells use 1280px; current 1408px is wider. If preserving width, keep 88rem; if matching wtips chrome, use 1280px. |
| *(no token — Tailwind default red)* `--color-red-400/500` | Tailwind reds | Form error border/ring | `--status-loss` / `--status-loss-2` | `#ff5d7a` / `#c8324d` | Map invalid-field styling to wtips loss reds for visual consistency. |

Additional mapping guidance for the implementation plan:
- **New token groups with no current equivalent** (must be introduced): full `--navy-*` 11-step + `--accent-*` 9-step scales, `--status-win/draw/live`, `--fg-1..4`, all `--glass-*`, all `--grad-*`, `--border-accent`, radii/spacing/motion/z-index scales, full type scale (Montserrat + display/heading/body sizes, weights, line-heights, letter-spacing).
- **Font change:** current app likely uses a default/sans stack; wtips mandates **Montserrat** (CDN `@import`, weights 100–900) and leans on heavy weights (`--fw-black 900` for display/scores). This is a load-bearing brand change.
- **`color-scheme: dark`** must be set on `html`, and `background-attachment: fixed` + `--grad-canvas` on `body`.
- **Duplication to resolve before porting:** `.glass`, `.eyebrow`, `.btn-*`, `.h1/.h2`, `.lead` exist in 2–3 files with conflicting values. Pick the **organizer `app.css` + `colors_and_type.css`** definitions as canonical for the app (they use tokens), and treat `site.css`'s hardcoded variants as marketing-only.
- **Known source bugs to avoid copying:** `--bg-page` referenced but undefined in `site.css`; the malformed `.step-num` rule block (app.css lines 105–108, missing selector/opening brace after the `.btn:disabled:hover` rule).