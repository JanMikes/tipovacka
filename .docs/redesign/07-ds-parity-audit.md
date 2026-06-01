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
| **app links Soutěže·Zápasy·Žebříček** | `Layout/Nav.html.twig` L13-17 | ❌ **P1** | App shows **Turnaje** as 3rd, not **Žebříček**. Add `portal_leaderboard` resolver route → primary soutěž; replace nav link. |
| "Vytvořit soutěž" CTA → create flow | `Layout/Nav.html.twig` L41 | ⚠️ **P1** | CTA → `public_tournaments_list`. Create needs a turnaj first; route to a portal turnaj-picker (or dashboard discovery), not the public marketing list. |
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
| **TeamFlag coin SVGs** | `TeamFlag.html.twig` | ⚠️ **P2** | Only `.flag.club` initials fallback; the curated country SVG set (CZE/SWE/FIN/CAN/…) is NOT shipped. Build `_flags` macro + name→code map per 02-components seed set. |
| `Scoring/RuleFields` (`.variant-card` presets) | (missing) | ❌ **P2** | No `.variant-card`/`.scoring-fields` classes; rule rows duplicated in portal+admin `rule_configuration`. Build component + add classes. See F. |

## D. Player pages

| DS source | App target | Status | Note / fix |
|---|---|---|---|
| dashboard PIN card | `portal/dashboard.html.twig` | ✅ | |
| dashboard "Moje výsledky" stat cards + switcher | `portal/dashboard.html.twig` | ✅ | 5 stats, flame, decimal comma. |
| dashboard "Tvé zápasy" tip rows | `portal/dashboard.html.twig` | ✅ | DONE (f0905fc): upcoming + evaluated now use `Match/MatchRow`. |
| **dashboard mini-leaderboard** | `portal/dashboard.html.twig` | ❌ **P1** | Missing entirely (DS §D left col). Add compact top-N for `selected_group` + "· Ty" + flame, link to full žebříček. |
| dashboard soutěž/turnaj discovery grids | `portal/dashboard.html.twig` | ✅ | Richer than DS. |
| **žebříček you-strip** | `portal/leaderboard/index.html.twig` | ❌ **P1** | Missing "Tvoje pozice 7./42 · Body · Do top N" band. Add `.you-strip` `@layer` class + summary from `member_stats`. **Omit Δ "Změna".** |
| žebříček podium-wrap | `portal/leaderboard/index.html.twig` | ✅ | |
| žebříček table cols + sticky TY | `Leaderboard/GroupLeaderboard.html.twig` | ✅ | See C: TY badge added; Trefa is not a DS column; sticky-me deferred (inert without scroll container). |
| žebříček lb-toolbar (search) | `Leaderboard/GroupLeaderboard.html.twig` | ⚠️ **P3** | No "Najít hráče…" search. Add cheap client-side `.lb-search` filter (range/sort were demo-only — skip). |
| žebříček gap-rows | `Leaderboard/GroupLeaderboard.html.twig` | ⚠️ **P3** | No "… pozice 13-24 …" condensation. Low value at small scale — document acceptable or add. |
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
| **Ceník (3 plány)** | `public/pricing.html.twig` | ❌ **P1** | Promises a live paywall ("Vyzkoušet zdarma" on 99 Kč, "/ měsíc") + lists "Distribuce tipů" as paid. Reframe reference-only/„Připravujeme"; neutralize CTAs; drop "Distribuce tipů" (it's FREE). |
| Soukromí | `public/privacy.html.twig` | ⚠️ **P1** | Lines 51,72 ASCII straight quotes → „…". |
| public tournament list/detail | `public/tournaments_list`,`tournament_detail` | ✅ | **CUT-LEAK FIXED** (8b61588): `'live'` → "Uzamčeno" locked. |
| invitation landing | `invitation/landing.html.twig` + `Auth/InvitationForm` | ✅ | |

## F. Organizer kit (`ui_kits/organizer-webapp/`)

| DS screen | App target | Status | Note / fix |
|---|---|---|---|
| PoolDetail shell | `portal/group/detail.html.twig` | ✅ | (Optional: "Hráč" role chip on owner row; inline top-N LB preview — both ⚠️ P3.) |
| PoolDetail matchday tip-cards | `portal/group/detail.html.twig` "Moje tipy" | ⚠️ **P3** | Compact rows; optionally reuse `.tip-card`. |
| CreatePoolModal step1 (name+source) | `group/create` + `tournament/create_private` | ✅ | Split across real routes. |
| **CreatePoolModal step2 `.variant-card` presets** | `portal/tournament/rule_configuration.html.twig` | ❌ **P2** | Plain rows, no preset tiles. Add `.variant-card`+`.scoring-fields` to `@layer`; build `Scoring/RuleFields`; dedupe portal+admin. „+střelec" tile = 🔮 inert. |
| CreatePoolModal step3 invite (chips + copy-field) | `group/detail` invites | ⚠️ **P2** | (a) email **chip-input** missing (`.email-chips`); (b) PIN+link use raw readonly inputs, not `.copy-field` one-click-copy. Add classes + a `copy` Stimulus controller. |
| CreatePoolModal step4 contributions tiers | — | 🔮 **P3** | Correctly absent. Reference-only later (premium). |
| InvitePlayersModal roster | `group/detail` + anon-member flows | ✅ | |
| join-by-PIN 8-box | `_partials/join_by_pin_form.html.twig` | ✅ | |
| TipForMembersScreen | `portal/group/manage_member_tips.html.twig` | ⚠️ **P2** | Missing "{filled}/{total} vyplněno" counter + bulk-fill shortcuts; unify `.score-input` (vs `.num-input` in `my_tips_batch`). |
| TipForMembers batch (self) | `portal/group/my_tips_batch.html.twig` | ✅ | (score-input class unify — see above.) |
| **SetResultModal scorers editor** | `portal/sport_match/set_score.html.twig` | 🔮 **P3** | Score entry ✅; scorers/timeline + „Trefený střelec" = deferred visual-only (inert, no backend). |
| LiveMatch (live scoreboard) | `sport_match/detail` + `guess/detail` | ✅ | Live correctly stripped; dist free post-lock. |
| PoolsDashboard / tournament grids | `portal/tournament/detail.html.twig` | ✅ | Payout quick-stats dropped (cut). |
| match create / import (round field) | `portal/sport_match/{form,import}` | ✅ | |
| ProfileModal | `portal/profile/edit.html.twig` | ✅ | |

## G. Admin · errors · emails (token application only)

| App target | Status | Note / fix |
|---|---|---|
| all 9 `admin/*` templates | ✅ | Fully dark via global form theme; no leakage. |
| 4 `Exception/error*` pages | ✅ | Dark canvas + CTA. |
| 5 `emails/*` templates | ⚠️ **P3** | Light body + navy header (deliverability-safe but off dark-brand). Reskin to navy body + light text (inline CSS, structure unchanged). |

---

## ⛔ Cut-leak log (must stay absent)
- ✅ FIXED (8b61588): `public/tournament_detail` "Živě" + in-product "Probíhá" (sport_match/detail, group/detail, tournament/detail) all → "Uzamčeno" locked.
- Marketing-decoration live pills kept (allowed): landing hero `home.html.twig`, auth rail `login.html.twig`, `features.html.twig` badge. Admin match list keeps "Probíhá" (staff tooling).
- No OAuth/social buttons, no nav bell, no Výplaty, no Tweaks panel, no premium paywall backend anywhere. ✅

## 🔮 Reference-element tracker (STEP 3 — visual-only, inert, gated)
Vehicle: a ROLE_ADMIN/kernel.debug `/_design` styleguide route mirroring DS preview, OR an off-by-default flag. Label „Připravujeme / reference". NO dead JS handlers, NO backend.
- Premium/pricing tier cards + create-step-4 (extend `PremiumTeaser` + `premium_enabled`).
- Scorers/timeline editor + „Trefený střelec" field (SetResultModal).
- Notifications bell + feed.
- Δ rank-change column (`.lb-delta-*` CSS exists; needs rank snapshots — show inert column in styleguide only).
- „+střelec" scoring preset tile (in `RuleFields`, inert).

## 🚫 Not-drawn (document only)
- Bracket/pavouk + group-stage standings tables — DS never draws them; `round` label only (shipped).
