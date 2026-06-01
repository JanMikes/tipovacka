# 02 — Component library (Twig)

Build these as **anonymous Twig components** under `templates/components/` (the
project already maps `App\Twig\Components\` → `components/` and uses anonymous
components like `Breadcrumbs`, `EmptyState`). Each wraps a CSS class from the
`@layer components` block (`01-foundation.md` §4). Exact markup/CSS to copy from
[`analysis/ds_components.md`](analysis/ds_components.md).

Naming: `templates/components/<Name>.html.twig` → `<twig:Name … />`.
Keep props minimal and typed via `{% props %}`. Numbers always `tabular-nums`.

Build order: the **Foundation** group is part of Phase 1 (the base layout needs
Nav/Footer/Button/Pill). The rest land alongside the surfaces that first use them.

---

## Foundation group (Phase 1)

### `Nav` (`Layout/Nav.html.twig`)
The sticky glass `.wtnav`. Two modes via prop `variant: 'app'|'public'` (default
derives from `app.user`).
- Brand: gradient `.brand-mark` "W" + `.brand-name` `{{ brand_name }}` → home.
- **public:** links Funkce/Ceník/Pro firmy/FAQ; actions Přihlásit + "Vytvořit
  soutěž zdarma".
- **app:** links **Soutěže** (`portal_dashboard`) / **Zápasy** (`portal_matches`)
  / **Žebříček** (`portal_leaderboard`); actions "Vytvořit soutěž" CTA + avatar
  dropdown (Profil / Admin if `is_granted('ROLE_ADMIN')` / Odhlásit).
- Active state from `app.request.attributes.get('_route')` → `.active`.
- Mobile hamburger reuses the existing **`mobile-nav`** Stimulus controller.
- Bell icon: **omit** (deferred).
Replaces the nav block currently inline in `base.html.twig`.

### `Footer` (`Layout/Footer.html.twig`)
Prop `variant: 'marketing'|'app'`.
- **marketing** `.wtfoot`: 4-col (brand+tagline / Aplikace / Marketing /
  Společnost) + legal row („© 2026 {{ brand_name }}. Vše hraje, nic se nesází." ·
  „Vyrobeno v Praze.").
- **app** `.app-foot`: single row („© {year} {{ brand_name }} · Tipuj s kámošema"
  + Pravidla · Soukromí · Podpora).

### `Button` (optional helper) / button classes
Prefer plain `<a class="btn btn-primary">`/`<button>` with the CSS classes; a
`Button` component is optional sugar. Variants: primary, success, ghost, danger,
link; sizes sm/lg. Always include a Lucide icon where the DS shows one.

### `Pill` (`Pill.html.twig`)
`{% props label, variant = 'neutral', icon = null, dot = false %}` → `.pill
.pill-{variant}`. Variants: soon/tipped/done/locked/accent/neutral/success/warn.
`dot` renders the small leading dot. Used for match states, statuses.

### `Badge` (`Badge.html.twig`)
`{% props label, variant, icon = null %}` → `.badge .badge-{variant}` with a
Lucide icon. Variants → icons: win=`check`, loss=`x`, draw=`equal`,
pending=`clock`, group=`layout-grid`, organizer=`shield`, points=`trophy`.

### `FlashMessages`
Re-tone the existing `base.html.twig` flash block to dark glass status pills
(success=win green, error=loss red, warning=draw gold, info=accent). Keep the
`app.flashes` capture-once logic.

---

## Surfaces & data components

### `Card` / `CardGlass` / `SurfaceAccent`
Thin wrappers (or just use the classes). `.card` solid surface (hover lift),
`.card-glass` translucent + accent corner glow, `.surface-accent` accent gradient
+ sheen (for "Tvoje pozice" highlight cards).

### `StatCard` (`StatCard.html.twig`)
`{% props label, value, meta = null, tone = null %}` → `.stat` glass card
(eyebrow label, big 36px/900 tabular value, caption meta). `tone:'live'|'win'|
'loss'` recolors the value. Used on dashboard stats + Zápasy header + organizer
quick-stats.

### `Avatar` (`Avatar.html.twig`)
`{% props name, size = 'md', rank = null %}`. Initials from `name` on a gradient.
`rank` 1/2/3 → medal gradients (gold/silver/bronze); else accent or a
deterministic gradient by first-letter. Sizes 24/30/36/44.

### `TeamFlag` (`TeamFlag.html.twig`) — **shipped (initials coin)**
> STATUS: ships the robust **initials coin** (accent gradient) for free-text team
> names. The curated national-flag SVG set below is **deferred** — the app's real
> match data is club teams (free-text), so a nation→flag map doesn't apply and
> would warrant a flag-icon library, not hand-written SVGs. No missing-flag fails.

`{% props code, size = 44 %}`. Circular "coin" flag. Ship a curated set of inline
SVG flags (clipPath circle, `viewBox 0 0 60 60`, `preserveAspectRatio slice`,
**no shadow**, 1px translucent ring) keyed by 3-letter code, with a fallback
`.flag.club` (accent gradient + letters) for unknown codes/club teams.
Seed set from DS: CZE, SWE, FIN, CAN, USA, CHE, ARG, FRA, BRA, GER, ESP, ENG,
MEX, NED, POR, ITA, BEL. Put SVGs in a single Twig include/macro
(`components/_flags.html.twig`) or a PHP map. **No emoji flags.**

> Team names in the current data are free-text strings (`SportMatch.homeTeam`).
> Map name→code where possible; otherwise show the club fallback with initials.
> A team→code/flag mapping table is a nice-to-have, not a blocker.

---

## ⭐ `MatchCard` (the key product element)

`templates/components/Match/MatchCard.html.twig` — the 3(+1)-state tip card.
Full markup/CSS in [`analysis/ds_components.md`](analysis/ds_components.md)
("MATCH / TIP CARD"). One structure, surface variants per state.

Structure: `.tip-card[.surface] → .tip-head(.tip-stage{round,when} + Pill) →
.tip-teams(.tip-team{TeamFlag,name} · .tip-vs · .tip-team) → [score area] →
[footer]`.

**State mapping (NO live state — live is cut):**

| Product state | Condition (backend) | Surface | Pill | Score area | Footer |
|---|---|---|---|---|---|
| **Brzy** (open, not tipped) | match open for guesses, no `Guess` yet | dark `.tip-card` | `pill-soon` „BRZY" / „Uzávěrka {time}" | `.tip-inputs` empty steppers | `.btn-primary-block` **Odeslat tip** |
| **Tipováno** (open, tipped) | open + `Guess` exists | dark | `pill-tipped` „TIPOVÁNO" | steppers prefilled with the tip | `.btn-edit-block` **Upravit tip** |
| **Uzamčeno** (deadline passed, not finished) | not open, not finished | dark, muted | `pill-locked` „UZAMČENO" | read-only „Tvůj tip {N:N}" (or „Netipováno") | — (link „Detail zápasu →") |
| **Ukončeno** (finished) | `state == Finished` + evaluation | `.tip-card.accent` | `pill-done` „UKONČENO" | `.final-score {N:N}` (Black 900) | `.result-banner` „Tvůj tip {N:N} — **+X bodů**" (check icon; if not tipped, „Netipováno · 0 bodů") |

Notes:
- This wraps/re-skins the existing **`Guess:GuessSubmitForm`** Live Component,
  which already morphs button labels (Odeslat/Upravit/Smazat). Keep its
  `#[LiveProp]`/`#[LiveAction]` wiring; replace the score inputs with the
  `.score-input` stepper styling. The steppers (▲/▼) can be a tiny Stimulus
  controller or native `<input type=number>` styled as the box; **keep keyboard
  entry working**.
- Header: round on line 1 (`.round`, from `SportMatch.round` label, fallback the
  tournament/stage), date+time on line 2 (`.when`, format „23. 4. · 20:15").
- Czech points numerals: „+1 bod / +2 body / +5 bodů".
- A compact **horizontal** variant (`MatchRow`) is used in lists; see below.

### `MatchRow` (`Match/MatchRow.html.twig`)
The dashboard/Zápasy **horizontal** tip row (DS `dashboard-hrac` `.tip-card`
grid: time/league · pill · home team · score-zone · away team · my-tip box ·
actions, with an optional full-width distribution strip). Same state model as
`MatchCard`. Used in lists where the vertical card is too tall. Left border tint
by state (tipped=green, locked=muted). „Detail zápasu →" action links to the
single-match page.

---

## Leaderboard components

### `Leaderboard` (`components/Leaderboard/GroupLeaderboard.html.twig` — shipped)
Re-skins the **`Leaderboard:GroupLeaderboard`** Live Component to the DS `.lb-table`.
Columns: **Pozice · Hráč · Úspěšnost · Přesné · Streak · Body** — this matches the
DS `zebricek.html` micro-stats (Přesné/Úspěšnost/Streak + Body). **There is NO
„Trefa" column:** the DS surfaces partial hits only as the **gold `.result-tip`
chip on individual tips** (organizer-kit matchday list), which is rendered by
`Match/MatchRow` (`hitClass="partial"`), not as a leaderboard stat. Δ column also
omitted (deferred). Rank 1–3 gold/silver/bronze + glow; `.lb-acc-bar` = % + thin
progress bar; `.lb-streak` = `lucide:flame` + n (no emoji); highlighted „TY" row
(`.lb-tr.me` accent border + the `.lb-ty` gradient badge). Sticky-me + gap-rows
(„… pozice 13–24 …") are deferred (the component renders the full list with no
vertical scroll container, so `position:sticky` would have no effect).

### `Podium` (`Leaderboard/Podium.html.twig`)
Top-3 raised cards (2nd silver / 1st gold raised+enlarged / 3rd bronze): medal
number, big Avatar (medal gradient), name, @handle, big points, micro-stats
(Přesné / Úspěšnost / Streak). Sits above the table on the full leaderboard page.

### `LeaderboardRow` (mini)
Compact row for the dashboard mini-leaderboard (`pos · avatar · name+@handle ·
points · streak`), top-3 colored, „me" highlighted.

---

## Forms & inputs

### `PinInput` (`PinInput.html.twig`)
The 8-box join-PIN entry (auto-advance, backspace-back, paste-spread, uppercase
alnum, `—` after box 4, submit disabled until 8 filled). Port the DS JS into a
**Stimulus controller** `pin_input_controller.js`. Replaces the single-field PIN
in `_partials/join_by_pin_form.html.twig` and appears on login/register left rail
and the dashboard. Posts to the existing PIN-join routes (`/pripojit`,
`/pripojit/rychle`). **Backend PIN is 8 chars** — use the 8-box variant, not the
6-digit one.

### Form theme (`form/_form_theme.html.twig`)
Re-theme every widget: dark input bg (`--color-inset`), uppercase 10–11px
letter-spaced labels (`--fg-3`), blue focus ring `0 0 0 3px rgba(70,153,208,.18)`,
DS checkbox/radio (accent fill + check), error → `--color-loss`. This propagates
to **all** forms. Keep the datepicker/tom-select widget hooks; they now render on
the dark skins from `01` §5.

### Scoring rule fields (`Scoring/RuleFields.html.twig`)
For rule configuration + create-soutěž: `.variant-card` presets (Standardní /
Vlastní — **the „+střelec" preset is deferred**) + `.scoring-fields` rows of
`.num-input` steppers (label + sub). Maps to the 4 existing rules:
`correct_home_goals`(1) / `correct_away_goals`(1) / `correct_outcome`(3) /
`exact_score`(5). Keep the `confirm-recalculation` controller.

### `EmptyState` (existing — fix it)
Re-skin to a dark glass empty card and **finally render the `illustration` prop**
(currently accepted but produces no output — wire it to a Lucide icon or remove
the prop). Keep the title/body/CTA API.

### `Breadcrumbs` (existing — reskin)
Re-tone to dark. (Decision: keep breadcrumbs — they're used across portal — but
the DS prefers a single „← Zpět na soutěže" back-link; you may add a `BackLink`
component for detail pages and keep breadcrumbs for deep admin/portal trees.)

---

## Distribution (free, after-lock)

### `PickDistribution` (`Match/PickDistribution.html.twig`)
The 1/X/2 bar (home-win / draw / away-win) with counts + %. Colors blue/gold/red.
Shown on the single-match page (and optionally MatchRow) **only after the match's
tip deadline / once locked** and respecting `Group.hideOthersTipsBeforeDeadline`.
Backed by a new `GetMatchPickDistribution` query (see `04-features.md` §Distribuce).
**No premium gate now** — render the real bars directly. (Keep the gold
"PRÉMIUM" teaser markup out of scope; it returns in the future monetization phase.)

---

## Icons to import (run before referencing)

`bin/console ux:icons:import lucide:flag lucide:flame lucide:equal lucide:layout-grid
lucide:shield lucide:bell lucide:plus lucide:check lucide:x lucide:clock lucide:trophy
lucide:chevron-down lucide:chevron-right lucide:arrow-right lucide:copy lucide:search
lucide:lock lucide:user lucide:users lucide:calendar lucide:target lucide:settings
lucide:crown lucide:circle-check-big lucide:circle-alert lucide:triangle-alert lucide:info`

(Many already exist in `assets/icons/lucide/` — importing is idempotent. Verify
each referenced icon exists, since dev throws on missing.)
