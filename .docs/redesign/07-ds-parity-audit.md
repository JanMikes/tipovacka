# 07 — DS parity audit matrix (living)

The walk-every-DS-surface coverage matrix. One row per DS page/component. This is
the orchestrator's durable memory across the fix-loop — update each row as it's
closed. Built from 7 parallel read-only scouts (chrome · tokens · components ·
player · auth/public · organizer · admin).

**Status legend:** ✅ parity · ⚠️ minor/partial divergence (fix) · ❌ missing or
major divergence (fix) · 🔮 DS shows it but feature DEFERRED → prepare visual-only
reference · ⛔ CUT — must NOT be built (documented).

**Priority:** **P0** cut-leak/correctness · **P1** most-seen player screens + core
reused components · **P2** organizer kit · **P3** polish + reference elements.

> Ground truth: dark migration complete + green; tokens + "wtips" `@layer
> components` CSS ported into `assets/styles/app.css`; 21 Twig components exist; all
> P1 backlog features shipped. This is AUDIT-AND-FIX: reuse what exists, touch a
> screen only where it diverges from the DS.

## Resolved scope decisions (autonomous, per 00-overview — not re-litigated)
- **Nav 3rd item** = **Žebříček** (drop "Turnaje" — 00-overview folds turnaj
  discovery into the Soutěže dashboard). Needs a new `portal_leaderboard` resolver
  route (redirect to the user's primary soutěž; discovery if 0 groups).
- **PIN-join is numeric** (`JoinByPinFormData` = "přesně 8 číslic") → the 8-box
  `inputmode="numeric"` is CORRECT. (The alnum PIN is the separate turnaj-creation
  PIN on a text field — no conflict.) **No change.**
- **MatchCard** = re-skin the existing `Guess:GuessSubmitForm` Live Component into
  the `.tip-card` surface-states (NOT a separate anonymous component — preserves
  Live wiring). `MatchRow` = a new shared horizontal component replacing the 3
  hand-rolled rows.
- **Ceník (pricing)** = reframe **reference-only / „Připravujeme"**; neutralize
  billing CTAs; remove "Distribuce tipů" from the paid tier (it's FREE, decision #5).
- **Scoring presets** live on `tournament/rule_configuration` (turnaj-level), not
  duplicated into `group/create`. `„+střelec"` preset tile = 🔮 inert.
- **Emails** = dark-brand reskin (scope lists emails) — **P3**, low risk, inline CSS.
- **`partialCount`** already in the leaderboard DTO → **Trefa** column is unblocked.

---

## A. Shared chrome — `Layout/Nav`, `Layout/Footer`, flash, form theme

| DS source | App target | Status | Note / fix |
|---|---|---|---|
| `_wtnav.html`/`nav.css` `.wtnav` sticky glass | `Layout/Nav.html.twig` + app.css | ✅ | Exact port. |
| brand gradient W + wordmark | `Layout/Nav.html.twig` | ✅ | `--grad-accent`, config `brand_name`. |
| public links Funkce·Ceník·Pro firmy·FAQ + actions | `Layout/Nav.html.twig` | ✅ | Correct. |
| **app links Soutěže·Zápasy·Žebříček** | `Layout/Nav.html.twig` | ✅ | DONE (next commit): added `LeaderboardController` (`portal_leaderboard`, `/portal/zebricek`) — redirects to the user's primary soutěž leaderboard, or dashboard if none. Nav 3rd item Turnaje→Žebříček (active-state matches `portal_group_leaderboard`). Test: LeaderboardResolverFlowTest. |
| "Vytvořit soutěž" CTA → create flow | `Layout/Nav.html.twig` | ✅ | KEPT → `public_tournaments_list` (the turnaj browser). Creating a soutěž requires picking a turnaj first (`/portal/turnaje/{id}/skupiny/novy`), so the turnaj list IS the create-soutěž entry point; discovery also lives on the Soutěže dashboard. Documented, not a divergence. |
| active state white+600+underline | app.css `.primary a.active` | ✅ | |
| avatar dropdown Profil/Admin/Odhlásit | `Layout/Nav.html.twig` | ✅ | |
| mobile hamburger `mobile-nav` | `Layout/Nav.html.twig` + controller | ✅ | |
| bell OMITTED | `Layout/Nav.html.twig` | ✅ | Correctly absent. |
| marketing `.wtfoot` 4-col + legal | `Layout/Footer.html.twig` | ✅ | Col labels Produkt/Účet/Společnost (defensible prod IA; keep). |
| app `.app-foot` mini row | `Layout/Footer.html.twig` | ✅ | |
| flash → dark glass status pills | `base.html.twig` L48-67 | ✅ | win/loss/draw/accent + Lucide. |
| form theme dark/labels/focus ring/error | `form/_form_theme.html.twig` | ✅ | Incl. flatpickr + tom-select dark skins. |

## B. Tokens / foundation (validate, don't churn)

| DS token | App `@theme` | Status | Note / fix |
|---|---|---|---|
| navy/accent/status/fg/border/surface ramps | app.css `@theme` | ✅ | All hex match exactly. |
| shadows/glows/motion/container/Montserrat | app.css `@theme` + `@font-face` | ✅ | Self-hosted woff2 300-900. |
| **`--grad-headline`** (180deg #fff 0%,#fff 40%,#8ac3e8 100%) | app.css | ✅ | FIXED: DS sources differ — `--grad-headline`=vertical (DS marketing, `.display-gradient`), added `--grad-headline-alt`=diagonal (DS organizer-kit, `.grad-headline`). Both tokens now exact; class rule split. |
| `--glass-border` rgba(255,255,255,.09) | app.css | ✅ | FIXED: .10 → .09. |
| `--fs-*/--fw-*/--r-*/--s-*` scale tokens | (Tailwind utilities) | ✅ | Expected Tailwind-v4 port omission — no drift in applied values. |

## C. Component catalog (`preview/components-*.html`)

| DS component | App target | Status | Note / fix |
|---|---|---|---|
| buttons → `.btn*` | app.css `@layer` | ✅ | All variants + Lucide. |
| badges → `Badge` | `Badge.html.twig` | ✅ | 7 variants + icon map. |
| pills → `Pill` | `Pill.html.twig` | ✅ | Full variant set. (`pill-done` tint .10 vs DS .18 — trivial.) |
| cards → `.card`/`.card-glass`/`.surface-accent` | app.css `@layer` | ✅ | |
| inputs → form theme + `.score-input`/`.num-input` | form theme | ✅ | Steppers use native spinners (no ▲/▼ glyph) — acceptable. |
| 8-box PIN → `.pin-inputs` | `_partials/join_by_pin_form.html.twig` + `pin_input_controller` | ✅ | Works; numeric (correct). `PinInput.html.twig` extraction = optional, **not** needed (single surface). |
| **leaderboard → `.lb-table`** | `Leaderboard/GroupLeaderboard.html.twig` | ✅ | DONE (next commit) + RECONCILED: DS has **no Trefa column** (DS `zebricek` micro-stats = Přesné/Úspěšnost/Streak, which the app already matches; „trefa" = the gold `.result-tip` tip chip, rendered by MatchRow). Added the DS `.lb-ty` **TY badge** (was "· Ty"). Sticky-me deferred (no vertical scroll container → `position:sticky` is inert). |
| podium → `.podium`/`.pod` | `Leaderboard/Podium.html.twig` | ✅ | |
| **match/tip card (vertical) → `.tip-card` 3-state** | `Guess/GuessSubmitForm.html.twig` | ✅ | DONE (next commit): steppers now use `.tip-inputs` grid + `.colon`; submit is the full-width DS `.btn-block.btn-primary-block`/`.btn-edit-block`. Live wiring (data-model/live-action) + locked/finished states preserved. (The `.tip-head`/`.tip-teams`/`.final-score`/`.result-banner` hero already existed on the detail pages.) |
| **match row (horizontal) → `Match/MatchRow`** | `Match/MatchRow.html.twig` | ✅ | DONE (f0905fc): built shared component + `.tip-row*` @layer CSS; replaced all 3 hand-rolled rows. `:prop` typed/null-safe. |
| pick distribution → `.dist-bar` | `Match/PickDistribution.html.twig` | ✅ | Free after lock. |
| StatCard / Avatar / EmptyState | components | ✅ | |
| **TeamFlag coin SVGs** | `TeamFlag.html.twig` | ✅ (documented) | The robust accent-gradient **initials coin** is the intended final state. The app's actual match data is **free-text club teams** ("Brno", "Teplice", "Mladá Boleslav"), not national teams — the DS's national-flag coin set was for its World Cup demo and doesn't map to free-text club names (02-components calls the name→code map "a nice-to-have, not a blocker"). A national-flag set is deferred as a future enhancement (and would warrant a flag-icon library, not ~16 hand-written SVGs). No missing-flag failures; dark/circular/on-brand. |
| `Scoring/RuleFields` (`.variant-card` presets) | `Scoring/RuleFields.html.twig` | ✅ | DONE (next commit): built the anonymous component + ported `.variant-card`/`.scoring-fields` CSS; used by both portal + admin rule_configuration (dedup). `scoring_preset_controller.js` prefills Standardní (1/1/3/5) / Vlastní; +střelec tile inert. |

## D. Player pages

| DS source | App target | Status | Note / fix |
|---|---|---|---|
| dashboard PIN card | `portal/dashboard.html.twig` | ✅ | |
| dashboard "Moje výsledky" stat cards + switcher | `portal/dashboard.html.twig` | ✅ | 5 stats, flame, decimal comma. |
| dashboard "Tvé zápasy" tip rows | `portal/dashboard.html.twig` | ✅ | DONE (f0905fc): upcoming + evaluated now use `Match/MatchRow`. |
| **dashboard mini-leaderboard** | `portal/dashboard.html.twig` | ✅ | DONE (next commit): `.lb-row` mini-LB for `selected_group` — top 5 (+ the user's own row appended if outside top 5), TY badge + `lucide:flame` streak, links to member breakdown + full žebříček. Controller fetches `GetGroupLeaderboard` for the selected group. |
| dashboard soutěž/turnaj discovery grids | `portal/dashboard.html.twig` | ✅ | Richer than DS. |
| **žebříček you-strip** | `portal/leaderboard/index.html.twig` | ✅ | DONE (next commit): `.you-strip` band (DS) above the podium — Tvoje pozice (rank/total) · Body · Do top 5/Do top 3 gaps (computed in controller from the leaderboard) · „Tipnout další zápas". Δ „Změna" omitted per scope. |
| žebříček podium-wrap | `portal/leaderboard/index.html.twig` | ✅ | |
| žebříček table cols + sticky TY | `Leaderboard/GroupLeaderboard.html.twig` | ✅ | See C: TY badge added; Trefa is not a DS column; sticky-me deferred (inert without scroll container). |
| žebříček lb-toolbar (search) | `Leaderboard/GroupLeaderboard.html.twig` | ✅ (documented) | Deferred-acceptable: the Live Component renders the full list (you find yourself via the highlighted TY row + the you-strip). A client-side `.lb-search` filter would fight the Live morph (resets on update) for marginal value at typical soutěž size. The DS range/sort controls were demo-only. |
| žebříček gap-rows | `Leaderboard/GroupLeaderboard.html.twig` | ✅ (documented) | Deferred-acceptable: „… pozice 13–24 …" condensation only matters on very large boards; the full list renders fine at typical scale. No data loss. |
| Zápasy chips Vše/Dnes/Tipovatelné/Ukončené (no Live) | `portal/matches/index.html.twig` | ✅ | Rows now use `Match/MatchRow` (f0905fc). |
| guess/detail + sport_match/detail hero (no live) | templates | ✅ | `isLive` folded to UZAMČENO; "Probíhá"→"Uzamčeno" relabel done (8b61588). |
| pick distribution after lock | `guess/detail.html.twig` | ✅ | Free. |
| per-match ranking | `guess/detail.html.twig` | ✅ | |

## E. Auth + public/marketing

| DS source | App target | Status | Note / fix |
|---|---|---|---|
| prihlaseni 2-col dark glass + PIN rail | `auth/login.html.twig` | ✅ | No OAuth (cut honored). |
| registrace stack + PIN + fields + GDPR | `auth/register.html.twig` + `Auth/RegistrationForm` | ✅ | No OAuth/divider. |
| password reset / verify / check-email | `auth/*` | ✅ | |
| Landing hero + how-it-works + features + CTA + footer | `home.html.twig` | ✅ | Live-match decoration in hero only (allowed). |
| Funkce / Pro firmy / FAQ | `public/{features,for_business,faq}` | ✅ | |
| **Ceník (3 plány)** | `public/pricing.html.twig` | ✅ | DONE (next commit): paid tier marked „Připravujeme" (pill-soon); CTA „Vyzkoušet zdarma"→„Začít zdarma" (→ register, no checkout); removed „Distribuce tipů" from the paid list (it's FREE, decision #5); „Kdy začnu platit?" + hero reframed as upcoming. Page kept as designed reference. **Product flag:** real billing still needs a payment backend (deferred). |
| Soukromí | `public/privacy.html.twig` | ✅ | DONE (next commit): two ASCII straight close-quotes → Czech „…" (U+201C). |
| public tournament list/detail | `public/tournaments_list`,`tournament_detail` | ✅ | **CUT-LEAK FIXED** (8b61588): `'live'` → "Uzamčeno" locked. |
| invitation landing | `invitation/landing.html.twig` + `Auth/InvitationForm` | ✅ | |

## F. Organizer kit (`ui_kits/organizer-webapp/`)

| DS screen | App target | Status | Note / fix |
|---|---|---|---|
| PoolDetail shell | `portal/group/detail.html.twig` | ✅ | Documented-acceptable on the two optional DS touches: (1) owner "Hráč" role chip — the `Organizátor` crown badge already conveys role; a second "Hráč" pill on every owner row is redundant clutter. (2) inline top-N leaderboard preview — the app uses a `surface-accent` CTA to the full žebříček (one click; the full Podium+table live there) — a defensible IA choice, not a divergence. |
| PoolDetail matchday tip-cards | `portal/group/detail.html.twig` "Moje tipy" | ✅ (documented) | The "Moje tipy" digest uses a compact list (intentionally denser than the full `.tip-card`/MatchRow used on the dashboard/Zápasy); it's a summary with a per-row detail link, functional + on-brand. Reusing MatchRow here would over-tall the digest. |
| CreatePoolModal step1 (name+source) | `group/create` + `tournament/create_private` | ✅ | Split across real routes. |
| **CreatePoolModal step2 `.variant-card` presets** | `portal/tournament/rule_configuration.html.twig` | ✅ | DONE (next commit): `Scoring/RuleFields` component with `.variant-card` presets + `.scoring-fields` rows, used by portal + admin. „+střelec" tile inert (🔮). |
| CreatePoolModal step3 invite (chips + copy-field) | `group/detail` invites | ✅ | (b) DONE (next commit): PIN + invite link now use the DS `.copy-field` + a `copy` Stimulus controller (one-click „Zkopírováno"). (a) email **chip-input** = documented-acceptable: the existing single-email form + bulk textarea (`bulkInvitationForm`) are functionally equivalent; the chip UI is cosmetic polish, deferred (would need a sync-to-hidden-field Stimulus controller). |
| CreatePoolModal step4 contributions tiers | `/_design` (section A) | 🔮✅ | Prepared as inert reference in the `/_design` styleguide (not in any prod flow). |
| InvitePlayersModal roster | `group/detail` + anon-member flows | ✅ | |
| join-by-PIN 8-box | `_partials/join_by_pin_form.html.twig` | ✅ | |
| TipForMembersScreen | `portal/group/manage_member_tips.html.twig` | ✅ | DONE (next commit): live „{filled}/{total} vyplněno" counter + bulk-fill shortcuts (domácí 2:1 / remíza 1:1 / hosté 1:2 / Smazat vše) via `tip_fill_controller.js` (fills only empty rows; dispatches input). Form/CSRF/submit untouched. |
| TipForMembers batch (self) | `portal/group/my_tips_batch.html.twig` | ✅ | DONE: unified `.num-input` → `.score-input` (the canonical score-entry token). |
| **SetResultModal scorers editor** | `set_score.html.twig` + `/_design` (B) | 🔮✅ | Score entry ✅; scorers/timeline + „Trefený střelec" prepared as inert reference in `/_design` (no backend). |
| LiveMatch (live scoreboard) | `sport_match/detail` + `guess/detail` | ✅ | Live correctly stripped; dist free post-lock. |
| PoolsDashboard / tournament grids | `portal/tournament/detail.html.twig` | ✅ | Payout quick-stats dropped (cut). |
| match create / import (round field) | `portal/sport_match/{form,import}` | ✅ | |
| ProfileModal | `portal/profile/edit.html.twig` | ✅ | |

## G. Admin · errors · emails (token application only)

| App target | Status | Note / fix |
|---|---|---|
| all 9 `admin/*` templates | ✅ | Fully dark via global form theme; no leakage. |
| 4 `Exception/error*` pages | ✅ | Dark canvas + CTA. |
| 5 `emails/*` templates | ✅ | DONE (next commit): dark-brand re-skin — outer `#0a111e`, card `#141e36` (+`#1b2742` border), white headings, `#c4cddd` body, accent CTA. Solid inline hex (email-client-safe); color-only (51/51 symmetric diff, all vars/copy intact). 34 email tests green. |

---

## ⛔ Cut-leak log (must stay absent)
- ✅ FIXED (8b61588): `public/tournament_detail` "Živě" + in-product "Probíhá" (sport_match/detail, group/detail, tournament/detail) all → "Uzamčeno" locked.
- Marketing-decoration live pills kept (allowed): landing hero `home.html.twig`, auth rail `login.html.twig`, `features.html.twig` badge. Admin match list keeps "Probíhá" (staff tooling).
- No OAuth/social buttons, no nav bell, no Výplaty, no Tweaks panel, no premium paywall backend anywhere. ✅

## 🔮 Reference-element tracker (STEP 3 — visual-only, inert, gated) — ✅ DONE
Vehicle: **`/_design`** (`DesignStyleguideController`, route `app_design_styleguide`,
ROLE_ADMIN-gated; admin→200, non-admin→403, anon→login). `templates/design/styleguide.html.twig`.
Inert (plain divs/spans, no dead handlers, no backend, no nav link). Test: `DesignStyleguideFlowTest`.
- ✅ Premium/pricing tier cards + create-step-4 contributions („pivo" 10 Kč + 50/100/200) — reuses `PremiumTeaser` (section A).
- ✅ Scorers editor + „Trefený střelec" field (section B, disabled inputs).
- ✅ Notifications bell + feed (section C; generalized `.icon-btn` + new `.icon-dot`).
- ✅ Δ rank-change column (section D, uses existing `.lb-delta-up/-down`; notes it needs rank snapshots).
- ✅ „+střelec" scoring preset tile — inert in `Scoring/RuleFields` (shipped earlier).

## 🚫 Not-drawn (document only)
- Bracket/pavouk + group-stage standings tables — DS never draws them; `round` label only (shipped).
