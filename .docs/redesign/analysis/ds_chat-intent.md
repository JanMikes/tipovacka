I have now read the entire transcript (lines 1-5491). I have all the information needed to produce the report.

---

# INTENT & DECISIONS REPORT — `wtips-design-system/chats/chat1.md`

This is a design-conversation transcript (Czech, started 2026-04-22) between a user and an AI design assistant building the **Wtips** ("W" + "tips" — Winning tips) design system and HTML page prototype. Wtips is a **betting/tipping tournament platform** for organizing sports prediction competitions (think office/friends World Cup pools). Two surfaces: an organizer SPA (`ui_kits/organizer-webapp/index.html`, React + Babel inline) and a set of static pages under `pages/`.

Brand DNA established early (lines 29, 1568–1574): dark navy canvas `#0f1726`, accent blue `#4699d0` (light accent `#8ac3e8`, dark-card `#141e36`), white text, **Montserrat** type, glassmorphism (backdrop-filter blur, glass cards), rounded corners (16px cards / 999px chips / 12px buttons), Montserrat Black 900 display with tight tracking `-0.04em`, tournament energy (live pulse, gradient hero text). Inspiration was a dark B2B SaaS "GoPidge" screenshot.

---

## 1. CHRONOLOGICAL LIST OF USER DECISIONS / REQUESTS

### Phase A — Foundation & localization (lines 1–520)
- **Initial build**: Assistant created colors/type tokens, README, preview cards (colors, typography, components: buttons/cards/glass/inputs/badges), and the organizer UI kit. Flagged 3 caveats: `Wtips.ai` logo missing (used placeholder wordmark `assets/logo-wtips.svg` + square mark), no real codebase, no team crests (used colored letter-badge `TeamFlag`).
- **User uploaded real `Wtips.svg`** (line 208). SVG contained two wordmark variants (dark `st0/st1` at x=0–609, light at x=629+); assistant cropped the **dark/left** version. Dot-over-"i" path near (144.8, 129.4).
- **"This is what the scorecard looks like"** (line 297): User shared the **"Tipovací karta — klíčový prvek"** (tipping card key element). Three states locked in: **Brzy** (Upcoming — score input + "Odeslat tip"), **Tipováno** (Tipped — "Upravit tip"), **Ukončeno** (Finished — result + points earned). Layout: stage label + date top, status pill top-right, flag–VS–flag row, team names, score inputs OR final score + result banner, action button. Ported to dark/glass.
- **"Do you speak Czech?" → "Ano vytvoř vše v češtině"** (lines 319–337): User directed the **entire design system be rewritten to Czech**. Assistant translated README, all preview cards, UI kit, SKILL.md. Established Czech conventions (see Glossary §3).

### Phase B — Font fix saga (recurring, lines 491–520, 1290–1520)
- **"Celý design system nezobrazuje nahraný font, udělej revizi"** / **"V celém systému komponent oprav font na Montserrat, již jsem jej nahrával"** (lines 499, 515): User repeatedly reported Montserrat not rendering. Root causes diagnosed: (1) `_base.css` 404'd (files starting with `_` treated as private → renamed to `base.css`); (2) local `.otf` fonts in `assets/fonts/` blocked by preview auth token (401) so browser fell back to sans-serif; (3) external CSS files returned 0 rules in preview iframe ("preview token required"). **Final resolution**: switched to **Google Fonts CDN** via inline `<style>` `@import` directly in each HTML `<head>` (not external CSS). Local `.otf` files kept in project for production export. This affected all 23 preview cards + Landing + UI kit.

### Phase C — Component regenerations (lines 573–1124) — each "Regenerate X: <instruction>"
- **Badges & chips** (3 rounds):
  - `573`: "menší zaoblení a font na Montserrat Medium" → **radius 6px, weight 500**.
  - `595`: "ikonku před textem, živě chci aby pulsovalo kolečko" → each badge gets a **Lucide icon** (stroke 1.75–2px); **LIVE** has pulsing dot with ring-pulse animation (1.4s loop). Icons: ✓ win, ✕ loss, clock for waiting, grid for group, branch for bracket, shield for organizer, trophy for points.
  - `609`: "Chybí badge remíza a body tučné" → added **REMÍZA · +1 b.** badge (equals icon, neutral steel color); points "147 b." → **bold 700**.
- **Buttons** (4 rounds):
  - `621`: "font na Montserrat Bold" → **weight 700**.
  - `635`: "Ohraničené mají chyby, zruš jej, přidal zápas udělej zelený" → **removed secondary/glass variant**, added **success (green gradient)** variant; "+ Přidat zápas" → green pill.
  - `656`: "všechny tlačítka stejné zaoblení" → **uniform radius 12px** (removed btn-sm 10px and btn-pill 999px overrides).
  - `670`: "Na zeleném pozadí bílý font" → **white text on green buttons**.
- **Card surfaces** (3 rounds):
  - `684`: "fonty na Montserrat" → added explicit `'Montserrat', sans-serif` stack.
  - `702`: "Na modrém pozadí texty bílé, na bílém modré" → **left dark-navy `#141e36`** all-white text (eyebrow `#8ac3e8`); **right white** with blue texts (eyebrow `#4699d0`, title `#141e36`).
  - `718`: (no instruction) → assistant added **third variant: accent gradient card** (blue gradient + sheen + glow) for highlights like "Tvoje pozice" / current matchday.
- **Form inputs** (2 rounds):
  - `732`: "fonty na Montserrat" → added fallback stack.
  - `752`: "Fonty udělej Montserrat Light" → **input/dropdown values = Montserrat Light 300**; labels kept at **600** for hierarchy.
- **Leaderboard rows** (`766`): "Montserrat Bold/Light, live body s menším zaoblením, písmo bílé, první 3 pozice v barvách medailí" → **1st–3rd = gold/silver/bronze** (with text glow + colored avatar gradient); names & points **Bold 700**, handle & "B" unit **Light 300**; **all-white text**; delta chips **radius 6px** (was 999px). Bonus 4th row without medal for contrast.
- **Match card** (2 rounds — major redesign):
  - `793`: "Celý design je hrozný, vlajky pěknější, reálné 3D vlajky, barevnost jako Card Surface, fonty Montserrat Bold/Medium/Light" → **3 states map to 3 surfaces**: BRZY on dark navy, TIPOVÁNO on white, UKONČENO on accent blue gradient. **3D coin flags** (round SVG: CZE, SWE, FIN, CAN, SUI, USA with glossy top highlight + bottom shadow + 1px inner ring). Fonts: Bold team names/score/buttons/points; Medium result banner/VS; Light meta (phase + date). Status pills radius 6px.
  - `822`: "Název kola na jeden řádek, datum/čas na druhý, vlajky bez stínů, ukončené skóre Montserrat Black, zarovnej jako předchozí dva" → unified layout (header → teams → score/input → action); **stage on 2 lines**; **flags without shadow** (1px border only); **finished score = Montserrat Black 900 tabular-nums**; full-width footer buttons.
- **Top navigation** (2 rounds):
  - `855`: "aktivní stav Montserrat Light, hover/active Montserrat Bold, logo SVG z souborů, ikonu uživatele piktogram" → links **Light 300 at rest, Bold 700 on hover/active** (blue underline); logo from `assets/logo-wtips.svg`; **Lucide user pictogram** avatar (stroke 1.75px).
  - `887`: "Logo použij logo-wtips.svg" → external load failed in iframe → **inlined SVG** (W wave in accent blue, rest of wordmark white).
- **Numeric display** (`925`): "Fonty Montserrat" → explicit Montserrat on score/odds/points/time samples via `.num-grid`.
- **Montserrat weights** (`949`): explicit Montserrat 100–900; sample text changed English "Tournament" → Czech **"Tipovačka"**.
- **Heading scale** (`973`): explicit Montserrat H1–H6.
- **Body & UI text**: explicit Montserrat on lead/body/small/caption/eyebrow.
- **Hero treatment** (4 rounds):
  - `1027`: "Fonty Montserrat" → explicit Montserrat everywhere; title changed from gradient (`-webkit-text-fill-color: transparent`) to **solid white** (more readable, matches other cards where gradient was removed).
  - `1049`: "větší rozpaky mezi písmeny, pouze lehce" → letter-spacing `-0.04em` → **`-0.01em`**.
  - `1063`: "Grafiku z hotových komponent, badge moc kulatý" → hero reuses real components: badge radius 6px + trophy icon (was 999px pill); floating Match card (dark, LIVE pulse, coin flags ARG vs FRA, Black score); floating leaderboard row (gold 1st + green delta chip radius 6px).
- **Logo & mark** (3 rounds):
  - `1081`: "Využij logo z knihovny, to co v menu" → load from `assets/logo-wtips.svg`.
  - `1101`: "Nevidím reálný náhled" → `<img src>` fails in iframe → **inlined both SVGs** (wordmark + gradient W mark).

### Phase D — Page generation (lines 1127 onward)
- **"Super a ted mi vygeneruj Html stránky"** (`1127`): triggered `questions_v2` (see §2 for answers).
- **"Pokračuj v práci na HTML stránkách"** (`1244`): assistant built shared `pages/site.css` + landing variant A (`landing-bold.html` — "Bold sport").
- **Tweaks panel request** (`1260`): "study this design and add a tweaks panel with two or three expressive controls that reshape the feel, not single-property pixel-pushing" → assistant built **3 systemic levers** on `:root` CSS vars: **Vibe** (Stadium · Studio · Newsroom — Stadium amplifies glow/gradients/shadows/live-pulse; Newsroom removes glass blur, flattens radius to 4px, dampens pulse → analytical terminal/Bloomberg feel); **Density** (Lean · Standard · Loaded — Lean shrinks display to 40px/14px padding, Loaded opens to 72px); **Type pressure** (slider 0–100 — moves display weight 700↔900, tracking -0.01em↔-0.05em, eyebrow case Sentence↔UPPERCASE; default 50 = mid-spectrum 800/-0.03em). Each lever changes 5+ properties at once.
- **"Můžeš pokračovat... opět navrhuješ bez fontu Montserrat, chci aby si stránky komponoval z Design Files, který jsem s tebou ladil"** (`1272`): **KEY DIRECTIVE** — pages must (1) load shared CSS chaining Montserrat, (2) reuse finished components from `preview/` (match card, leaderboard, badges, hero, logo), (3) have explicit `'Montserrat', sans-serif` everywhere.
- **"A kde teda uvidím celý web?" / "Kde zobrazím funkční prototyp?"** (`1762`, `1795`): assistant clarified the full web didn't exist yet; functional prototype = `ui_kits/organizer-webapp/index.html`. Listed next-steps plan: #15 landing 3 variants + index, #16 marketing (Funkce/Ceník/Pro firmy/FAQ), #17 auth, #18 app screens, #19 wire CTAs.

### Phase E — Matches page ("Zápasy") build + many micro-edits (lines 1813–2165)
- **"vytvoř mi podstránku zápasů"** (`1813`): built `MatchesScreen` in organizer SPA — eyebrow + gradient H1, "Hledat" + "+ Přidat zápas"; **4-stat row** (Naplánováno / Live teď / Tipy dnes / Čekající výsledky); **filter bar** tabs (Vše · Live · Dnes · Tipovatelné · Ukončené) + competition dropdown; **list grouped by day**; each row: time + competition chip, both teams with placeholder flags, score/time, status pill (LIVE/UZAVÍRÁ ZA/UKONČENO), compact pick distribution bar, contextual action.
- Long series of **"Apply comment" / "Apply drawing"** micro-edits on MatchRow: unified **TeamFlag** to circular SVG flags (CZ red-white-blue, USA stripes+canton, ES yellow-red) matching `components-match-card` (`1879`); added **"Můj tip"** cell with states (no-tip yellow dashed "Tipovat" / tipped score tabular-nums / live green if tip = current score / finished green "+X bodů · přesně" or red "0 b") (`1913`); removed "184/248" counts → just "N trefy" green (`1941`); pick distribution bar tweaks → one continuous horizontal bar split blue(1)/yellow(X)/pink(2), percentages on top (`2071`); LIVE pill repositioned multiple times (own column between time and home team `2099`, full-width top strip `2117`, back to column `2152`); **"Přidej do zakresleného místa STATUS zápasu (live badge)"** (`2151`).
- **LiveMatch**: added **"Pořadí za zápas"** (per-match ranking, top 10 with points/tips, chip "Přesně/Výsledek/—", "Načíst dalších 10" expand) (`2029`).

### Phase F — Player Dashboard (`pages/dashboard-hrac.html`, lines 2167–2606)
- **"Vytvoř mi stránku Dashboard hráče"** (`2167`): built with top nav (logo, active "Dashboard", notification dot); hero greeting + **rank card** (7./42, 147 b, +3 pozic); 4 stat cards (K tipnutí dnes / Live / Přesné tipy % / Streak); **"Tvé zápasy dnes"** (Live CZE–GER 1:1 tip 2:1 +1b / waiting ESP–ENG "+ Tipnout" / tipped FRA–POR); **Žebříček** left (top 8, "Marek · Ty" highlighted blue); **Tvé soutěže** right (3 pools, position/points/progress bar); **Poslední výsledky** (5 rows). Flags: gradient circles (CZ DE ES FR EN PT IT BE).
- **Pool switcher** evolution: widened to 540px, rounded 14px (not pill) (`2210`); removed FM icon (`2224`); blue label "DASHBOARD VYBRANÉ SOUTĚŽE" above select, switcher shows just name 18px + caret (`2238`); flags → real SVG flags (`2264`).
- **"sekce Tvé zápasy dnes, udělej designem jako ve stránce zápasy"** (`2277`): ported MatchesScreen tip-card layout to dashboard. Then many edits: 7-column grid so MŮJ TIP + actions sit on one row (`2327`); removed "Live update" button → only 3-dot menu (`2359`); removed "Upravit tip" button (`2377`); added filter chips Vše 6/Live 1/Dnes 2/Tipovatelné 3/Ukončené 2 + competition selector (`2392`); CTA "Zobrazit všechny zápasy soutěže →" right-aligned (`2433`); all section links → text-with-arrow style ("Celý žebříček", "Všechny soutěže", "Historie") (`2525`).
- Copy edits: stat card renamed "Zbývá vám natipovat" (`2535`); **direct edit** "LIVE TEĎ" → **"DNES SE HRAJE"** (`2545`); added pool filter (Sport: Fotbal/Hokej/Basketbal · Viditelnost: Veřejné/Neveřejné · Stav: Nadcházející/Skončené) + 2 new finished pools NBA Finals + Český pohár (`2565`); **PIN card** added (8 inputs 4+4, auto-focus, paste handler, "Připojit se" enabled at 8/8) (`2591`).

### Phase G — Leaderboard page (`pages/zebricek.html`, lines 2637–2700)
- **"Chtěl bych vytvořit stránku žebříček"** (`2637`): podium top 3 with medals (gold/silver/bronze, avatars, points, success %, streak); live header (player count, matches played, current round, live indicator); toolbar (search + segment filter Celkem/Toto kolo/Týden/Měsíc + sort); full leaderboard (position, Δ ▲▼●, avatar, points, success % + progress bar, exact tips, hits, streak); **"Tvůj řádek"** highlighted blue + "TY" badge; gap rows between top 12 and tail; sticky bottom strip with your position + CTA; responsive <1100px.
- **"Tvoje pozice"** moved above podium as full banner (no longer sticky) (`2685`).
- Later: **you-strip** reorganized to grid (pozice | Výkon Body/Změna | divider | Cíle Do top 5/Do top 3 | CTA) (`4368`); nav badge "Organizátor" removed, added primary CTA "Vytvořit soutěž" (`4373`).

### Phase H — Unified navigation saga (lines 2704–2970, recurring)
- **"Na všech stránkách udělej funkční menu, design viz organizer-webapp/index.html"** (`2704`): repeatedly iterated. Issues: JS-injected nav not visible in HTML (`2789`) → inlined markup + `pages/nav.css`; layout broke (`2817`) → inlined `<style id="wtnav-style">` in `<head>`; **two menus problem** (`2838`) → removed old internal nav; **"preview token required"** on links (`2858`) → added `target="_top"`; user wanted the actual organizer nav → inlined that nav (Soutěže/Zápasy/Žebříček/Výplaty + "Organizátor" chip) into all 4 pages (`2872`).
- Nav refinements: removed "Výplaty" (`2905`); added "Dashboard" first (`2926`); unified Dashboard tab styling, removed Výplaty from org SPA nav (`2962`); Dashboard opens in same window (`2972`).

### Phase I — Auth pages (lines 2974–3275)
- **Login** (`pages/prihlaseni.html`) (`2974`): unified with žebříček/dashboard style — nav, palette, glass card, mini live match + quick stats left, form right (email + password + Google/Apple). **"Proč je stránka přihlášení celá v jiném designu? Stránky dělej ve stejných designech jako žebříček"** (`3004`) → rewritten to match.
- Added icons above stats (people/trophy/thumbs-up) (`3040`); **"Dej do pozadí fotku v tomto duchu, do průhledná"** → football stadium photo from Unsplash, opacity 0.18, screen blend, navy vignette (`3066`); **"Chci aby fotka byla v tonu modré"** (`3078`); **"vlož vložení PINU nad Badge 257"** (`3094`); PIN card kept only eyebrow "PŘIPOJIT SE K SOUTĚŽI" + button (`3128`); PIN inputs left + button right same row (`3144`).
- **Registration** (`pages/registrace.html`) (`3174`): copied from login. Form: E-mail, Jméno/Příjmení (2-col), Přezdívka + hint, Heslo (eye-toggle), Heslo znovu, GDPR consent, "Vytvořit účet" CTA, link to prihlaseni.html. Made centered, then 80% width with paired fields (e-mail + přezdívka, jméno + příjmení, heslo + heslo znovu).

### Phase J — Create-competition wizard (CreatePoolModal, lines 3279–5196) — major feature
- **"vytvoř vytvoření soutěže, funkční modal, 3 kroky"** (`3279`): User gave EXACT spec:
  - **Krok 1 — Vytvořit novou soutěž**: "Nastavte základy. Hráče pozvete v dalším kroku." → competition name; select from existing tournaments/competitions; "Chci vytvořit soutěž od začátku".
  - **Krok 2 — Vyber pravidla**: (a) Standardní body za: dobrý skóre domácích (editable), dobrý skóre hostů (editable), dobrý tip výsledku zápasu (editable), přesný tip výsledku zápasu; (b) Standardní + střelec zápasu; (c) Vlastní.
  - **Krok 3 — Pozvánka e-mailem** (email input); Poslat URL (copyable); auto-generated group PIN (copyable).
- Built 3-step modal with stepper. Iterations: removed step labels → dots only (`3354`); checkbox "Vytvořit soutěž od začátku" greys select (`3377`); 3 dots stepper (active blue glow, done filled, future dimmed) (`3387`); fixed "can't continue" by pre-filling name (`3399`); Step 2 = 3 stacked radio sections expanding inputs (`3417`); variant cards with checkbox top-right, darker bg (`3479`); user shared reference image "Takový design budou mít boxy standardní/Standard+střelec/Vlastní" (`3497`) → checkmark above title, centered cards, eyebrow-style field labels in accent.
- **Step 4 added** "Pozvete nás na pivo?" (`4927`): 3 → then 2 monetization options: **"Zaplatím za celou skupinu"** (10 Kč × players, payment after tip-lock) vs **"Nechám příspěvek na jednotlivcích"**. Heavily iterated benefit lists. Final (line 5096):
  - **Zaplatím za celou skupinu** (5 gold benefits): Ty rozhoduješ o viditelnosti tipů ostatních · Ty rozhoduješ o čase uzavírky · Vtipné badge pro hráče (Smolař, Šťastlivec…) · Automatické zapisování výsledků · Vlastní pravidla bodování.
  - **Nechám příspěvek na jednotlivcích** — 3 price tiers (blue cards): **50 Kč** lišta tipů ostatních (% voting) · **100 Kč** konkrétní tipy kolegů · **200 Kč** měnit tip během turnaje (max 1h before day's first match).
  - "Doporučujeme" gold star badge on first option (left, gold border + gold check until something selected, then blue); radio button (not checkmark); tag "Po uzavření všech tipujících".
- Wizard made 4 steps with proper numbering; modal widened to 880px; Step 1 fields side-by-side 2-col (Název soutěže + Zdroj zápasů, 44px height) (`5196`).

### Phase K — Pool detail (organizer + player) + premium teaser + more (lines 3661–5196)
- Pool detail header **3 buttons** open modals: **Nastavení** (scoring rules, recycles Step 2), **Pozvat** (emails/URL/PIN, Step 3), **Uzamknout tipy** (new calendar + time picker) (`3661`).
- Pools page reorganized: "Soutěže, kde tipuješ" section + separate "Organizuješ → Tvé soutěže" with PIN/filter/cards; subtle 1px dividers between sections (`3729`, `3777`, `3787`).
- **Premium/paywall teaser** for pick distribution (`4503`): gold **PRÉMIUM** badge + "Distribuce tipů" + "Odemknout →" CTA; blurred percentages + lock icon + "Uvidíš, jak tipuje 248 hráčů" (FOMO); click dispatches custom event **`wtips:open-premium`** + sets `previewUnlocked = true` to reveal real module. Applied to MatchesScreen, LiveMatch, PoolDetail, and dashboard. Gold bar segments tuned: champagne `#ffd166` / mid-gold `#f5b544` / dark bronze `#c88a1d`, 45° striped texture, 2px gap, opacity 0.55. Unlocked shows "248 z 248 tipujících".
- **"+5" points badge** (green pulsing circle) on live MŮJ TIP (`4017`); clean circle 28×28 (`4060`).
- **"Udělej v žebříčku všechny hráče světle šedé kromě prvních třech pozic, všude kde jsou tabulky"** (`4026`): top-3 avatars colored gradient, rank 4+ muted grey; nav (logged-in user) avatar stays colored. Applied to leaderboard + TopScorers. Later top-3 avatars get medal color matching position number (`4090`).
- **"Tipovat za členy" page** (`4189`): select competition + select user + full match list. Spec: dva selecty + complete matches, two number inputs per match, 1/X/2 result chip auto-highlights. Built as modal then **refactored to full screen** `TipForMembersScreen` (`4395`); player select gets search; removed "Předchozí tip" row; 2-column grid for 70 matches.
- **"Můžeš všem tlačítkům + Vytvořit soutěž / + Nová soutěž dát stejnou funkcionalitu... a sjednotit znění na Vytvořit soutěž"** (`4893`): unified copy to "Vytvořit soutěž", all open CreatePoolModal.
- **"Zapsat výsledek" modal** (organizer-only, on live match) (`5204`): big score input (Argentina | : | Francie), match state toggle (● Probíhá / ✓ Ukončený), scorers list (Tým | Minuta | Jméno), add-goal buttons. Modal moved outside Card glass so `position: fixed` works against viewport.

### Phase L — Final prototype assembly (lines 5269–5491)
- **"Můžeš mi ted udělat funkční prototyp?"** (`5269`): full prototype = `ui_kits/organizer-webapp/index.html` with screens (Soutěže, Zápasy), Pool detail, Live match, Create wizard, Profile/Settings modals.
- **Dashboard/Žebříček nav not clickable / opens new tab with token error** (`5302`, `5320`): "preview token required" because `pages/*.html` are separate files outside the SPA. **User chose to rewrite Dashboard + Žebříček as React screens inside the SPA** (`DashboardScreen`, `ZebricekScreen`) (`5353`) — extracted all CSS/HTML/JS from the static pages and injected as components so nav switches screens in same window.
- Fixed premium teaser CSS lost during extraction (was in skipped `wtnav-style` block) (`5455`); kebab 3-dot → "Detail zápasu →" pill (`5389`); added "tipovalo N hráčů" on match-progress events (`5421`); Profile/Settings modals moved below NavBar outside backdrop-filter container for `position: fixed` (`5479`).

---

## 2. THE REQUESTED PAGE SET (from `questions_v2` answers, lines 1137–1146)

**`page_type`** (every page the user explicitly asked for):
1. **Veřejný landing** (homepage / public product landing for Wtips)
2. **Marketingové podstránky**: **Funkce**, **Ceník**, **Pro firmy**, **FAQ**
3. **Přihlášení / Registrace** (login / registration)
4. **Hráčský dashboard** (player dashboard, post-login)
5. **Detail soutěže** (competition detail — as **organizer** AND **hráč/player**)
6. **Stránka tipovacího lístku jednoho zápasu** (single-match tip page)
7. **Žebříček soutěže** (competition leaderboard)
8. **Profil uživatele / historie tipů** (user profile / tip history)

**`audience`**: **Hráči** (people who tip) + **Organizátoři soutěží** (companies, parties, friends/"kámoši").

**`tone`**: **Mix sportovní + profi** (sporty + professional).

**`variations`**: **3 varianty** (different layouts/concepts) — landing in 3 variants. (Only variant A "Bold sport" = `landing-bold.html` was actually built; variants B/C remained on the to-do list throughout.)

**`sections`** (required landing sections): Hero (nadpis + CTA) · Jak to funguje (3 kroky) · Funkce / proč Wtips · Ukázka aplikace (screenshoty / mockupy) · Závěrečné CTA + footer.

**`imagery`**: Mix UI mockupů + abstraktních vizuálů.

**`interactivity`**: Plně interaktivní mock (clickable CTAs → next page).

**`responsive`**: Plně responzivní (desktop, tablet, mobil).

**Build status of the page set** (what actually got built vs. requested): BUILT — `pages/dashboard-hrac.html`, `pages/zebricek.html`, `pages/prihlaseni.html`, `pages/registrace.html`, `pages/landing-bold.html` (variant A only), `pages/index.html` (rozcestník), and the organizer SPA `ui_kits/organizer-webapp/index.html` (Soutěže, Zápasy, Detail soutěže organizer+player, Live match/single-match, Create-pool wizard, Tipovat-za-členy, Profile/Settings modals, plus injected Dashboard + Žebříček React screens). NOT built / still on the open to-do list: landing variants B & C + selection index, marketing subpages (Funkce/Ceník/Pro firmy/FAQ), standalone single-match tip page, dedicated **user Profile / tip-history page** (only Profile *modal* exists in the SPA). CTA wiring (#19) never marked fully complete.

---

## 3. GLOSSARY / TERMINOLOGY & COPY-TONE RULES

**Product terminology** (the assistant proposed these and asked the user to confirm at lines 475–476; user did not object, and they persisted in all built UI):

| Czech term | Meaning / usage in product |
|---|---|
| **soutěž** | pool / competition (the tipping contest unit); plural "soutěže" |
| **tipovačka** | the tipping product/activity itself (brand-level noun; replaced "Tournament" in samples) |
| **pavouk** | bracket (knockout tree); "Pavouk je živě" example copy |
| **skupina / skupinová fáze** | group / group stage; "SKUPINA C" |
| **tip / tipovat / tipnout** | prediction / to predict (verb); "Tvůj tip", "Tipnout", "Tipovat" |
| **tipující** | participant who tips (used as "247 hráčů tipuje", "248 z 248 tipujících") |
| **organizátor** | organizer (runs the pool); chip "Organizátor" |
| **účastník** | participant (referenced as product-language candidate) |
| **hráč** | player; chip "Hráč", "Dashboard hráče" |
| **výkop** | kick-off; "Uzamkněte tipy před výkopem" |
| **zápasový den / zápasy / kolo** | match day / matches / round; "KOLO 35", "MD 3 z 14", "Poslední kolo" |
| **živě / LIVE** | live; pulsing LIVE badge; "DNES SE HRAJE" (replaced "LIVE TEĎ") |
| **konec / ukončeno** | finished match state ("UKONČENO") |
| **brzy** | upcoming match state ("BRZY") |
| **kurz** | odds (numeric display sample, decimal comma e.g. 1,85) |
| **žebříček** | leaderboard |
| **výplata / výplaty** | payout(s) (nav item "Výplaty" — later **removed** from nav per user) |
| **body / b. / b** | points; "147 b.", "+1 b", "+X bodů" |
| **trefa / trefy** | hits (correct predictions); "N trefy" |
| **přesně / výsledek** | "exact" / "result" tip-accuracy chip ("Přesně / Výsledek / —") |
| **remíza** | draw (badge "REMÍZA · +1 b." with equals icon) |
| **streak** | streak (kept English in stat cards) |
| **PIN** | 6–8 char group join code (copyable, auto-generated) |

**Match-card / state vocabulary**: **Brzy** (upcoming), **Tipováno** (tipped), **Ukončeno** (finished); actions **Odeslat tip**, **Upravit tip**; pills **UZAVÍRÁ ZA**, **Uzávěrka 19:30**, **Tipnuto**.

**Czech copy / tone rules** (established lines 471–473, applied throughout):
- **Vykání** (formal "you" — "Uzamkněte tipy…", "Nastavte základy")
- **Sentence case** in headings; **VERZÁLKY/UPPERCASE only** in eyebrow / section labels (e.g. "VÁŠ WORKSPACE", "DASHBOARD VYBRANÉ SOUTĚŽE", "PŘIPOJIT SE K SOUTĚŽI")
- **Decimal comma** (1,85 not 1.85)
- **Czech quotation marks** „…" (low-high)
- **No emoji** — use **Lucide / iconify icons** (stroke ~1.75–2px). (Note: a few 🔒 lock glyphs slipped into the premium teaser copy in transcript text, but the stated rule is no-emoji.)
- **Correct Czech grammatical numerals** ("1 tip / 2 tipy / 5 tipů")
- Tone target: **sporty + professional mix** ("Mix sportovní + profi")

---

## 4. EXPLICIT NEW-FEATURE / PRODUCT-SURFACE DECISIONS STATED IN CHAT

These are product-surface intentions that go beyond pure styling:

1. **Two product surfaces confirmed**: organizer web app + player-facing views (audience = hráči + organizátoři). The same person can be both — Pool detail shows both **"Organizátor"** and **"Hráč"** badges side by side (`3951`, `3961`).
2. **PIN-based pool joining**: 6-digit (wizard) / 8-char (dashboard, 4+4) group PIN, auto-generated, copyable, with paste/auto-advance UX. A core join mechanic.
3. **Create-competition flow** with selectable **source/template** ("vybrat z již vytvořených turnajů, soutěží" — 5 templates) OR "od začátku" (from scratch). "Zdroj zápasů" field implies match-data import.
4. **Configurable scoring rules** (3 presets): **Standardní** (editable points for home-score / away-score / match-result / exact-result), **Standardní + střelec zápasu** (adds goalscorer), **Vlastní** (custom). These are reused in both the wizard (Step 2) and the pool "Nastavení" modal.
5. **Monetization model** (Wizard Step 4 "Pozvete nás na pivo?"): organizer either **pays for the whole group** (10 Kč × players, billed after tip-lock) — unlocking organizer powers (control tip visibility, control lock time, funny player badges like "Smolař"/"Šťastlivec", auto result entry, custom scoring rules) — OR **leaves it to individuals** with 3 per-player premium tiers: **50 Kč** (vote-distribution bar), **100 Kč** (see colleagues' concrete tips), **200 Kč** (change tip during tournament, max 1h before day's first match). Wtips stays free otherwise.
6. **Premium paywall on "Distribuce tipů"** (pick distribution): blurred teaser with `wtips:open-premium` event hook → real implementation should open a paywall modal. Designed for FOMO ("Uvidíš, jak tipuje 248 hráčů").
7. **"Tipovat za členy"** (tip on behalf of members): organizer can enter tips for any player across all matches (select competition + select player + full match list with score inputs). A delegation/admin feature.
8. **"Zapsat výsledek"** (enter result): organizer-only result entry with score + match-state toggle + **scorers** (team/minute/name) — implies goalscorer tracking and the "střelec" scoring variant.
9. **Funny player badges**: "Smolař" (unlucky), "Šťastlivec" (lucky) — gamification element listed as a paid organizer perk.
10. **Live points accrual**: "+5" pulsing badge on the live tip shows points being earned in real time during a running match.
11. **Per-match ranking** ("Pořadí za zápas") inside LiveMatch, separate from the overall leaderboard.
12. **Tweaks panel** (Vibe / Density / Type pressure) — a design-exploration tool on the organizer SPA, persisted via TWEAK_DEFAULTS markers; activated via host `__activate_edit_mode`. (A meta/design-system feature, not an end-user product feature.)

---

## 5. UNRESOLVED / OPEN ITEMS FLAGGED BY THE ASSISTANT

1. **Real logo** — `Wtips.ai` was referenced in the brief but **never uploaded** (lines 27, 164, 169, 477). The user later uploaded `Wtips.svg` (line 208), which the assistant cropped/inlined. So the original `.ai` remains absent, but a usable SVG wordmark + gradient-W mark now exist. (Resolved in practice via the SVG, but the `.ai` source was never provided.)
2. **Team crests / sport-category icons / flags** — no real crest set; **licensing flagged** (line 166: "A proper crest set would need licensing or a custom illustration pass"). Assistant asked whether to link Streamline Sports, commission a custom set, or keep flag-initials (line 171, q3). Resolved partway by hand-drawn **circular SVG flags** (CZ/USA/ES patterns) but only a small country set exists; broader flag-set licensing/coverage unresolved.
3. **Real product copy / voice** — assistant flagged that tone examples in README were **inferred**, not from real Wtips marketing, and asked for real snippets to tune voice (lines 172, q4). Never supplied.
4. **Terminology confirmation** — assistant explicitly asked the user to confirm the glossary (soutěž, tipující, tipovačka, pavouk, skupinová fáze, zápasový den, výkop) at lines 475–476. User never explicitly confirmed or corrected; terms persisted by default.
5. **No original codebase / Figma** — UI kit is a fresh recreation from brand direction, not a pixel-perfect reproduction of any existing Wtips product (line 165).
6. **Landing variants B & C + selection index** — repeatedly listed as to-do #15; **never built** (only variant A `landing-bold.html`).
7. **Marketing subpages** (Funkce / Ceník / Pro firmy / FAQ) — to-do #16; **never built**.
8. **Dedicated user Profile / tip-history page** — part of the requested page set; only a Profile **modal** exists in the SPA (Osobní údaje / Heslo / Smazat účet, email readonly, phone optional). No standalone profile/history page.
9. **Standalone single-match tip page** — the requested "tipovací lístek jednoho zápasu" exists as the in-SPA LiveMatch screen, not as a dedicated static page.
10. **CTA wiring across all pages (#19)** — never marked fully complete; assistant kept proposing it as the closing step.
11. **Preview-environment limitation** (not a product bug, but a persistent friction): static `pages/*.html` files cannot be navigated to from inside the preview iframe without "preview token required"; the cross-file nav was only resolvable by either `target="_top"` (opens new tab) or by porting pages into the SPA as React screens. The user chose the React-port route for Dashboard + Žebříček, but the other static pages remain outside the SPA.
12. **Local `.otf` fonts** remain blocked by the preview auth token (401); production export is expected to use the local fonts, while the preview/prototype relies on Google Fonts CDN — a deliberate split that an implementer must reconcile.