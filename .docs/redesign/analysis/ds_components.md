I have read every requested file in full plus the two CSS token files they depend on. Here is the complete component catalog.

---

# Wtips Design System — Component Catalog

All preview files live in `/Users/janmikes/www/wtips-design-system/project/preview/`. They share two stylesheets: `preview/base.css` (layout helpers `.stack/.row/.wrap/.grid`, `.chip-label`, `.hex`, `.token`) which `@import`s `/Users/janmikes/www/wtips-design-system/project/colors_and_type.css` (the canonical token file — all `var(--…)` resolve here). Every preview also loads **Montserrat** (weights 100–900) from Google Fonts. Canvas background is `var(--bg-canvas)` `#0f1726` with `--grad-canvas` radial overlay.

---

## GLOBAL TOKENS (source: `colors_and_type.css`)

These are the load-bearing values reused by every component below.

**Core brand:** `--brand-primary #0f1726` (deep navy canvas), `--brand-white #ffffff`, `--brand-accent #4699d0` (electric blue).

**Navy scale:** 950 `#070b14`, 900 `#0a111e`, **850 `#0f1726` ★ primary canvas**, 800 `#131d31`, 700 `#1b2742`, 600 `#243356`, 500 `#334670`, 400 `#4a5d8a`, 300 `#6b7ca3`, 200 `#96a3c1`, 100 `#c4cddd`.

**Accent (blue) scale:** 900 `#163754`, 800 `#1f5176`, 700 `#2a6e9e`, 600 `#3786bf`, **500 `#4699d0` ★ brand**, 400 `#65adde`, 300 `#8ac3e8`, 200 `#b3d8ef`, 100 `#dcecf8`. Rules: 500 = brand, 600 = hover, 400 = body links, 100–300 = text-on-accent / tints / glows.

**Status:** win `#3ed598` (+ win-2 `#24a978`), loss `#ff5d7a` (+ loss-2 `#c8324d`), draw/pending `#f5b544`, live `#ff3b5c`, info = accent-500.

**Backgrounds:** canvas `#0f1726`, canvas-2 `#0a111e`, **surface (card base) `#141e36`**, surface-2 (raised) `#1a2544`, inset (inputs/wells) `#0c1321`, overlay scrim `rgba(7,11,20,0.72)`.

**Foreground on dark:** `--fg-1 #fff` (1.00), `--fg-2 rgba(255,255,255,0.72)`, `--fg-3 rgba(255,255,255,0.52)`, `--fg-4 rgba(255,255,255,0.32)`, `--fg-accent` = accent-400 `#65adde`.

**Borders:** border-1 `rgba(255,255,255,0.06)` quiet, border-2 `rgba(255,255,255,0.10)` default, border-3 `rgba(255,255,255,0.18)` hover, border-accent `rgba(70,153,208,0.45)` focused.

**Radii:** xs 4, sm 8, md 12, lg 16, xl 20, 2xl 24, 3xl 32, pill 999.

**Spacing (4px base):** s-1 4, s-2 8, s-3 12, s-4 16, s-5 20, s-6 24, s-8 32, s-10 40, s-12 48, s-16 64, s-20 80, s-24 96.

**Shadows:** sm `0 1px 2px rgba(0,0,0,0.35)`, md `0 8px 24px rgba(0,0,0,0.35)`, lg `0 20px 48px rgba(0,0,0,0.45)`, xl `0 32px 80px rgba(0,0,0,0.55)`, glow `0 0 0 1px rgba(70,153,208,0.35), 0 12px 40px rgba(70,153,208,0.25)`, inset-hi `inset 0 1px 0 rgba(255,255,255,0.08)`.

**Gradients:** `--grad-canvas: radial-gradient(120% 80% at 50% 0%, #18233f 0%, #0f1726 55%, #0a111e 100%)`; `--grad-accent: linear-gradient(135deg, #65adde 0%, #4699d0 45%, #2a6e9e 100%)`; `--grad-headline: linear-gradient(180deg,#fff 0%,#fff 40%,#8ac3e8 100%)`; `--grad-headline-alt: linear-gradient(100deg,#fff 0%,#b3d8ef 55%,#4699d0 100%)`; `--grad-win: linear-gradient(135deg,#3ed598 0%,#24a978 100%)`; `--grad-loss: linear-gradient(135deg,#ff5d7a 0%,#c8324d 100%)`; `--grad-glow-accent: radial-gradient(60% 80% at 50% 0%, rgba(70,153,208,0.35) 0%, rgba(70,153,208,0) 70%)`.

**Motion:** ease-standard `cubic-bezier(0.2,0,0,1)`, ease-emphatic `cubic-bezier(0.2,0.8,0.2,1)`, durations fast 120ms / base 200ms / slow 320ms / xslow 520ms.

**Type families:** sans/display = Montserrat, mono = `ui-monospace,'SF Mono'…`. Display sizes (clamp): xl `clamp(56px,8vw,104px)`, lg `clamp(44px,6vw,72px)`, md `clamp(36px,5vw,56px)`. Heading sizes: h1 40 / h2 32 / h3 24 / h4 20 / h5 17 / h6 14. Body: lg 18 / body 15 / sm 13 / caption 12 / micro 11.

---

# ⭐ MATCH / TIP CARD — `components-match-card.html` (KEY PRODUCT ELEMENT)

The most important component. **One unified structure across all three states:** `.tip-card` → `.tip-head` → `.tip-teams` → (score area) → footer (button or banner). Three surface variants map to three lifecycle states.

### Base card `.tip-card`
```css
.tip-card {
  background: #141e36;
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 16px;
  padding: 18px;
  font-family: 'Montserrat', sans-serif;
  box-shadow: 0 8px 24px rgba(0,0,0,0.35);
  display: flex; flex-direction: column; gap: 14px;
  color: #ffffff;
}
```
**Surface variants:**
- `.tip-card.light` → `background:#fff`, `border:1px solid rgba(20,30,54,0.08)`, `box-shadow:0 8px 24px rgba(20,30,54,0.10)`, `color:#141e36`.
- `.tip-card.accent` → `background: linear-gradient(135deg,#65adde 0%,#4699d0 50%,#2a6e9e 100%)`, `border:1px solid rgba(255,255,255,0.18)`, `box-shadow:0 12px 32px rgba(70,153,208,0.30), inset 0 1px 0 rgba(255,255,255,0.22)`, `color:#fff`, `position:relative;overflow:hidden`. Has a `::before` sheen: `radial-gradient(70% 90% at 100% 0%, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 60%)`; direct children `position:relative` to sit above it.

### Header `.tip-head` (round on line 1, date/time on line 2 + status pill)
```html
<div class="tip-head">
  <div class="tip-stage">
    <div class="round">Čtvrtfinále</div>
    <div class="when">23. 4. · 20:15</div>
  </div>
  <span class="pill pill-soon"><span class="dot"></span>BRZY</span>
</div>
```
- `.tip-head`: `flex; align-items:flex-start; justify-content:space-between; gap:10px`.
- `.tip-stage`: vertical, `gap:2px`.
- `.tip-stage .round`: **13px / weight 700**, `letter-spacing:-0.01em` (round name e.g. "Čtvrtfinále", "Semifinále", "Osmifinále").
- `.tip-stage .when`: **11px / weight 300**, `letter-spacing:0.02em`, `opacity:0.62` (date · time, separated by `·`, e.g. "23. 4. · 20:15"). On `.light`: `opacity:1; color:rgba(20,30,54,0.55)`. On `.accent`: `opacity:1; color:rgba(255,255,255,0.85)`.

### Status pills `.pill` (states)
```css
.pill { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:6px;
  font-size:10px; font-weight:700; letter-spacing:0.06em; border:1px solid transparent; flex:none; }
.pill .dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
```
| Pill | Label | bg | border | color | dot? |
|---|---|---|---|---|---|
| `.pill-soon` | BRZY | `rgba(245,181,68,0.14)` | `rgba(245,181,68,0.40)` | `#f5b544` (amber) | yes |
| `.pill-tipped` | TIPOVÁNO | `rgba(62,213,152,0.14)` | `rgba(62,213,152,0.40)` | `#6fe4b5` (green) | yes |
| `.pill-done` | UKONČENO | `rgba(255,255,255,0.18)` | `rgba(255,255,255,0.35)` | `#ffffff` | **no dot** |

### Teams + coin-flags `.tip-teams`
```css
.tip-teams { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:14px; min-height:96px; }
.tip-team { display:flex; flex-direction:column; align-items:center; gap:8px; }
.tip-team .name { font-size:13px; font-weight:700; letter-spacing:-0.01em; text-align:center; }
.tip-vs { font-size:11px; font-weight:500; letter-spacing:0.18em; color:rgba(255,255,255,0.52); text-transform:uppercase; }
.tip-card.light .tip-vs { color:rgba(20,30,54,0.45); }
```
The center column holds `<div class="tip-vs">vs</div>` (uppercase, wide tracking).

**Coin-flag treatment** `.flag` — a 44px circular "coin" with the flag clipped inside:
```css
.flag { width:44px; height:44px; border-radius:50%; overflow:hidden; border:1px solid rgba(255,255,255,0.18); }
.tip-card.light .flag { border-color:rgba(20,30,54,0.12); }
.flag svg { width:100%; height:100%; display:block; }
```
Flags are **inline SVGs** drawn with a `<clipPath id="…"><circle cx=30 cy=30 r=30/></clipPath>` on a `viewBox="0 0 60 60" preserveAspectRatio="xMidYMid slice"`, each clipped group `clip-path="url(#xx)"`. Examples in file: Czech (`cz`: white/red bands + blue triangle `#11457E`), Sweden (`se`: `#006AA7` + `#FECC00` cross), Finland (`fi`: `#003580` cross on white), Canada (`ca`: `#D80621` bands + maple leaf path), Switzerland (`ch`: `#DA291C` + white cross), USA (`us`: `#B22234` stripes + `#3C3B6E` canton). No drop-shadow on flags (comment: "Vlajky bez stínů").

### Score input stepper `.tip-inputs` / `.score-input` (BRZY + TIPOVÁNO states)
```html
<div class="tip-inputs">
  <div class="score-input">2<div class="steppers"><button>▲</button><button>▼</button></div></div>
  <span class="colon">:</span>
  <div class="score-input">2<div class="steppers"><button>▲</button><button>▼</button></div></div>
</div>
```
```css
.score-input { width:64px; height:46px; border-radius:10px;
  background:rgba(12,19,33,0.85); border:1px solid rgba(255,255,255,0.12); color:#fff;
  font-weight:700; font-size:22px; text-align:center; font-variant-numeric:tabular-nums;
  display:flex; align-items:center; justify-content:center; position:relative; }
.tip-card.light .score-input { background:#f4f6fb; border-color:rgba(20,30,54,0.10); color:#141e36; }
.tip-card.accent .score-input { background:rgba(255,255,255,0.14); border-color:rgba(255,255,255,0.30); color:#fff; }
.steppers { position:absolute; right:4px; top:3px; bottom:3px; display:flex; flex-direction:column; justify-content:space-between; gap:2px; }
.steppers button { width:14px; height:16px; background:transparent; border:none; color:rgba(255,255,255,0.52); font-size:9px; cursor:pointer; padding:0; }
.tip-card.light .steppers button { color:rgba(20,30,54,0.45); }
.tip-inputs { display:grid; grid-template-columns:1fr auto 1fr; gap:10px; align-items:center; justify-items:center; }
.colon { font-size:18px; font-weight:700; opacity:0.55; }
```
Two 64×46 number boxes with a stacked ▲/▼ stepper pinned to the right edge, separated by a 18px `:` colon.

### Final score (UKONČENO state) `.final-score`
```html
<div class="final-score">
  <span class="sc">1</span><span class="sep">:</span><span class="sc">4</span>
</div>
```
```css
.final-score { display:flex; align-items:center; justify-content:center; gap:8px; font-variant-numeric:tabular-nums; line-height:1; }
.final-score .sc { font-size:36px; font-weight:900; letter-spacing:-0.03em; }   /* Montserrat Black */
.final-score .sep { font-size:28px; font-weight:900; opacity:0.6; }
```

### Footer buttons (block) `.btn-block`
```css
.btn-block { width:100%; padding:11px; border-radius:12px; font-weight:700; font-size:13px;
  cursor:pointer; border:1px solid transparent; color:#fff; }
.btn-primary-block { background:linear-gradient(135deg,#65adde 0%,#4699d0 45%,#2a6e9e 100%);
  box-shadow:0 8px 20px rgba(70,153,208,0.25), inset 0 1px 0 rgba(255,255,255,0.18); }
.btn-edit-block { background:rgba(255,255,255,0.06); border-color:rgba(255,255,255,0.16); }
.tip-card.light .btn-edit-block { background:#f4f6fb; border-color:rgba(20,30,54,0.10); color:#141e36; }
.tip-card.accent .btn-edit-block { background:rgba(255,255,255,0.18); border-color:rgba(255,255,255,0.35); }
```

### Result banner (UKONČENO state) `.result-banner`
```html
<div class="result-banner">
  <span>✓</span>
  <span>Tvůj tip 1:3 — <b>+8 bodů</b></span>
</div>
```
```css
.result-banner { display:flex; align-items:center; gap:8px; justify-content:center; padding:10px 12px; border-radius:10px;
  background:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.30); color:#fff; font-size:12px; font-weight:500; }
.result-banner b { font-weight:700; }   /* the points emphasis "+8 bodů" */
```
Uses a literal `✓` glyph + copy `"Tvůj tip 1:3 — +8 bodů"` (the user's own tip restated, then points in bold).

### The THREE states in full (as authored)

**STATE 1 — BRZY (upcoming): dark surface, score inputs + "Odeslat tip"** — `<div class="tip-card">`, header round "Čtvrtfinále" / "23. 4. · 20:15", `pill-soon` amber **BRZY**, teams Česko vs Švédsko, `.tip-inputs` steppers `2 : 2`, footer `<button class="btn-block btn-primary-block">Odeslat tip</button>`.

**STATE 2 — TIPOVÁNO (tipped): light surface, score inputs prefilled + "Upravit tip"** — `<div class="tip-card light">`, header "Semifinále" / "25. 4. · 18:00", `pill-tipped` green **TIPOVÁNO**, teams Finsko vs Kanada, `.tip-inputs` showing the submitted tip `2 : 3`, footer `<button class="btn-block btn-edit-block">Upravit tip</button>`.

**STATE 3 — UKONČENO (finished): accent gradient surface, final score + result banner + points** — `<div class="tip-card accent">`, header "Osmifinále" / "20. 4. · 16:00", `pill-done` white **UKONČENO** (no dot), teams Švýcarsko vs USA, `.final-score` `1 : 4` (Black 900, 36px), then `.result-banner` `✓ Tvůj tip 1:3 — +8 bodů`.

> Layout container in file: `display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; align-items:stretch` (three states side by side, equal height).

---

# BUTTONS — `components-buttons.html`

```css
.btn { font-family:'Montserrat'; font-weight:700; font-size:14px; padding:12px 22px; border-radius:12px;
  border:1px solid transparent; cursor:pointer; letter-spacing:0.01em; transition:all 200ms cubic-bezier(0.2,0,0,1); }
.btn-primary { background:linear-gradient(135deg,#65adde 0%,#4699d0 45%,#2a6e9e 100%); color:#fff;
  box-shadow:0 10px 32px rgba(70,153,208,0.25), inset 0 1px 0 rgba(255,255,255,0.18); }
.btn-success { background:linear-gradient(135deg,#5fe0a8 0%,#3ed598 45%,#1f9e6c 100%); color:#fff;
  box-shadow:0 10px 32px rgba(62,213,152,0.25), inset 0 1px 0 rgba(255,255,255,0.25); }
.btn-ghost  { background:transparent; color:rgba(255,255,255,0.72); }
.btn-danger { background:linear-gradient(135deg,#ff5d7a 0%,#c8324d 100%); color:#fff; box-shadow:0 10px 32px rgba(200,50,77,0.25); }
.btn-sm     { padding:8px 14px; font-size:12px; }
.btn:disabled { opacity:0.4; cursor:not-allowed; box-shadow:none; }
```
**Variants shown:** primary (modrý gradient — "Vytvořit soutěž"), success (zelený gradient, positive action — "Pozvat hráče"), ghost (tiché/quiet — "Zrušit"), danger (červený gradient — "Ukončit soutěž"). **Size:** `.btn-sm` (e.g. "Uzamknout tipy", "Export CSV", "+ Přidat zápas"). **State:** `disabled` (e.g. "Odesláno", opacity 0.4, no shadow). Annotation line: "Primary = modrý gradient · Success = zelený gradient (pozitivní akce) · Ghost = tiché · Danger = červený gradient".

Note radius differences across the system: standalone buttons = **12px**; nav CTA = **10px**; block buttons in match card = **12px**.

---

# CARDS — `components-cards.html`

Three surface variants in a `1fr 1fr 1fr` grid, all `padding:18px; border-radius:16px`. (These are the canonical surfaces the match card reuses.)

**1. Dark card** `background:#141e36; border:1px solid rgba(255,255,255,0.10); box-shadow:0 8px 24px rgba(0,0,0,0.35)`. Eyebrow `11px/700`, `letter-spacing:0.16em`, uppercase, color `#8ac3e8` ("Tmavá karta") → title `20px/700 #fff` ("Matchday 14") → body `13px/500 rgba(255,255,255,0.78)`.

**2. Light card** `background:#fff; border:1px solid rgba(20,30,54,0.08); box-shadow:0 8px 24px rgba(20,30,54,0.10)`. Eyebrow color `#4699d0`; title `20px/700 #141e36`; body `13px/500 rgba(20,30,54,0.68)`.

**3. Accent-gradient card** `background:linear-gradient(135deg,#65adde 0%,#4699d0 50%,#2a6e9e 100%); border:1px solid rgba(255,255,255,0.18); box-shadow:0 12px 32px rgba(70,153,208,0.30), inset 0 1px 0 rgba(255,255,255,0.22); position:relative; overflow:hidden`. Inner sheen div `background:radial-gradient(70% 90% at 100% 0%,rgba(255,255,255,0.18) 0%,rgba(255,255,255,0) 60%)`. Content wrapped in `position:relative`. Eyebrow `rgba(255,255,255,0.85)`; title `#fff` ("Tvoje pozice · 4."); body `rgba(255,255,255,0.85)` ("+2 oproti minulému kolu").

---

# FORM INPUTS — `components-inputs.html`

All inputs (inline styles): `width:100%; padding:12px 14px; background:#0c1321; border-radius:10px; color:#fff; font-weight:300; font-size:14px; outline:none`. Labels: `11px / 600`, `letter-spacing:0.14em`, uppercase, `margin-bottom:6px`.

**States:**
- **Default**: `border:1px solid rgba(255,255,255,0.10)`; label color `rgba(255,255,255,0.52)`. (e.g. value "Mistrovství světa 2026").
- **Focus**: `border:1px solid rgba(70,153,208,0.6)` + `box-shadow:0 0 0 3px rgba(70,153,208,0.18)`; label color `var(--accent-400)` (e.g. "Fokus" / "USA vs. Kanada").
- **Error**: `border:1px solid rgba(255,93,122,0.6)`; label color `#ff5d7a`; helper text below `11px #ff5d7a` ("Název soutěže musí mít alespoň 3 znaky.").
- **Select / dropdown**: same well, a flex row with caret `▾` pushed right via `margin-left:auto; color:var(--fg-3)` (value "Fotbal").

---

# BADGES & CHIPS — `components-badges.html`

Base:
```css
.badge { padding:5px 10px; border-radius:6px; display:inline-flex; align-items:center; gap:6px;
  font-family:'Montserrat'; font-size:12px; font-weight:500; }
.badge svg { width:12px; height:12px; flex:none; }
```
Icons are **inline Lucide-style SVGs** (24×24, stroke 2 or 2.5, round caps/joins). Each badge style is inline:

| Badge | Label | bg | border | color | Icon (Lucide equiv.) |
|---|---|---|---|---|---|
| LIVE | ŽIVĚ | `rgba(255,59,92,0.12)` | `rgba(255,59,92,0.35)` | `#ff7a90` | pulsing `.live-dot` 6px `#ff3b5c`, `@keyframes pulseDot` 1.4s (box-shadow ring 0→5px + scale 1→1.15) |
| WIN | VÝHRA · +3 b. | `rgba(62,213,152,0.12)` | `rgba(62,213,152,0.35)` | `#6fe4b5` | checkmark `<polyline points="20 6 9 17 4 12">` (**lucide:check**), stroke 2.5 |
| LOSS | PROHRA · 0 b. | `rgba(255,93,122,0.12)` | `rgba(255,93,122,0.35)` | `#ff8ea1` | X / two crossed lines (**lucide:x**), stroke 2.5 |
| DRAW | REMÍZA · +1 b. | `rgba(180,196,224,0.10)` | `rgba(180,196,224,0.32)` | `#c4cddd` | equals / two h-lines (**lucide:equal**), stroke 2.5 |
| PENDING | ČEKÁ | `rgba(245,181,68,0.12)` | `rgba(245,181,68,0.35)` | `#f5b544` | clock circle+hands (**lucide:clock**), stroke 2 |
| GROUP | SKUPINA A | `rgba(70,153,208,0.14)` | `rgba(70,153,208,0.45)` | `#8ac3e8` | 4-square grid (**lucide:layout-grid**), stroke 2 |
| BRACKET | Osmifinále | `rgba(255,255,255,0.05)` | `rgba(255,255,255,0.12)` | `rgba(255,255,255,0.72)` | network/branch (**lucide:git-branch / network**), stroke 2 |
| ORGANIZER | ORGANIZÁTOR | `linear-gradient(135deg,#65adde,#2a6e9e)` | `rgba(255,255,255,0.14)` | `#fff` + `box-shadow:0 4px 12px rgba(70,153,208,0.3)` | shield (**lucide:shield**), stroke 2 |
| POINTS | 147 b. | `rgba(255,255,255,0.05)` | `rgba(255,255,255,0.12)` | `#fff`, `font-variant-numeric:tabular-nums`, **font-weight:700** | trophy (**lucide:trophy**), stroke 2 |

---

# LEADERBOARD ROW — `components-leaderboard.html`

Container `.lb`: `padding:14px; background:#141e36; border:1px solid rgba(255,255,255,0.10); border-radius:14px`.
Row grid: `.row { grid-template-columns:36px 1fr auto auto; gap:14px; align-items:center; padding:10px 6px; border-bottom:1px solid rgba(255,255,255,0.06); }` (last row no border).

**Rank with medal colors (top 3) + glow text-shadow:**
```css
.rank   { font-variant-numeric:tabular-nums; font-weight:700; font-size:18px; color:#fff; }
.rank-1 { color:#f5cd54; text-shadow:0 0 14px rgba(245,205,84,0.45); }   /* gold */
.rank-2 { color:#cfd6e0; text-shadow:0 0 14px rgba(207,214,224,0.35); }  /* silver */
.rank-3 { color:#d49460; text-shadow:0 0 14px rgba(212,148,96,0.40); }   /* bronze */
```
Rank 4+ uses plain `.rank` (white, no glow).

**Avatar** `.av`: 30×30 circle, `font-weight:700; font-size:12px; color:#fff`, gradient background matched to rank:
- 1st `linear-gradient(135deg,#f5cd54,#b8852a)` (gold)
- 2nd `linear-gradient(135deg,#cfd6e0,#8a93a3)` (silver)
- 3rd `linear-gradient(135deg,#d49460,#8a532a)` (bronze)
- 4th+ `linear-gradient(135deg,#65adde,#2a6e9e)` (accent blue)
Avatar holds 2-letter initials (MK, AP, TL, JV).

**Name / handle hierarchy (bold vs light):** `.name { font-weight:700; font-size:14px; color:#fff }` over `.handle { font-weight:300; font-size:11px; color:rgba(255,255,255,0.55) }` (e.g. "Marek K." / "@marek").

**Points** `.pts { font-variant-numeric:tabular-nums; font-weight:700; font-size:18px; color:#fff }` with `.pts-unit { font-weight:300; font-size:11px; color:rgba(255,255,255,0.55); margin-left:3px }` for the "B" suffix (e.g. `147B`).

**Delta chips** `.delta { padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; font-variant-numeric:tabular-nums }`:
- `.delta-up { background:rgba(62,213,152,0.12); border:1px solid rgba(62,213,152,0.35); color:#6fe4b5 }` → "+12", "+9", "+5"
- `.delta-down { background:rgba(255,93,122,0.12); border:1px solid rgba(255,93,122,0.35); color:#ff8ea1 }` → "−4" (uses true minus `−`)

Sample rows in file: 1 Marek K. @marek 147B +12 · 2 Ana P. @anap 142B +9 · 3 Tomáš L. @tomasl 138B −4 · 4 Jana V. @janav 131B +5.

---

# TOP NAVIGATION

There are **two nav implementations**:

### A) Standalone preview `components-nav.html` (floating glass pill)
```css
.nav { padding:14px 22px; background:rgba(15,23,38,0.72); backdrop-filter:blur(22px) saturate(160%);
  border:1px solid rgba(255,255,255,0.08); border-radius:16px; display:flex; align-items:center; gap:28px; }
```
- **Logo** `.nav-logo` 28px tall, inline **wordmark SVG** `viewBox="755 105 355 140" fill="#ffffff"` (the "Wtips" wordmark; the leading "W" stroke fragment is `fill="#4699d0"` accent, rest white). `aria-label="Wtips"`.
- **Links** `.nav-links a`: `font-size:13px; color:rgba(255,255,255,0.72); font-weight:300` (Light at rest), `padding:6px 0; border-bottom:2px solid transparent`. **Hover** → `color:#fff; font-weight:700` (Bold). **Active** → `color:#fff; font-weight:700; border-bottom-color:#4699d0`. Links: **Soutěže** (active), Zápasy, Žebříček, Výplaty.
- **CTA** `.nav-cta`: `padding:9px 16px; background:linear-gradient(135deg,#65adde 0%,#4699d0 45%,#2a6e9e 100%); border:1px solid rgba(255,255,255,0.14); border-radius:10px; font-weight:600; font-size:13px; box-shadow:0 8px 20px rgba(70,153,208,0.25)` → "Nová soutěž".
- **Avatar** `.nav-avatar`: 34×34 circle, `background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.14)`, holds a **Lucide user icon** (`<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>`, **stroke 1.75** — explicitly commented "Lucide-style user icon, stroke 1.75").

### B) Shared production nav partial `pages/_wtnav.html` (sticky full-bleed header) — canonical site nav
```css
.wtnav { position:sticky; top:0; z-index:50; background:rgba(15,23,38,0.72);
  backdrop-filter:blur(22px) saturate(160%); border-bottom:1px solid rgba(255,255,255,0.08); }
.wtnav .bar { max-width:1280px; margin:0 auto; padding:0 32px; height:72px; display:flex; align-items:center; gap:32px; }
```
- **Brand lockup** `.brand` (link to `index.html`): square `.brand-mark` 32×32, `border-radius:10px`, `background:linear-gradient(135deg,#65adde 0%,#4699d0 45%,#2a6e9e 100%)`, `box-shadow:0 4px 12px rgba(70,153,208,0.35)`, contains glyph **"W"** at `font-weight:900; font-size:15px; color:#fff`. Next to it `.brand-name` "Wtips" at `font-weight:900; font-size:19px; letter-spacing:-0.03em; color:#fff`. (Optional `.chip-neutral` pill: `border-radius:999px; bg rgba(255,255,255,0.05); border rgba(255,255,255,0.12); 10px/600`.)
- **Primary links** `.primary a`: `font-size:13px; font-weight:500; color:rgba(255,255,255,0.72); padding:6px 0; border-bottom:2px solid transparent`. **Hover** → `color:#fff`. **Active** → `color:#fff; font-weight:600; border-bottom-color:#4699d0`. Links: Soutěže (`dashboard-hrac.html`), Zápasy (`#`), **Žebříček (active, `zebricek.html`)**, Výplaty (`#`). (Note: this nav uses weight **500→600** for active, vs the standalone preview's 300→700.)
- **Actions** `.actions` (`margin-left:auto; gap:14px`):
  - **CTA** `.nav-cta`: `padding:9px 16px; border-radius:10px; background:linear-gradient(135deg,#4699d0,#1f5d8e); font-weight:700; font-size:13px; box-shadow:0 2px 0 rgba(31,93,142,0.5), 0 6px 16px rgba(70,153,208,0.28)`. Hover: `transform:translateY(-1px); filter:brightness(1.06)` + bigger shadow. Icon = plus (**lucide:plus**, `<path d="M12 5v14M5 12h14"/>` stroke 2.5, 14×14) + `<span>Vytvořit soutěž</span>` (span hidden < 900px).
  - **Icon button** `.icon-btn`: 36×36, `border-radius:10px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.10); color:rgba(255,255,255,0.72)`. Hover → `bg rgba(255,255,255,0.08); color:#fff`. Holds bell (**lucide:bell**, stroke 1.75, 18×18) — aria-label "Notifikace".
  - **Avatar** `.avatar`: 36×36 circle, `background:linear-gradient(135deg,#ff5d7a,#c8324d)` (loss/pink gradient), `font-weight:700; font-size:13px; color:#fff; border:1px solid rgba(255,255,255,0.18)`, holds initials "MK".
- **Responsive** `@media (max-width:900px)`: bar gap 14, height 60, padding 0 18; primary gap 14 / 12px; CTA label `<span>` hidden, CTA padding 9px 11px.

---

# HERO TREATMENT — `brand-hero.html`

```css
.hero { position:relative; width:100%; height:260px; border-radius:16px; overflow:hidden;
  background:radial-gradient(120% 80% at 50% 0%, #18233f 0%, #0f1726 55%, #0a111e 100%);
  border:1px solid rgba(255,255,255,0.10); }
.hero-glow { position:absolute; inset:0; background:radial-gradient(50% 70% at 70% 30%, rgba(70,153,208,0.35) 0%, rgba(70,153,208,0) 70%); }
```
**Left content** `.hero-text` (left:32px, vertically centered, max-width 360):
- **Eyebrow badge** `.badge.badge-accent`: `padding:4px 10px; border-radius:6px; font-size:11px; font-weight:700; letter-spacing:0.06em; uppercase; bg rgba(70,153,208,0.14); border rgba(70,153,208,0.40); color:#8ac3e8`, with a **trophy Lucide icon** (12×12, stroke 2) + label "MS ve fotbale 2026".
- **Title** `.hero-title`: `font-weight:900; font-size:48px; line-height:0.98; letter-spacing:-0.01em; color:#fff` — "Veďte soutěž.<br>Ovládněte drama." (Montserrat Black headline).

**Floating mini match-card** `.float-match` (right:24, top:28, width:200): same `#141e36` surface, `border-radius:16px`, shadow-md. Compact header (round "Skupina A" 11px/700, when "67. min" 10px/300/0.62 opacity) + a **LIVE pill** `.pill-live` (`bg rgba(255,93,122,0.14); border rgba(255,93,122,0.40); color:#ff8ea1; 9px/700`) with an animated ring dot (`.ring` 6px `#ff5d7a`, `::after` expanding border ring, `@keyframes ring-pulse` 1.4s scale 0.7→2.4 / opacity 1→0). Teams use **28px coin flags** (ARG vs FRA, same clipPath SVG technique), team codes `10px/700`, center `.score` `font-weight:900; font-size:20px; tabular-nums; letter-spacing:-0.02em` showing "2 : 1".

**Floating leaderboard row** `.float-lb` (right:80, bottom:22, width:180): `#141e36`, `border-radius:14px`, shadow-md. Gold rank "1" (`#f5cd54` + glow `0 0 14px rgba(245,205,84,0.45)`), name "Marek K." 11px/700, sub "147 b · 248 hráčů" 10px/300, green delta "+12".

> Establishes the hero pattern: dark radial canvas + offset accent glow + Black headline + glassy floating product cards (live match + leaderboard) layered on the right.

---

# LOGO & MARK — `brand-logo.html`

Two assets, both inline SVG:

**1. Wordmark** (`.wordmark`, max-width 320, height 64) — the full "Wtips" logotype. **Identical SVG to the nav logo**: `viewBox="755 105 355 140" fill="#ffffff"`. First path (the W's leading shape) is `fill="#4699d0"` (accent blue), remaining glyphs white. Source asset cited: `assets/logo-wtips.svg`. **Usage: on dark backgrounds.**

**2. Mark / monogram** (`.mark-frame` 90×90, `border-radius:18px; bg rgba(70,153,208,0.08)`) — the **gradient "W"** alone. `viewBox="-10 -10 180 95"`, single path `fill="url(#mg)"` where `#mg = linearGradient(#65adde → #2a6e9e)` (top-left to bottom-right). Source asset: `assets/logo-mark.svg`. **Usage: avatars, favicons, small lockups.**

Legend copy (verbatim): *"Wordmark (z knihovny `assets/logo-wtips.svg`) na tmavá pozadí · Značka (gradient W z `assets/logo-mark.svg`) pro avatary, favicony, malé lockupy."*

Note: `_wtnav.html` uses a **simplified text version** of the mark — a CSS gradient square with a typeset "W" (font-weight 900), not the SVG — for the sticky header.

---

# TYPOGRAPHY

### Display — `type-display.html`
`.display-md`: Montserrat **900**, `letter-spacing:-0.04em` (very tight), `line-height:1.02`, `font-size:56px`, `#fff`. Used on hero headlines. Sample "Tipovačky světa". (CSS also defines display-xl `clamp(56,8vw,104)` and display-lg `clamp(44,6vw,72)`; `.display-gradient` clips `--grad-headline` to text.)

### Headings — `type-headings.html`
| Class | Size | Weight | Tracking | Extra |
|---|---|---|---|---|
| h1 | 40px | 800 | -0.02em | line-height 1.15 |
| h2 | 32px | 700 | -0.02em | |
| h3 | 24px | 700 | -0.02em | |
| h4 | 20px | 600 | | |
| h5 | 17px | 600 | | |
| h6 | 14px | 600 | 0.08em | UPPERCASE, color rgba(255,255,255,0.72) |

Samples: H1 "Souhrn zápasového dne", H2 "Žebříček", H3 "Skupinová fáze", H4 "Vaše soutěž", H5 "Nastavení soutěže", H6 "Název sekce".

### Body & UI text — `type-body.html`
- `.lead` 18px/400, `rgba(255,255,255,0.72)`, line-height 1.55
- `.body` 15px/400, `rgba(255,255,255,0.72)`
- `.body-sm` 13px/400, `rgba(255,255,255,0.72)`
- `.caption` 12px/400, `rgba(255,255,255,0.52)`, letter-spacing 0.04em
- `.eyebrow` 12px/600, letter-spacing 0.16em, UPPERCASE, color `var(--accent-400)`

### Weights — `type-weights.html`
Full Montserrat ramp 100→900, all rendered on the word "Tipovačka" at 22px / letter-spacing -0.01em: 100 Thin, 200 ExtraLight, 300 Light, 400 Regular, 500 Medium, 600 SemiBold, 700 Bold, 800 ExtraBold, 900 Black.

### Numerics — `type-numerics.html` (all `font-variant-numeric:tabular-nums`)
- `.num-display` (scores): **64px / 900**, `letter-spacing:-0.02em`, line-height 1 — sample "2 – 1", label "Skóre".
- `.num-odds` (decimal odds): **28px / 800**, color `var(--accent-400)` — "1,85", label "Kurz".
- `.num-points` (points): **36px / 900**, with `.unit` (color `--fg-3`, weight 700) — "147 b.", label "Body v soutěži".
- `.num-time` (kickoff): **18px / 600**, color `--fg-2` — "20:00", label "Výkop".

---

# COLOR PREVIEW PAGES (mapping to tokens)

- **`colors-core.html`** — 3 swatches: Primary `#0F1726 (--brand-primary)`, Secondary `#FFFFFF (--brand-white)`, Accent `#4699D0 (--brand-accent)`. Accent swatch carries `box-shadow:0 10px 32px rgba(70,153,208,0.25)`.
- **`colors-navy.html`** — 11-step navy ramp (950→100, values above). **850 `#0f1726` flagged ★ as primary canvas** (blue outline). Note: "900/950 for wash edges. 700–500 for borders & muted chrome."
- **`colors-accent.html`** — 9-step blue ramp (900→100). **500 `#4699d0` flagged ★ brand** (white outline). "500 brand · 600 hover · 400 body links · 100–300 text-on-accent/tints/glows."
- **`colors-status.html`** — 5 status tiles (radius 10): Win `linear-gradient(135deg,#3ed598,#24a978)` `#3ED598`; Loss `linear-gradient(135deg,#ff5d7a,#c8324d)` `#FF5D7A`; Pending `#f5b544`; LIVE `#ff3b5c` with `box-shadow:0 0 0 4px rgba(255,59,92,0.18)` ring; Info `#4699d0`.
- **`colors-foreground.html`** — fg ladder: `--fg-1` 1.00, `--fg-2` 0.72, `--fg-3` 0.52, `--fg-4` 0.32, `--fg-accent #65ADDE`. Demonstrates a card stepping title→body→caption→disabled through the ladder.
- **`colors-gradients.html`** — Canvas radial (`--grad-canvas`), Accent (`--grad-accent`), Headline white→blue (`--grad-headline-alt` `linear-gradient(100deg,#fff,#b3d8ef 55%,#4699d0)`), Ambient accent glow (`--grad-glow-accent`).

---

# SPACING / RADII / BORDERS / SHADOWS PREVIEW PAGES

- **`spacing-radii.html`** — Radii samples: 4, 8, 12, 16, 24, pill(999). Spacing (4px base) bars: s-1 4 / s-2 8 / s-3 12 / s-4 16 / s-6 24 / s-8 32 / s-12 48 (bars colored `var(--accent-500)`).
- **`spacing-borders.html`** — border-1 `rgba(255,255,255,0.06)` quiet, border-2 `0.10` default, border-3 `0.18` hover, border-accent `rgba(70,153,208,0.45)` + `box-shadow:0 0 0 3px rgba(70,153,208,0.14)` focused.
- **`spacing-shadows.html`** — shadow-sm `0 1px 2px rgba(0,0,0,0.35)`, shadow-md `0 8px 24px rgba(0,0,0,0.35)`, shadow-lg `0 20px 48px rgba(0,0,0,0.45)`, shadow-glow `0 0 0 1px rgba(70,153,208,0.35), 0 12px 40px rgba(70,153,208,0.25)` (accent-bordered).

---

# CROSS-CUTTING NOTES FOR IMPLEMENTERS

1. **Two competing accent gradients exist.** The 3-stop product gradient `linear-gradient(135deg,#65adde 0%,#4699d0 45%,#2a6e9e 100%)` (buttons, cards, brand-mark, both navs' CTA in preview) vs `_wtnav.html`'s 2-stop CTA `linear-gradient(135deg,#4699d0,#1f5d8e)` and brand-mark `…45%,#2a6e9e`. Pick one canonical for production.
2. **Surface color `#141e36` is NOT a token** in `colors_and_type.css` (which defines `--bg-surface:#141e36` — actually it IS `--bg-surface`). The match/leaderboard/cards hardcode `#141e36` instead of `var(--bg-surface)`; reconcile when porting to Twig.
3. **Pill radius = 6px** everywhere (badges, status pills) except the neutral chip in `_wtnav` (999px) and avatars/flags (50%).
4. **Tabular numerics** (`font-variant-numeric:tabular-nums`) are mandatory on every number: scores, points, odds, time, ranks, deltas.
5. **Active-nav weight differs** between the two nav files (300→700 in preview vs 500→600 in `_wtnav`). The `_wtnav.html` is the shared/production partial and should win.
6. **Lucide icons referenced** (import these for the Symfony app per CLAUDE.md `ux:icons:import`): `check`, `x`, `equal`, `clock`, `layout-grid` (group), `git-branch`/`network` (bracket), `shield` (organizer), `trophy` (points + hero eyebrow), `user`, `bell`, `plus`. All drawn at stroke 1.75–2.5, round caps/joins.
7. **Flags are bespoke inline SVG coins** (60×60 viewBox, circular clipPath, `preserveAspectRatio="xMidYMid slice"`, 44px in match card / 28px in hero/mini), no shadow, 1px translucent ring border. Countries present: CZ, SE, FI, CA, CH, US, AR, FR.