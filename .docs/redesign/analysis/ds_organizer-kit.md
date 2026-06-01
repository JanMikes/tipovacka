I have everything needed. The `_imported-screens.js` is the source for the embedded Dashboard + Žebříček screens that are inlined into index.html as `DASHBOARD_CSS/HTML/JS` and `ZEBRICEK_CSS/HTML/JS`. I have read the full app. Here is the complete catalog.

---

# Wtips — Organizer Web App UI Kit: Catalog

Location: `/Users/janmikes/www/wtips-design-system/project/ui_kits/organizer-webapp/`

The standalone `*.jsx` source files are an **abridged/earlier version** of the kit. The built **`index.html` (5668 lines)** is the authoritative artifact — it is a single-file React 18 + Babel-standalone app (`<script type="text/babel">`) and contains substantially more than the JSX files: extra screens (global Matches, ported Dashboard + Žebříček), more modals, a 4-step create wizard (vs. 3 in JSX), and a freemium "Distribuce tipů / PRÉMIUM" paywall system that does not appear in the JSX files at all. Where index.html and the JSX disagree, **index.html is the source of truth**; differences are flagged below.

App shell: `App` (index.html:5556) renders `<Nav>` + `.app-shell` + a `screen` state machine. Screens: `pools | matches | detail | match | dashboard | leaderboard | tipformembers` (plus the `CreatePoolModal` overlay). Czech-language throughout. Brand: deep navy `#0f1726`, electric-blue accent `#4699d0`, gold `#f5b544`, status green `#3ed598` / red `#ff5d7a`, Montserrat 100–900, dark-first glass.

---

## 1. SCREEN-BY-SCREEN SPEC

### 1A. Pools Dashboard — `PoolsDashboard` (index.html:1458)

Route: `screen === 'pools'` (the landing screen). Class wrapper `.content-wrap`.

**Layout (top → bottom):**
1. **Page header** — eyebrow `Váš workspace`; gradient H1 `Soutěže` (56px, `.grad-headline`, weight 900, tracking −0.04em); lead paragraph: *"Turnaje, které organizujete. Otevřete soutěž pro správu pravidel, kontrolu tipů nebo uzavření kola."* Right-aligned actions: secondary `🔍 Hledat`, primary `+ Vytvořit soutěž` (opens CreatePoolModal).
2. **Quick-stats row** — 4 glass `Card`s in a `repeat(4,1fr)` grid:
   - `Aktivní soutěže` = **4** · sub `2 živě dnes`
   - `Hráčů celkem` = **612** · sub `+38 tento týden`
   - `Sledovaných zápasů` = **127** · sub `Ve 3 turnajích`
   - `Výherní bank` = **205 000 Kč** · sub `V úschěvném účtu`
   Each: eyebrow label, big tabular num (32px, weight 900), caption sub.
3. **Divider** (gradient hairline).
4. **"Hraješ v" / Soutěže, kde tipuješ** section (index.html:1553) — a **participant** (non-organizer) view. Header eyebrow `Hraješ v`, H2 `Soutěže, kde tipuješ`, sub *"Soutěže, do kterých tě pozval kamarád nebo ses připojil přes PIN."*, right-side count `{n} aktivních`. 3-up grid of cards from `JOINED_POOLS` (index.html:1714). Each card shows: tournament eyebrow, pool name, optional LIVE chip; a tinted inner panel with **Tvá pozice** (`{rank}. / {totalPlayers}`, rank-colored — gold for 1, silver for ≤3) and **Body** (`{points}`) with a trend delta `{trend} v kole` (green `+`, red `−`); footer with `{nextLabel}`/`{nextValue}` and a CTA `Tipuj {pendingTips}` or `Otevřít →`. Sample data: `Kámoši na MS` (4./28, 87 b, +12, "Tipuj 3"), `Hospodská liga UCL` (1./12, 142 b, +9), `Premier League s tátou` (8./14, 64 b, −3, "Tipuj 5").
5. **Divider.**
6. **"Organizuješ" / Tvé soutěže** section header — eyebrow `Organizuješ`, H2 `Tvé soutěže`, sub *"Turnaje, které spravuješ — kontroluj tipy, uzavři kolo, zvi hráče."*
7. **Join-by-PIN card** (index.html:1633) — glass card, blue left-border. Copy: eyebrow `Připojit se k soutěži`, title `Zadej 8místný kód a rovnou se připoj`, sub `Žádná pozvánka, žádné čekání.` **8 single-char boxes** (alphanumeric, uppercased, with a `—` separator after box 4), full keyboard/paste handling (auto-advance, backspace-back, paste-fill). Primary `→ Připojit se` button, disabled until all 8 filled.
8. **Filter bar** (glass card, index.html:1668) — three `SegGroup` segmented controls: **SPORT** (Všechny sporty / Fotbal / Hokej / Basketbal), **VIDITELNOST** (Všechny / Veřejné / Neveřejné), **STAV** (Všechny / Nadcházející / Skončené). Right: `{filtered} z {total} soutěží`. Active segment uses `--grad-accent` fill.
9. **Organizer pool grid** — 2-col grid of glass cards from `POOLS` (index.html:5547, 6 pools). Each card: tournament eyebrow, pool name (22px), LIVE chip (`LIVE · {liveCount}`) or stage chip; meta row `👥 {players} hráčů` + `🏆 {payout}`; **progress bar** (`--grad-accent` fill at `{progress}%`); footer `{progress} % dokončeno` + `Spravovat →`. Empty-state card when filter matches nothing: *"Žádné soutěže neodpovídají filtru…"*. Clicking a card → `onOpen(id)` → PoolDetail.

**Pool data model** (`POOLS`): `name, tournament, sport, visibility, state, live, liveCount, players, matchday, payout, progress, stage`. Payout strings carry the **money/bank** concept: e.g. `105 000 Kč v banku`, `Jen o čest`, `18 000 Kč rozděleno`.

**Tweak hooks:** `data-tw-gap`, `data-tw-display`, `data-tw-pad` attributes on header/stat/card elements drive the vibe/density system.

> JSX `PoolsDashboard.jsx` lacks: the participant "Hraješ v" section, the Join-by-PIN card, the filter bar, and `matchday`/calendar icon in cards. It also says button `Nová soutěž` where index.html says `Vytvořit soutěž`.

---

### 1B. Pool Detail — `PoolDetail` (index.html:1834)

Route: `screen === 'detail'`. Opened from a pool card.

**Layout:**
- **Back link** `← Zpět na soutěže`.
- **Header** — eyebrow `{tournament} · Matchday 3 z 14`; gradient H1 `{pool.name}` (48px) + LIVE chip; **role chips** `Organizátor` (accent) + `Hráč` (neutral) — signals the viewer is both. Action buttons (right): `Nastavení` (→ PoolSettingsModal), `👥 Pozvat` (→ InvitePlayersModal), `✎ Tipovat za členy` (→ TipForMembers screen), primary `Uzamknout tipy` (→ LockTipsModal).
- **Two-column grid `1.35fr 1fr`:**

  **Left = Matchday / tip-cards** (the dashboard-style `.tip-card` component, full styling at index.html:614–668):
  - **Live match card** — left red border, `LIVE 67'` pulsing pill, time `18:00`, league `Skupina A · MD3`, home **Argentina** (Domácí) vs **Francie** (Hosté) with circular `TeamFlag`s, big score `2 – 1`, **Můj tip 2 : 1** with a live `+5` points badge (`.tc-pts-badge.live`, green, pulsing), a `⋯` more button, and a **`TcDistPremium`** distribution teaser (see Feature #1). Clicking → LiveMatch.
  - **3 upcoming/tipped cards** (Brazílie–Německo, Španělsko–Nizozemsko, Portugalsko–Itálie) — each with a pill (`Tipnuto` green / `Uzávěrka 19:30` gold), score-zone shows kickoff time, `Můj tip` set or empty (`+ Zadat tip`), and its own `TcDistPremium` with per-match `{k1,kx,k2}` distribution.
  - Footer link `Zobrazit všechny zápasy soutěže →`.

  **Right = Leaderboard** (`Žebříček`):
  - Filter chips `Všichni · 248` / `Přátelé`.
  - 7 ranked rows (grid `32px 1fr auto 56px`): rank number (gold/silver/bronze colored for 1–3), `Avatar` (medal-gradient for top 3, muted for rest), name + `@handle`, points `{pts} B`, and a colored delta chip (`+12` win-green, `−4` loss-red, `0` neutral). Sample leader: Marek Kulhánek 147 +12.
  - Footer link `Celý žebříček →`.

**State:** local toggles for the three modals (`showLock`, `showSettings`, `showInvite`).

> JSX `PoolDetail.jsx` puts leaderboard left / matchday right, uses `ScoreBig` instead of the `.tip-card` grid, has no role chips, no `Tipovat za členy` button, no premium teaser, and its modals are stubs (buttons inert).

---

### 1C. Create Pool — `CreatePoolModal` (index.html:3000)

A centered modal (`.modal-backdrop` blur + `.modal-panel` slide-up; here widened to maxWidth 880). **4-step wizard** — `STEPS = [Soutěž, Pravidla, Pozvánka, Podpora]` — driven by a dot `Stepper` (`.step-num` active/done states, `.step-bar` connectors). Footer: `← Zpět` (left), `Zrušit` + `Pokračovat →` (right); on final step a contextual create button. `canNext` gating per step.

- **Step 1 — Soutěž** (`Step1`, index.html:2658): two side-by-side fields — `Název soutěže` text input (placeholder `např. Firemní MS 2026`) and `Zdroj zápasů` `<select>` over `TOURNAMENTS` (MS ve fotbale 2026 / MS v hokeji 2026 / UEFA Euro 2028 / Liga mistrů 2025/26 / Premier League 2025/26, each with org + dates). A checkbox card **`Vytvořit soutěž od začátku`** (disables the select; *"Bez šablony — zápasy, týmy a termíny doplníte ručně."*).
- **Step 2 — Pravidla / Scoring** (`Step2`, index.html:2718): eyebrow `Krok 2 ze 4`, title `Vyber pravidla`, sub *"Bodování ovlivní pořadí v žebříčku. Hodnoty lze kdykoli upravit."* Three `.variant-card`s with a checkmark indicator: **Standardní** (Skóre + výsledek), **Standard + střelec** (Plus bonus za střelce), **Vlastní** (Nastavte si sami). Below, a bordered `.scoring-fields` block of `NumberField` rows (label + sub + 64px `.num-input` stepper):
  - `Dobrý tip skóre domácích` (default 1) — Trefený počet gólů domácího týmu
  - `Dobrý tip skóre hostů` (1)
  - `Dobrý tip výsledku` (3) — Výhra / remíza / prohra
  - `Přesný tip výsledku` (5) — Trefená obě skóre současně
  - `Trefený střelec zápasu` (0/2, shown only when variant ≠ standard) — Bonus za uhodnutí střelce
  Presets: standard `{1,1,3,5,0}`, scorer `{1,1,3,5,2}`, custom = editable.
- **Step 3 — Pozvánka / Invite** (`Step3`, index.html:2771): `E-maily hráčů` **chip-input** (Enter/comma/space to add, Backspace removes last, validates `@`); a count hint with Czech pluralization (`hráč/hráči/hráčů`). `Odkaz na pozvánku` copy-field (`https://wtips.cz/p/{pin}`). `PIN skupiny` copy-field (monospace, 6-digit random, letter-spaced) with hint *"Hráč zadá PIN na **wtips.cz/pripojit** a okamžitě se přidá do soutěže."* Copy buttons flip to `Zkopírováno` for 1.5 s.
- **Step 4 — Podpora / "Pozvete nás na pivo?"** (`Step4`, index.html:2845) — **NEW monetization step.** Copy: *"Celý Wtips fungoval, funguje a fungovat bude **zadarmo**… Když nás pozvete **na pivo formou 10 Kč za soutěžícího**, můžeme systém vylepšovat…"* Two radio option-cards:
  - **Zaplatím za celou skupinu** (recommended, gold "Doporučujeme" badge): price `= {emails×10} Kč · {n} hráčů × 10 Kč`, tag `Po uzavření všech tipujících`. Benefit list (`GROUP_BENEFITS`): control whether players see the tip-distribution bar, equal deadline for all, funny player badges (`Smolař, Šťastlivec…`), auto-recorded results, custom scoring/bonuses.
  - **Nechám příspěvek na jednotlivcích**, tag `Ty zůstáváš mimo`. Shows per-player upsell tiers (`INDIVIDUAL_TIERS`): **50 Kč** Lišta tipů ostatních (see how colleagues voted, %); **100 Kč** Konkrétní tipy kolegů; **200 Kč** Měnit tip během turnaje (up to 1 h before first match of the day).
  Final button label switches: `Vytvořit a požádat o příspěvek` (group) vs `Vytvořit soutěž`.

> JSX `CreatePoolModal.jsx` is the **3-step** version (no Step 4 / payment), uses rich option-cards for the tournament list (not a `<select>`), and includes a (buggy) live scoring "Příklad" preview that index.html dropped.

---

### 1D. Live Match View — `LiveMatch` (index.html:3206)

Route: `screen === 'match'`. The in-progress match with **PICK DISTRIBUTION** as the centerpiece.

**Layout:**
- **Header row** — `← Zpět na soutěž`; a **demo dev toggle** `Náhled · Prémium uzamčeno/odemčeno` (flips `previewUnlocked`).
- **Hero match card** (glass, padding 32): `ŽIVĚ · 67'` chip + `Skupina A · Matchday 3 · Maracanã`; an **admin-only** primary `✎ Zapsat výsledek` button (→ SetResultModal). Big matchup: **Argentina** `ARG · V2 R0 P0` (win/draw/loss form) — score **2 – 1** (88px num) — **Francie** `FRA · V1 R1 P0`, with 52px circular flags.
- **Two-column grid `1.2fr 1fr`:**

  **Left = Rozložení tipů / PICK DISTRIBUTION** — two states:
  - **Unlocked** (`previewUnlocked`): `✓ Prémium` badge + eyebrow `Rozložení tipů`, headline `248 hráčů tipovalo`, then 3 bars (`picks`): **Výhra Argentiny** 144 · 58 % (blue, glowing), **Remíza** 55 · 22 % (gold), **Výhra Francie** 49 · 20 % (red). Each bar: label, count + %, filled progress track.
  - **Locked** (default): a **gold paywall card** — `PRÉMIUM` badge, headline, `Odemknout →`, with the real bars rendered **behind a `blur(7px)` overlay** and a centered lock-icon teaser: *"Uvidíš, jak tipuje konkurence"* / *"Detailní rozpad tipů s počtem hráčů, kteří vsadili na 1 / X / 2 — jen pro Prémium."* Click unlocks + fires `wtips:open-premium` CustomEvent.

  **Right = Průběh zápasu / Match timeline** — `Žlutá — Dembélé (FRA) 67'`, `Gól — Messi (ARG) 52'`, `Gól — Mbappé (FRA) 38'`, `Gól — Álvarez (ARG) 24'`, `Výkop 1'`. Each row: minute, a status-colored dot, event text, and **`tipovalo {n} hráčů`** (engagement count per event).

- **`TopScorers`** (index.html:3449) — `Pořadí za zápas` / *"Nejvíc bodů z tohoto zápasu"*, `{n} hráčů s tipem`. Table columns `# · Hráč · Tip · Přesnost · Body`; rows sorted by points; medal dots for top 3; **Přesnost** chip = `PŘESNĚ` (exact, green) / `VÝSLEDEK` (gold) / `—`; points shown as `+8` green or `0` gray. **"Načíst dalších 10" / "Sbalit"** load-more pager (`shown` state).

**SetResultModal** (organizer live-update, index.html:3071): two large 84px score inputs (Argentina : Francie), **Stav zápasu** toggle `● Probíhá` / `✓ Ukončený`, and a dynamic **Střelci** editor — rows of `[team select][minute][scorer name][✕ remove]` with `+ Gól Argentina` / `+ Gól Francie` add buttons. Footer note: *"Změny se okamžitě promítnou v žebříčku."* + `Uložit výsledek`.

> JSX `LiveMatch.jsx` has only the always-unlocked distribution + a simpler timeline (no per-event tip counts), no premium gate, no `Zapsat výsledek` button/modal, and no `TopScorers` table.

---

## 2. SHARED COMPONENTS — `components.jsx` (and the richer index.html copies)

| Component | Props | Variants / behavior |
|---|---|---|
| **`Button`** | `variant`, `size`, `onClick`, …rest | `variant`: `primary` (`--grad-accent` fill, glow shadow, hover lift, `:active` scale 0.98), `secondary` (translucent + blur), `ghost` (text only). `size='sm'` → smaller padding/radius. Disabled = opacity 0.45, no-lift. |
| **`Card`** | `glass`, `style`, `onClick`, …rest | Two surfaces: `.card` (solid `--bg-surface`, hover lifts −2px) and `.card-glass` (translucent + `blur(18px) saturate(140%)`, inner highlight, accent radial `::before` glow). Radius from `--tw-card-radius`. |
| **`Chip`** | `variant`, `live`, children | `.chip` pill (radius `--tw-chip-radius`). Variants: `live` (red, with pulsing `.dot`), `win` (green), `loss` (red), `pending`/`warn` (gold), `accent` (blue), `neutral` (white-glass), `solid` (gradient), `success` (green, used in TopScorers). `live` renders a pulsing dot. |
| **`Avatar`** | `name`, `size` (`sm`/`md`/`lg`), `gradient`, `muted` | Initials from `name`; deterministic gradient picked by first-initial char code (5 gradients). `muted` = desaturated glass fill (for ranks > 3). Sizes 24/32/44px. |
| **`TeamFlag`** | `code`, `size` | **Two implementations.** `components.jsx`: 2-color CSS chevron with the 3-letter code printed on top. **index.html (richer):** real **circular SVG flags** clipped to a circle via `clipPath`, ~15 nations (`CZE, SWE, FIN, CAN, USA, ARG, FRA, BRA, GER, ESP, ENG, MEX, NED, POR, ITA`); fallback gray. Color map `TEAM_COLORS`. |
| **`Eyebrow`** | children | `.eyebrow` — uppercase, letter-spaced, accent-blue caption. Case/tracking driven by the type-pressure tweak. |
| **`ScoreBig`** | `home`, `away`, `dim` | 44px tabular score with `–` separator; `dim='home'/'away'` dims the loser. (Used in JSX PoolDetail; index.html mostly uses inline scores + `.tc-score`.) |
| **`Icon`** | — | Inline-SVG dictionary (stroke 1.75, 24×24): `trophy, plus, users, calendar, arrow, close, copy, check, search, bell` (index.html adds `copy`/`check`). |
| **`StatChip`** | — | **Not a standalone component.** Quick-stats are rendered inline as glass `Card`s (eyebrow + big num + sub). No dedicated `StatChip` despite the task's hint. |

CSS-only composite components (not React): **`.tip-card`** (the 7-column match/tip row, with `.tc-when/.tc-team/.tc-score/.tc-mytip/.tc-pts-badge/.tc-actions/.tc-dist` sub-parts and a `max-width:1400px` responsive collapse), **`.option-card`** / **`.variant-card`** (pickers), **`.copy-field`**, **`.email-chips`**/`.email-chip`, **`.stepper`**/`.step-num`/`.step-bar`, **`PickBar`** (1/X/2 segmented bar in MatchesScreen).

---

## 3. THE TWEAKS PANEL — expressive controls (index.html:5612, infra at 5567 + CSS 440–612)

`useTweaks` + floating draggable `TweaksPanel` (title **"Wtips · Tweaks"**). Persisted block: `TWEAK_DEFAULTS = { vibe:"studio", density:"standard", typePressure:50 }`. Three "system levers" that reshape the whole app feel; applied as `data-vibe`/`data-density` on `<html>` and as continuous CSS custom properties.

1. **Vibe** (`TweakRadio`: **Stadium / Studio / Newsroom**) — rewrites a bank of `--tw-*` vars:
   - **Stadium** — hotter: glass alpha 0.06, blur 22px, **accent-glow 0.45**, multi-color headline gradient (white→gold→blue→pink), pink canvas tint, pill button radius (999px), **faster live pulse (1.1s)** + amplitude ×1.8. Copy: *"Žhavé barvy, gradient nadpisy, silnější glow a rychlejší pulsy. Cítíš davy."*
   - **Studio** — balanced default. *"…tournament-app, jak ho navrhl design system."*
   - **Newsroom** — flat analyst terminal: **glass off (alpha 0, blur 0)**, glow 0.05, solid-white headline (no gradient), **4px square radii**, slow 4s pulse (amp 0.4), opaque cards. Copy: *"…ostřejší rohy, žádné sklo, ztlumené animace. Bloomberg-energy."*
2. **Density** (`TweakRadio`: **Lean / Std / Loaded**) — scales `--tw-card-pad`, stat padding, `--tw-display-size` (40 / 56 / 72px), stat/card-title sizes, grid & section gaps, eyebrow size. Lean = more items per screen; Loaded = generous 72px hero + deep cards.
3. **Type pressure** (`TweakSlider` 0–100, step 5) — continuous typographic intensity computed in JS (index.html:5581): display weight **700→900**, display tracking **−0.01→−0.05em**, card-title weight 600→900, num tracking, and eyebrow tracking 0.06→0.20em + **case flips `none`→`uppercase`** above 0.35. Copy: *"0 = sportscentre čistota, 100 = tabloid headline."*

The generic `tweaks-panel.jsx` also ships unused control primitives: `TweakToggle`, `TweakSelect`, `TweakText`, `TweakNumber` (drag-scrub), `TweakColor` (swatch), `TweakButton`. The panel speaks a host postMessage protocol (`__activate_edit_mode` / `__edit_mode_set_keys` / `__edit_mode_dismissed`) and persists to the `/*EDITMODE-BEGIN*/…/*END*/` JSON block.

---

## 4. NEW FEATURES this kit implies (likely absent from a basic tipovačka)

1. **Live match PICK DISTRIBUTION ("Rozložení / Distribuce tipů")** — the kit's signature feature. A 1/X/2 (home/draw/away) breakdown with both **counts and percentages**, shown on the LiveMatch screen (full bars), on every PoolDetail tip-card (`TcDistPremium`), on MatchesScreen rows, and on the player dashboard. Bars are color-coded blue/gold/red.
2. **Freemium / PRÉMIUM paywall around distribution** — distribution is gated behind a **gold premium teaser**: blurred numbers, lock icon, *"Uvidíš, jak tipuje 248 hráčů"*, `Odemknout →`, firing a `wtips:open-premium` event. Tied to the Step-4 monetization model: org pays **10 Kč/player** to unlock for everyone, or individuals buy tiers (**50/100/200 Kč** for the distribution bar, concrete picks, and mid-tournament tip edits). This is a whole **pricing/upsell surface** a basic clone won't have.
3. **"Pozvete nás na pivo?" support/payout step** — wizard Step 4: pay-for-group vs leave-it-to-individuals, with cost math, recommended badge, and a benefits matrix. New **payments/contributions** concept distinct from prize money.
4. **Prize bank / payout ("v banku / rozděleno / Jen o čest")** — pools carry a money field surfaced as a `Výherní bank` stat (205 000 Kč) and per-pool payout strings (`105 000 Kč v banku`, `18 000 Kč rozděleno`). Implies pot tracking / escrow ("V úschěvném účtu").
5. **"Tipovat za členy" (organizer fills tips for members)** — a full screen (`TipForMembersScreen`, index.html:2459): pick a pool + a member (searchable, with a `late` `{tipped}/{open}` badge), then fill a 2-col grid of score inputs for the round, with **bulk fill** (`home 2:1 / draw 1:1 / away 1:2 / clear`), a `{filled}/{total} vyplněno` counter, preloaded previous tips, and an "Uloženo" toast. Copy notes tips are flagged organizer-entered and the player can overwrite before lock.
6. **Lock-tips deadline picker** (`LockTipsModal`) — a full Czech calendar (month grid, Mon-first, past-disabled) + time input to set when tips lock. *"Po tomto datu už hráči nebudou moct přidávat ani upravovat tipy."*
7. **Set-result / live score editor** (`SetResultModal`) — organizers enter score, toggle live/finished, and add/remove **scorers** (team, minute, name) — implies a scorer-tracking model feeding the "Trefený střelec" scoring bonus.
8. **Scoring-rule configuration UI** — presets (Standardní / +střelec / Vlastní) and 5 editable point values, exposed both in the wizard and as a standalone `PoolSettingsModal`.
9. **Participant management** — `InvitePlayersModal` (email chips + link + PIN), Join-by-PIN 8-box entry, member roster (`POOL_MEMBERS`), role chips (Organizátor/Hráč).
10. **Global Matches planner** (`MatchesScreen`, index.html:3585) — all matches across all your pools, with stat cards (Naplánováno / Live teď / Tipy dnes / Čekající výsledky), status tabs (Vše/Live/Dnes/Tipovatelné/Ukončené with counts), per-pool filter, date-grouped rows, per-match actions (`Live update` / `Uzavřít tipy` / `Zadat výsledek` / `Detail`), a `MyTipCell` (with exact/result coloring + points), `+ Přidat zápas`, and a premium distribution teaser per row.
11. **Analytics / quick stats & streaks** — success-rate %, exact-hit counts, **🔥 streak**, "Do top 5 / top 3 +N b" gap-to-target, `Δ` rank movement, accuracy mini-bars (in the ported Dashboard + Žebříček).
12. **Podium leaderboard + rich rankings** (ported `ZebricekScreen`, index.html:5116) — Top-3 podium (gold/silver/bronze cards with per-player extras), range segments (Celkem/Poslední kolo/Týden/Měsíc), sortable table with columns Pozice/Δ/Hráč/Body/Úspěšnost/Přesné/Trefa/Streak, sticky "me" row, live search.
13. **Player Dashboard ("Ahoj, Marku.")** (ported `DashboardScreen`, index.html:4238) — personal hero with rank panel, stat cards (incl. streak/účinnost), today's matches with the shared tip-card + premium teaser, leaderboard preview, pool switcher, and "Tvé poslední výsledky" history with exact/partial/miss point coloring.
14. **Account surface** — `ProfileModal` (Osobní údaje / Heslo / Smazat účet tabs, notifications) and `AccountSettingsModal` (email/push/SMS toggles, language cs/en/sk), reached from the nav avatar dropdown.

**Not present** (despite the task's prompts): there is **no bracket/pavouk visualization** and **no group-stage standings table** in this kit — tournament structure is referenced only via text (`Skupina A`, `Osmifinále odv.`, `Čtvrtfinále úv.`) and round labels, not drawn. Odds are referenced only as a CSS comment ("scores, odds"); the actual "odds-like" UI is the 1/X/2 **pick distribution**, not bookmaker odds.

---

## 5. INTERACTION / ANIMATION PATTERNS

- **Live pulse** — `@keyframes wtips-pulse` on `.chip-live .dot` (opacity+scale, duration & amplitude tied to the vibe tweak). Tip-cards use a separate `@keyframes tcPulse` (expanding box-shadow ring) on `.tip-pill.live .pulse`, and `tcPtsPulse` (glow breathing) on the live points badge.
- **Counters / load-more** — `TopScorers` reveals 10 rows at a time (`Načíst dalších N` / `Sbalit`); `TipForMembers` shows a live `{filled}/{total}` count; create-wizard email count with Czech pluralization.
- **Modal transitions** — `.modal-backdrop` `fadeIn 200ms` + scrim blur; `.modal-panel` `slideUp 300ms var(--ease-emphatic)` (translateY 20→0). Backdrop-click closes; panel `stopPropagation`.
- **Premium reveal** — locked distribution sits behind `filter: blur(7px)` with a centered teaser overlay; click sets `unlocked`/`previewUnlocked` and dispatches a `wtips:open-premium` CustomEvent. Hover on gold teasers lifts (`translateY(-1/-2px)`) and intensifies border/box-shadow with `--ease-emphatic`.
- **Copy feedback** — copy buttons swap label to `Zkopírováno` for 1.5 s; TipForMembers save shows `✓ Uloženo` then navigates back after 900 ms.
- **Hover micro-interactions** — `.card`/`.card-glass` lift −2px; `.btn-primary` lift −1px + deeper glow, `:active` scale 0.98; `.tc-mytip:hover` lift; nav links underline-grow; avatar caret rotates 180° on menu open.
- **Inputs** — focus rings `0 0 0 3px rgba(70,153,208,0.18)` on fields/score inputs; PIN/score boxes tint blue when filled; segmented controls/tabs animate active fill; the tweak segmented `TweakRadio` thumb slides with `cubic-bezier(.3,.7,.4,1)` and supports pointer-drag.
- **Calendar** — `LockTipsModal` month nav, hover-highlight cells, gradient-filled picked day with glow, disabled past days.
- **Embedded screens** are injected via `StaticHtmlScreen` (sets `innerHTML`, then `new Function(js)` to run flag-swap/filter scripts), scoped by `.embed-dashboard` / `.embed-zebricek` class prefixes — their CSS/HTML/JS source lives in `_imported-screens.js` and is inlined into index.html.

**Key files:** `index.html` (authoritative built app), `components.jsx`/`Nav.jsx`/`PoolsDashboard.jsx`/`PoolDetail.jsx`/`CreatePoolModal.jsx`/`LiveMatch.jsx`/`App.jsx` (abridged source — older 3-step wizard, no premium/payout/Matches/TipForMembers), `app.css` (shared styles, superseded by inline `<style>` blocks in index.html), `tweaks-panel.jsx` (reusable tweak shell), `_imported-screens.js` (Dashboard + Žebříček source), `README.md` (notes this is a fresh recreation from brand direction, to be reconciled against any real Wtips codebase).