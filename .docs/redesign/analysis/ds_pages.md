I have now read all files fully. Here is the complete catalog of the page designs.

---

# Wtips Design System — PAGE Design Catalog

Source root: `/Users/janmikes/www/wtips-design-system/project/`. All pages are dark-first, glass-morphism, Montserrat (weights 100–900), electric-blue accent `#4699d0`. Pages load the shared design tokens stylesheet `../colors_and_type.css` (defines `--navy-*`, `--accent-*`, `--status-*`, `--fg-*`, `--glass-*`, `--grad-*`, radii, spacing, shadows, type scale). Marketing pages also load `site.css` (containers, `.btn`, `.pill`, `.eyebrow`, footer). App/auth pages largely inline a local `:root` palette plus the shared `.wtnav` nav block.

---

## SHARED CHROME (used by all pages)

### Top nav — `.wtnav` (two delivery forms)
The exact same sticky glass header appears in two forms:

1. **Hard-coded `<header class="wtnav">`** — landing-bold, prihlaseni, registrace, zebricek, dashboard-hrac, index. Markup (identical everywhere):
```html
<header class="wtnav"><div class="bar">
  <a class="brand" target="_top" href="index.html">
    <div class="brand-mark">W</div><div class="brand-name">Wtips</div></a>
  <nav class="primary">
    <a href="dashboard-hrac.html">Soutěže</a>
    <a href="#">Zápasy</a>
    <a href="zebricek.html">Žebříček</a>
    <a href="#">Výplaty</a></nav>
  <div class="actions">
    <button class="nav-cta"><svg plus/><span>Vytvořit soutěž</span></button>
    <button class="icon-btn" aria-label="Notifikace"><svg bell/></button>
    <div class="avatar">MK</div></div>
</div></header>
```
2. **JS-injected** via `_partials.js` for `Landing.html` (`<div data-partial="header" data-active="">`). This variant differs: nav items are **Přehled / Dashboard / Žebříček / Landing** (not Soutěže/Zápasy/Žebříček/Výplaty), brand has a `brand-chip` reading **"Hráč"**, actions are bell (with red `.dot`) + avatar "MK" (title "Marek Kulhánek") + hamburger `.menu-btn` (mobile). The hard-coded variant has **no** chip and **no** hamburger; instead it has the gradient "Vytvořit soutěž" CTA.

Nav styling (canonical): sticky `top:0; z-index:50`; `background:rgba(15,23,38,0.72)`; `backdrop-filter:blur(22px) saturate(160%)`; bottom border `rgba(255,255,255,0.08)`; bar `max-width:1280px; height:72px; padding:0 32px; gap:32px`. Brand-mark: 32×32, radius 10, gradient `linear-gradient(135deg,#65adde,#4699d0,#2a6e9e)`, white "W" weight 900. Brand-name weight 900, 19px, `-0.03em`. Primary links 13px weight 500, `rgba(255,255,255,0.72)`; `.active` → white, weight 600, 2px bottom-border `#4699d0`. nav-cta gradient `linear-gradient(135deg,#4699d0,#1f5d8e)` with stacked box-shadow. avatar 36×36 circle gradient `#ff5d7a→#c8324d` "MK". Mobile `≤900px`: height 60, gap 14, CTA label hidden.

### Footer — two variants
- **Marketing footer** (`.wtfoot`, injected by `_partials.js` on Landing.html; styled identically in site.css as `.site-footer`): 4-column grid `1.4fr repeat(3,1fr)`, dark `#07101e`, top border. Col 1: 28px W mark + "Wtips" + tagline **"Tipovací soutěže pro firmy, partičky i klubové komunity. Bez sázek, jen pro radost a vychloubání."** Col headers (h5, uppercase, `0.12em`): **Aplikace** (Přehled stránek / Dashboard / Žebříček), **Marketing** (Landing / Vše ostatní), **Společnost** (Kontakt / Obchodní podmínky / Ochrana soukromí). Legal row: **"© 2026 Wtips. Vše hraje, nic se nesází."** / **"Vyrobeno v Praze."**
- **App/auth mini-footer** (`<footer>` inline): single flex row, e.g. zebricek/dashboard: `© 2026 Wtips · …` left, link list right. Dashboard's: **"© 2026 Wtips · Tipuj s kámošema"** / **"Pravidla · Soukromí · Podpora"**.

### Shared component vocabulary (from site.css / colors_and_type.css)
- **Pills** `.pill` + `.pill-accent / .pill-success / .pill-warn / .pill-neutral / .pill-live`. `.pill-live .ring` = pulsing dot (`ring-pulse` keyframe).
- **Buttons** `.btn` + `.btn-primary` (blue gradient), `.btn-success` (green gradient), `.btn-ghost`, `.btn-link`, sizes `.btn-sm/.btn-lg`, plus landing-only `.btn-light` (white) / `.btn-clear` (translucent).
- **Surfaces** `.surface`, `.surface-light`, `.surface-accent`, `.glass` (translucent + blur 22px).
- **Eyebrow** `.eyebrow` — two forms: marketing chip-style (boxed, blue tint) in site.css; app pages redefine `.eyebrow` as plain uppercase blue text 11px `0.14em`.
- Status colors: win `#3ed598`, loss `#ff5d7a`, draw/pending `#f5b544`, live `#ff3b5c`/`#ff5d7a`, gold `#f5b544`, silver `#cfd6e1`, bronze `#c08458`.

---

## 1. PAGE INDEX / CHOOSER
**File:** `pages/index.html` · **Maps to:** internal design-system rozcestník (not a product screen, but defines the page taxonomy + terminology)

**Top → bottom:**
1. **wtnav** (hard-coded, brand link has stray `class="active"`).
2. **Hero** (centered): eyebrow **"Wtips · Stránky"**, h-display **"Kompletní web"**, lead **"Veřejné stránky, marketing, auth a aplikační obrazovky — všechny propojené, plně responzivní, hotové k procházení."**
3. **Card grid** (`auto-fill minmax(280px,1fr)`), grouped by `.group-title` section labels. Each `.page-card` = scaled-down live `<iframe>` preview (16:10, `transform:scale(0.31)`) + meta (optional `.tag`, `.name`, `.desc`). Hover lifts 3px, blue border glow.

**Groups & cards (establishes the full screen inventory + terminology):**
- **Landing — 3 koncepty**: *Varianta A* **Bold sport** ("Velký headline, full-bleed live skóre, dramatický hero. Pro hráče a partičky."), *Varianta B* **Product showcase** ("Side-by-side hero s mockupem aplikace…"), *Varianta C* **Editorial** ("Magazínový layout…").
- **Marketing**: **Funkce** ("Detail toho, co Wtips umí."), **Ceník** ("3 plány, FAQ k cenám."), **Pro firmy** ("B2B narativ, use cases, kontakt."), **FAQ** ("Časté otázky, akordeon.").
- **Účet**: **Přihlášení** ("Email, OAuth, «zapomenuté heslo»."), **Registrace** ("Vytvoření účtu hráče i organizátora.").
- **Aplikace**: **Dashboard hráče** ("Soutěže, blížící se zápasy, výsledky."), **Detail soutěže** ("Žebříček, matchday, pravidla."), **Tipovací lístek** ("Zadání tipu na jeden zápas."), **Žebříček** ("Plný leaderboard soutěže."), **Profil** ("Historie tipů, statistiky.").

Note: index links point to `landing-bold.html`, `login.html`, `signup.html`, `dashboard.html`, `leaderboard.html` etc. — but the **actual delivered files** are `prihlaseni.html`, `registrace.html`, `dashboard-hrac.html`, `zebricek.html`. Several iframes (landing-product, features, pricing, pool-detail, bet-slip, profile…) reference files not in this catalog.

---

## 2. LANDING (Bold sport)
**Files:** `pages/landing-bold.html` AND root `Landing.html` (functionally identical hero/sections; differences below) · **Maps to:** public marketing landing page

`Landing.html` = the canonical version: it inlines the full `colors_and_type.css` + `site.css` + the hero/section CSS, uses `data-partial` header+footer (so marketing nav + wtfoot), and loads `_partials.js`. `landing-bold.html` = the same body but with a hard-coded `.wtnav` header and a `data-partial="footer"` div (no `_partials.js` include in the snapshot, so footer placeholder stays). Treat `Landing.html` as source of truth.

**Sections top → bottom:**

### A. HERO (`.hero`, 2-col grid `1.15fr / 0.95fr`)
Background: layered radial blue glows + `linear-gradient(180deg,#07101e→#0a111e)` + faint 80px grid masked to an ellipse.
- **Left column:**
  - eyebrow **"Tipovací soutěže pro partičky"**
  - `.hero-headline` (clamp 48–96px, weight 900, line-height 0.95): **"Tipuj, `<span accent>`vyhraj`</span>` a `<span strike>`pak to omlátíš`</span>` kámošům o hlavu."** — "vyhraj" has blue gradient text-fill; "pak to omlátíš" has a red strikethrough bar (`#ff5d7a→#c8324d`, rotated −3°).
  - lead: **"Wtips je tipovačka pro firmy, party a kámoše ze sportbaru. Žádné sázky, žádné kurzové fígle — jen vy, váš turnaj a kdo to nakonec vystihl nejlíp."**
  - CTA row: `.btn-primary.btn-lg` **"Vytvořit soutěž zdarma"** → signup.html; `.btn-ghost.btn-lg` **"Jak to funguje"** → features.html.
  - `.reassure` row (green ✓): **"Bez sázek a peněz"**, **"Hotovo za 2 minuty"**, **"5 hráčů zdarma navždy"**.
- **Right column** `.hero-live` (glow halo behind):
  - `.hero-card` glass panel. Head: `.pill-live` **"Živě · 67'"** + ctx **"Skupina A · MD3"**.
  - `.match-row` (`1fr auto 1fr`): **Argentina** (flag "ARG", sub "V2 R0 P0 · 6 b") — score **2–1** (56px) — **Francie** (flag "FRA", sub "V1 R1 P0 · 4 b"). Flags are CSS-gradient squares with 3-letter codes.
  - `.picks` block: label **"Tipy 248 hráčů" / "Před začátkem"**, then three bars: **Výhra Argentiny 58 %** (blue gradient glow bar), **Remíza 22 %** (`#f5b544`), **Výhra Francie 20 %** (`#ff5d7a`).
  - Three floating glass mini-cards (`.float`, hidden on mobile): TL avatar "MK" green **"+12 b / Marek vystihl skóre"**; TR `.pill-success` **"1. místo"** + **"147 / 248 hráčů"**; BL avatar "AP" red **"+9 b / Ana trefila výsledek"**.

### B. HOW IT WORKS (`.steps`, centered head + 3-col grid)
- eyebrow **"Jak to funguje"**, h1 **"Tři kroky a máte party tipovačku"**, lead **"Žádné stahování, žádné registrace pro hráče přes půl hodiny. Pošlete jim odkaz a tipuje se."**
- Three `.step` cards (numbered `.num`, title, desc, `.visual` mini-mock):
  - **01 Vytvořte soutěž** — "Vyberte turnaj — MS, NHL, Liga mistrů, Premier League. Wtips automaticky natáhne rozpis zápasů a soupisky." Visual: label "Dostupné turnaje" + `.pill-accent` chips **MS 2026 / EPL / NHL / UCL / NBA / Euro 2028**.
  - **02 Pozvěte hráče** — "Pošlete odkaz na Slack, Teams, do skupinového chatu. Hráč klikne, tipuje — účet vytvoří jedním klikem." Visual: fake URL pill **"wtips.cz/firemni-ms-2026"** + uppercase **"Kopírovat"** chip.
  - **03 Sledujte žebříček** — "Zápasy se aktualizují živě, body padají automaticky. Wtips ukáže, kdo vede, kdo se posunul, kdo to zase totálně netrefil." Visual: 3-row mini leaderboard (1 Marek K. `+12` 147; 2 Ana P. `+9` 142; 3 Tomáš L. `−4` 138).

### C. FEATURES (`.features`, centered head + 3-col grid of `.feat`)
- eyebrow **"Proč Wtips"**, h1 **"Postaveno na kámošské rivalitě"**, lead **"Tipovačku jsme dělali primárně pro sebe. Pak ji chtěli kolegové, pak kámošovo IT oddělení. Tady je proč."**
- Six feature cards (icon in tinted box + title + desc):
  1. **Živé skóre, živé body** — "Body padají hráčům na účet ve chvíli, kdy padá gól. Žádné »zítra to dopočítáme«. Žebříček se hýbe."
  2. **5 hráčů zdarma** — "Free plán bez časáku. Pro firmy, klub nebo větší partu — placené plány od 99 Kč měsíčně."
  3. **Bez sázek, bez stresu** — "Wtips nesází ani neoperuje s penězi. Co se vyhraje, je interní záležitost vás a vašich kolegů."
  4. **Trash-talk feed** — "Komentáře u zápasu, reakce, gif podpora. Mocking je polovina zábavy — my to nezakazujeme, my to podporujeme."
  5. **Vlastní bodování** — "Standardní 3/1/0, pavouk s váhou kola, nebo si nastavte body za přesné skóre, střelce, počet karet."
  6. **Web i mobil** — "Funguje v prohlížeči i v telefonu. Žádná appka v App Store, žádná žádost o instalaci přes IT."

### D. SHOWCASE (`.showcase`)
- eyebrow **"Ukázka aplikace"**, h1 **"Takhle to vypadá v ostrém provozu"**, lead **"Detail soutěže s živým zápasem, žebříčkem a rozpisem. Klikněte si dovnitř."**
- `.showcase-frame` = clickable 16:10 `<iframe>` of `ui_kits/organizer-webapp/index.html` (path differs per file).
- `.legend` stats: **248** aktivních hráčů v ukázce · **14** matchdayů sledovaných živě · **3** bodovací modely.

### E. FINAL CTA (`.cta-final` → `.cta-card`, blue gradient panel with grid texture)
- `.pill-neutral` **"Začněte zdarma"**, h2 (clamp 40–64px) **"MS 2026 začíná za chvíli. Kdo to letos vystihne?"**, p **"Vytvořte svůj turnaj, pošlete kolegům odkaz a do hodiny máte celé patro v napětí. První soutěž do 5 hráčů je na nás navždy."**
- Actions: `.btn-light.btn-lg` **"Vytvořit soutěž"** → signup; `.btn-clear.btn-lg` **"Co všechno Wtips umí →"** → features.

### F. FOOTER — marketing `.wtfoot` (Landing.html) / placeholder (landing-bold).

**Responsive:** `≤980px` hero collapses to 1 col, floats hidden, steps/features → 1 col, CTA padding shrinks.

---

## 3. LOGIN (Přihlášení)
**File:** `pages/prihlaseni.html` · **Maps to:** login screen

Page background: navy + two radial glows + a **fixed stadium photo** (`unsplash photo-1522778119026`, opacity 0.28, grayscale→sepia→hue-rotate to blue) + dark vignette overlays. Layout `.auth-wrap` = 2-col grid `1fr 1fr`, gap 64, min-height `100vh−72px`.

### LEFT — `.auth-visual` (value prop)
1. **PIN join card** (`.pin-card`) — eyebrow **"Připojit se k soutěži"**, then 8 single-char `.pin-inputs` boxes (alnum, auto-uppercase, auto-advance, paste-spread; separator "—" after 4th) + `.pin-btn` **"Připojit se"** (disabled until 8 chars; on submit → `dashboard-hrac.html?pin=…`). **Establishes the 8-character competition PIN as the join mechanism.**
2. `.live-pill` (red, pulsing) **"247 hráčů tipuje právě teď"**.
3. eyebrow **"Vítej zpátky · pokračování v soutěži"**.
4. h1 (64px) **"Tipy `<span accent>`nečekají.`</span>`"** (blue gradient on "nečekají.").
5. lead **"Přihlas se a podívej se, jak se daří tvojí partičce v Euru, klubové lize i kanclové soutěži."**
6. `.quick-stats` row (icon + value + label): **12 400+ Hráčů** · **340 Aktivních soutěží** · **98 % Doporučí dál**.
(`.mini-match` styles exist but no mini-match markup is present in login; it's used styling-wise in registrace too and hidden `≤1000px`.)

### RIGHT — `.auth-card` (glass form, max 480px, right-aligned)
1. eyebrow **"Přihlášení"**, h2 **"Pojď tipovat."**, sub **"Zadej e-mail a heslo, nebo se přihlas přes Google či Apple. Bez sázek, bez peněz — jen pro radost."**
2. `.social-grid` (2-col): **Google** + **Apple** OAuth buttons (full color G logo, white Apple logo).
3. `.divider-or` **"nebo e-mailem"**.
4. `.form-grid` (submit → dashboard-hrac.html):
   - Field **E-mail** (mail icon, placeholder `marek@firma.cz`, type email).
   - Field **Heslo** (lock icon, placeholder `••••••••`, min 6, eye toggle `.icon-r`).
   - `.row-between`: checkbox **"Pamatovat si mě"** (checked) + link **"Zapomenuté heslo?"**.
   - `.submit` gradient button **"Přihlásit se"** + arrow icon.
5. `.auth-foot`: **"Nemáš ještě účet? `<a>`Vytvoř si ho zdarma`</a>`"** → registrace.html.

Field styling: label uppercase 10px `0.1em`; input `rgba(0,0,0,0.22)`, left icon at 42px padding, focus blue ring.

**Footer (inline):** **"© 2026 Wtips · Zpět na rozcestník"** / Dashboard · Žebříček · Landing links.

**Responsive:** `≤1000px` → single column, card centered, h1 44px, mini-match hidden. `≤540px` → social buttons stack 1-col, h2 30px.

---

## 4. REGISTRATION (Registrace)
**File:** `pages/registrace.html` · **Maps to:** create-account screen

Same background system and palette as login. Layout differs: `.auth-wrap` is a centered **column** (`flex-direction:column; align-items:center`); inner `.auth-stack` is 80% width, max 980px. The PIN join card sits **above** the form (full width), and the `.auth-card` here has `max-width:none` (form spans the stack).

### Top — PIN join card
Identical `.pin-card` as login: eyebrow **"Připojit se k soutěži"** + 8-box PIN + **"Připojit se"** button (same JS).

### Form card — `.auth-card`
1. eyebrow **"Registrace"**, h2 **"Vytvoř si účet."**, sub **"Připoj se k Tipovačce zdarma. Žádné sázky, žádné peníze — jen radost z tipování s kámošema."**  *(Note: copy uses capital "Tipovačce" — brand-adjacent term.)*
2. `.social-grid`: **Google** + **Apple** sign-up buttons.
3. `.divider-or` **"nebo e-mailem"**.
4. `.form-grid` with paired `.field-row` (2-col) layout — fields each have required `*` (`.req`, red):
   - Row 1: **E-mailová adresa \*** (mail icon, placeholder `vas@email.cz`) | **Přezdívka \*** (user+plus icon, placeholder `vase_prezdivka`, 3–30 chars, pattern `[A-Za-z0-9_.\-]+`, `.hint` **"3–30 znaků, písmena, čísla, _ . -"**).
   - Row 2: **Jméno \*** (placeholder `Jan`) | **Příjmení \*** (placeholder `Novák`).
   - Row 3: **Heslo \*** (lock icon, placeholder `Zadejte heslo`, min 6, eye toggle) | **Heslo znovu \*** (placeholder `Zopakujte heslo`).
   - `.check.terms`: required checkbox **"Souhlasím se `<a>`zpracováním osobních údajů`</a>` a `<a>`podmínkami`</a>`."**
   - `.submit` **"Vytvořit účet"** + arrow.
5. `.auth-foot`: **"Už máš účet? `<a>`Přihlas se`</a>`"** → prihlaseni.html.

This page establishes the **registration data model**: email, přezdívka (username/handle), jméno, příjmení, heslo + confirm, GDPR consent.

**Footer:** same inline footer as login.

**Responsive:** `≤1000px` single column; `≤540px` social buttons stack, h2 30px.

---

## 5. LEADERBOARD (Žebříček)
**File:** `pages/zebricek.html` · **Maps to:** full competition leaderboard

Background: navy + gold-tinted top-right glow + blue top-left glow (gold theme signals "podium"). `.app` container 1280px. Hard-coded wtnav with **Žebříček** active.

**Sections top → bottom:**

### A. Page head (`.page-head`)
- eyebrow **"Soutěžní žebříček · v reálném čase"**.
- `.head-row`: h1 (64px) **"Žebříček"** + `.head-meta` (4 metrics): **Hráčů 42** · **Odehráno 38 / 64** · **Kolo Osmifinále** · **Aktualizace ● Live** (green).
- `.pool-bar` competition switcher (540px): icon "FM" + label **"Soutěž" / "Firemní MS 2026"** + caret, with a transparent `<select>` overlay listing **Firemní MS 2026 · 42 hráčů**, **Kámoši NHL Playoff · 8 hráčů**, **Liga Mistrů 25/26 · 24 hráčů**.

### B. "You" strip (`.you-strip`, above podium)
Blue glass band: **Tvoje pozice 7. / 42** | divider | **Body 147** · **Změna ▲ 3** (green) | divider | **Do top 5 +18 b** · **Do top 3 +22 b** | right button **"Tipnout další zápas →"**.

### C. Podium (`.podium-wrap` → `.podium`, 3-col `1fr 1.2fr 1fr`, gold ambient glow)
- `.podium-title` **"Top 3 · stav po 38. zápase"**.
- Three `.pod` cards (2nd silver / 1st gold raised+enlarged / 3rd bronze), each with: numbered medal, big initials avatar, name, @handle, big points, "bodů" label, and `.pod-extras` micro-stats (**Přesné / Úspěšnost / Streak**):
  - **2 Petra Nováková** @petra.n — **175** — Přesné 11 / Úspěšnost 28,9 % / Streak 🔥 5.
  - **1 Jakub Kratochvíl** @jakub.k · **"vede 6 kol v řadě"** — **182** · "bodů · 1. místo" — Přesné 13 / 34,2 % / 🔥 7.
  - **3 Tomáš Svoboda** @tom.s — **169** — Přesné 10 / 26,3 % / 🔥 3.

### D. Toolbar (`.lb-toolbar`)
- `.lb-search` input **"Najít hráče v žebříčku…"** (live JS filter on name/handle, hides gap rows when searching).
- `.seg` segmented control **Celkem** (active) / **Poslední kolo** / **Týden** / **Měsíc** (JS toggles active class only).
- right `.sort-btn` **"Seřadit: Body"** + caret.

### E. Table (`.lb-table`)
- `.lb-thead` columns (`60px 36px minmax(220px,1.6fr) 100px 110px 90px 90px 110px`): **Pozice · Δ · Hráč · Body · Úspěšnost · Přesné · Trefa · Streak**.
- `.lb-tr` rows. Pos colored gold/silver/bronze for top 3 (`.lb-pos.gold/.silver/.bronze`). `.lb-delta` up (green ▲n) / down (red ▼n) / flat (●). `.lb-player` = avatar (gradient by player) + name + @handle. `.lb-pts` big number. `.lb-acc` = % + thin progress bar. `.lb-exact` (green) / `.lb-partial` (gold, header "Trefa"). `.lb-streak` hot (🔥 n) / cold (—).
- Rows present: 1 Jakub Kratochvíl 182 (34,2%, 13/9, 🔥7) … through 12, then `.gap-row` **"… pozice 13–24 …"**, rows 25–27, `.gap-row` **"… pozice 28–40 …"**, rows 41–42.
- **YOU row** `.lb-tr.me` = #7 **Marek Konečný** @marek.k 147 (23,7%, 9/8, 🔥4) — left blue border, blue gradient bg, and a **"TY"** badge appended after the name via CSS `::after`.
- `.lb-footer`: info **"Zobrazeno 15 z 42 hráčů · Poslední aktualizace před 12 s"** + page buttons **← Předchozí** (disabled) / **Zobrazit celý žebříček** (primary) / **Další →**.

### F. Footer (inline): **"© 2026 Wtips · Zpět na rozcestník"** / Dashboard · Detail soutěže · Profil.

**Responsive:** `≤1100px` h1 42px; podium → single col (1st un-raised); thead hidden; each `.lb-tr` re-flows to a 3-col grid-template-areas layout (`pos/player/pts`, `delta/acc`, `exact/partial/streak`).

**Terminology captured:** Žebříček, Pozice, Δ (změna), Body, Úspěšnost, Přesné (exact tip), Trefa (partial hit), Streak, Kolo (round, e.g. "Osmifinále"), Odehráno X/Y, "stav po N. zápase".

---

## 6. PLAYER DASHBOARD (Dashboard hráče)
**File:** `pages/dashboard-hrac.html` · **Maps to:** logged-in player home / per-competition dashboard

Background: navy + dual blue radials. `.app` 1280px. Hard-coded wtnav with **Soutěže** active. This is the richest page; it is **data-driven** — a `POOLS` JS object (firemni / nhl / ucl) re-renders stats/tips/leaderboard/results when the pool `<select>` changes via `applyPool()`. Flags are SVG, injected by `data-flag` code (cze/ger/esp/eng/fra/por/ita/bel built-in; unknown codes → blue `.flag.club` chip with letters).

**Sections top → bottom:**

### A. Hero (`.hero`, grid `1fr 380px`)
- Left: small label **"Dashboard vybrané soutěže"**; `.pool-switcher` (caret + transparent select) showing **"Firemní MS 2026"** (options: Firemní MS 2026 · 42 hráčů / Kámoši NHL Playoff · 8 hráčů / Liga Mistrů 25/26 · 24 hráčů); h1 (64px) **"Ahoj, Marku."**; lead **"Dnes tě čekají `<strong>`2 zápasy k tipnutí`</strong>` a 1 už běží naživo. Drž se v top 10 a posuneš se výš."**
- Right `.hero-rank` (blue glass card): label **"Tvoje pozice"**, pool name, big **"7. / 42"**, meta row **Body 147** · **Změna ↑ 3** (green) · **Do top 5 +18**.

### B. Stats (`.stats`, 4-col)
Four `.stat` cards: **Zbývá vám natipovat 2** ("Uzávěrka za 1 h 36 min") · **Dnes se hraje 1** (red, "CZE – GER · 67. min") · **Přesné tipy 9 / 38** ("23,7 % úspěšnost") · **Streak 🔥 4** ("Tipy v řadě se ziskem").

### C. "Tvé zápasy dnes" (`.section`)
- `.match-filter` bar: `.mf-chip` tabs **Vše 6** (active) / **Live 1** / **Dnes 2** / **Tipovatelné 3** / **Ukončené 2**; right `.mf-tourney` **"SOUTĚŽ" + "Všechny soutěže"** dropdown button.
- `.tip-list` of `.tip-card` rows (grid `90px auto 1.2fr 110px 1.2fr 110px 130px`). Variants by left border: `.live` (red), `.tipped` (green), default. Each card composes:
  - `.when` time + `.league` (e.g. "Skupina C").
  - status `.pill`: `.pill.live` **"LIVE 67'"** (pulsing dot) / `.pill.soon` **"Uzávěrka 19:30"** / `.pill.tip` **"Tipováno"**.
  - `.team.home` (right-aligned, name + role "Domácí") + SVG flag; `.score-zone` (live score `1 – 1`, or `.upcoming` showing kickoff time e.g. `20:30`); `.team` away (flag + name + role "Hosté").
  - `.my-tip` box: `.set` shows **"Můj tip 2 : 1"**; `.empty` (dashed gold) shows **"Můj tip + Tipnout"**.
  - `.row-actions` → `.detail-btn` **"Detail zápasu"** + arrow.
  - **Premium distribution teaser** `.dist.premium-teaser` (spans full width): gold **PRÉMIUM** badge (star icon) + **"Distribuce tipů"**, center lock pill **"Uvidíš, jak tipuje 248 hráčů"**, right CTA **"Odemknout →"**, striped placeholder bar. On click → reveals real 1/X/2 distribution (`data-pt-real` JSON like `{"k1":32,"kx":28,"k2":40,"players":248}`), swaps to an **"✓ Prémium"** unlocked badge + real `.dist-bar` (blue/gold/red segments), and dispatches `wtips:open-premium`. **This establishes the premium-gating of tip distribution.**
  - Three static cards shown (live CZE–GER 1–1 tip 2:1; soon ESP–ENG; tipped FRA–POR tip 2:1); JS can render more from POOLS.
- `.show-all-btn` **"Zobrazit všechny zápasy soutěže →"**.

### D. Two columns (`.grid-2`, `1.5fr 1fr`)
- **Left — "Žebříček · Firemní MS 2026"** (`.lb-list` mini leaderboard inside a `.card`): compact rows (grid `40px 1fr 70px 60px`) = pos (gold/silver/bronze for top 3, gold has glow) + avatar + name + @handle + points + streak. Rows 1–8 with `.lb-row.me` highlighting #7 **"Marek Konečný · Ty"** 147 🔥4. `.show-all-btn` **"Celý žebříček →"**.
- **Right — "Tvé soutěže"** (`.pools-list`): `.pool-card` items (first `.primary` blue):
  - **Firemní MS 2026** — "42 hráčů · MS ve fotbale 2026" — rank **7. / 42**, **147 b**, progress 84%, status `.badge` **"Live"** + "1 zápas běží".
  - **Kámoši NHL Playoff** — "8 hráčů · NHL 2026" — **2. / 8**, **68 b**, green bar 78%, "Další zápas zítra ve 20:00".
  - **Liga Mistrů 25/26** — "24 hráčů · UCL 25/26" — **12. / 24**, **93 b**, bar 42%, "Pauza · pokračuje 18. září".
  - `.btn-ghost` full-width **"+ Přidat se do soutěže"**.
  - `.show-all-btn` **"Všechny soutěže →"**.

### E. "Tvé poslední výsledky" (`.results-list` in a `.card`)
Rows (grid `60px 1fr 90px 80px 70px`): when · match (small flags + "Home – Away") · `.result-score` · `.result-tip` (exact green / partial gold / miss) · `.result-pts` (win green / miss gray). Data: Itálie–Belgie 2–1 tip 2:1 **+5 b** (exact); Francie–Portugalsko 3–2 tip 2:1 **+1 b** (partial); Španělsko–Anglie 0–0 tip 1:1 **0 b** (miss); Německo–Itálie 2–2 tip 1:1 **+3 b** (partial); Portugalsko–Belgie 1–0 tip 1:0 **+5 b** (exact). `.show-all-btn` **"Historie →"**.

### F. Footer (inline): **"© 2026 Wtips · Tipuj s kámošema"** / **"Pravidla · Soukromí · Podpora"**.

**Scoring model captured** (from results data): **exact** tip = +5 b, **partial** (right tendency/diff) = +1 to +3 b, **miss** = 0 b. NHL/UCL pools reuse the same +5/+1 pattern.

**Responsive:** `≤1100px` hero → 1 col, `.grid-2` → 1 col, stats → 2 col, `.tip-card` collapses columns and hides `.pill`.

---

## CROSS-PAGE TERMINOLOGY GLOSSARY (canonical Czech copy)
- **Soutěž / pool** — a competition (a "tipovačka"); joined by 8-char **PIN**. Examples: "Firemní MS 2026", "Kámoši NHL Playoff", "Liga Mistrů 25/26".
- **Hráč** — player. **Přezdívka / @handle** — username (e.g. `@marek.k`).
- **Tip / Tipnout / Tipováno / Tipovatelné** — a prediction / to predict / predicted / open-for-prediction. **Můj tip** = "2 : 1".
- **Uzávěrka** — prediction deadline.
- **Body (b)** — points. **Přesné** = exact-score tip. **Trefa / partial** = partial hit. **Úspěšnost** = accuracy %. **Streak** (🔥 n) — consecutive scoring tips.
- **Žebříček** — leaderboard. **Pozice**, **Změna / Δ** (▲/▼), **Kolo** (round: "Skupina", "Osmifinále"), **Odehráno**.
- **Distribuce tipů** — tip distribution (1/X/2), a **PRÉMIUM** gated feature ("Odemknout", "Uvidíš, jak tipuje 248 hráčů").
- Nav labels: **Soutěže · Zápasy · Žebříček · Výplaty** (hard-coded nav) vs **Přehled · Dashboard · Žebříček · Landing** (`_partials.js`). Brand chip **"Hráč"**. Primary CTA **"Vytvořit soutěž"**.
- Tagline: **"Bez sázek, jen pro radost a vychloubání."** / Legal: **"Vše hraje, nic se nesází."** / **"Vyrobeno v Praze."**

## KEY IMPLEMENTATION NOTES FOR A TWIG+TAILWIND REBUILD
- Two distinct **eyebrow** styles coexist (boxed marketing chip in site.css; plain uppercase blue text in app pages) — pick per surface.
- The nav, footer, pin-card, mini-match, quick-stats CSS is **duplicated inline** across auth/app pages (no shared CSS import there except the implicit token vars some expect). A real build should extract `.wtnav`, `.wtfoot`, pill/btn/field partials.
- Auth pages depend on `colors_and_type.css` vars (`--accent-*`, `--fg-*`) but **also inline their own** `:root` (`--navy-*`, `--accent`, `--accent-2`, `--gold/silver/bronze`, `--green/red`, `--line`, `--glass`) — these two palettes overlap but are not identical (e.g. auth `--navy-900:#0a1124` vs token `--navy-900:#0a111e`).
- Dashboard's flags are inline SVG keyed by 3-letter code; leaderboard/podium avatars are initials on per-player gradients (gold `#f5b544→#c08458`, silver `#cfd6e1→#7d8597`, etc.).
- Filenames are inconsistent: index.html links to `login/signup/dashboard/leaderboard.html`, but real files are `prihlaseni/registrace/dashboard-hrac/zebricek.html`. The auth pages link to `dashboard-hrac.html` / `zebricek.html` directly.