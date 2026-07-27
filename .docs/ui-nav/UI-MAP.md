# Current UI surface — map

Snapshot taken 2026-07-28, before any item in this stream landed. **Keep it current**: an
implementer that adds/moves/removes a route, page or shared component must update this file
in the same commit.

Regenerate the route facts with:
```bash
docker compose exec web bin/console debug:router --show-controllers
```

---

## 1. Navigation shell

`templates/base.html.twig` → `<twig:Layout:Nav />` + `<twig:Layout:Footer />`.
`<body data-turbo="false">` — Turbo is globally OFF; opt in per element with `data-turbo="true"`.

**`templates/components/Layout/Nav.html.twig`** — sticky glass top bar, two variants driven by
`app.user`:

| Variant | Primary links | Right-hand actions |
|---|---|---|
| `app` (logged in) | Soutěže → `portal_dashboard` · Zápasy → `portal_matches` · Žebříček → `portal_leaderboard` | Administrace (ROLE_ADMIN, desktop only) · `<twig:Notification:Bell />` · „Vytvořit soutěž" CTA → `portal_competition_create` · avatar `<details>` dropdown (Profil / CreditBalance / Administrace / Odhlásit se) |
| `public` (logged out) | Soutěže → `public_competitions_list` · Funkce · Ceník · Pro firmy · FAQ | Přihlásit se · „Vytvořit soutěž zdarma" |

Mobile: `mobile_nav_controller.js` toggles `.wt-mobile` panel which repeats the primary links
plus Profil / Kredity / Administrace / Odhlásit se.

Notable current quirks (candidates for this stream, not yet decided):
- „Soutěže" for a logged-in user points at the **dashboard**, so the label and the destination
  disagree; the dashboard is a mixed feed (stats + soutěže + zdroje + zápasy).
- „Žebříček" is a *resolver* route (`portal_leaderboard`) that redirects to the user's primary
  soutěž leaderboard — global-looking label, soutěž-scoped destination.
- „Kredity" only exists in the mobile menu and the avatar dropdown (via `CreditBalance`).
- The admin area has **no link back** into the portal shell other than the brand mark.

**`templates/admin/layout.html.twig`** — separate shell for `^/admin` (own sidebar/tabs).

**`templates/auth/_layout.html.twig`** — bare centred shell for login/registration/reset.

---

## 2. Routes by area

Czech URL slugs throughout. `{id}` = UUID v7.

### Public / marketing (logged out, no firewall)
| Route | Path | Template |
|---|---|---|
| `app_home` | `/` | `home.html.twig` |
| `app_features` | `/funkce` | `public/features.html.twig` |
| `app_pricing` | `/cenik` | `public/pricing.html.twig` |
| `app_for_business` | `/pro-firmy` | `public/for_business.html.twig` |
| `app_faq` | `/faq` | `public/faq.html.twig` |
| `app_privacy` | `/ochrana-soukromi` | `public/privacy.html.twig` |
| `public_competitions_list` | `/souteze` | `public/competitions_list.html.twig` |
| `public_match_sources_list_legacy` | `/turnaje` | legacy redirect |
| `app_design_styleguide` | `/_design` | `design/styleguide.html.twig` |

### Auth
`app_login` `/prihlaseni` · `app_logout` `/odhlaseni` · `app_register` `/registrace` ·
`app_verify_email` `/overit-email` · `app_verify_email_pending` `/overeni-ceka` ·
`app_resend_verification_email` · `app_forgot_password_request` `/reset-hesla` ·
`app_reset_password` `/reset-hesla/token/{token}` · `app_check_email` `/reset-hesla/email-odeslan`.
Templates in `templates/auth/`, forms are Live Components in `templates/components/Auth/`.

### Portal — top level
| Route | Path | Template | Notes |
|---|---|---|---|
| `portal_dashboard` | `/nastenka` | `portal/dashboard.html.twig` (564 l.) | The „Soutěže" nav target. Sections: hero headline → primary-soutěž panel with `SoutezSwitcher` + 5 `StatCard`s + mini-leaderboard → 3 global `StatCard`s → „Moje soutěže" cards → „Moje zdroje zápasů" cards → „Nadcházející zápasy" |
| `portal_matches` | `/zapasy` | `portal/matches/index.html.twig` (119 l.) | „Vaše zápasy" — cross-competition match feed, `MatchRow` + `Match:TipStats` |
| `portal_leaderboard` | `/portal/zebricek` | — (redirector) | Resolves to the primary soutěž's leaderboard |
| `portal_credits` | `/portal/kredity` | `portal/credits/overview.html.twig` | + `portal_credits_buy`, `portal_credits_return` |
| `portal_notifications` | `/portal/oznameni` | `portal/notifications/center.html.twig` | + `portal_notification_read`, `portal_notifications_read_all` |
| `portal_profile_edit` | `/portal/profil` | `portal/profile/edit.html.twig` | + `portal_account_delete` `/portal/ucet/smazat` |

### Portal — competition (soutěž) — the hub
| Route | Path | Template |
|---|---|---|
| `portal_competition_detail` | `/portal/souteze/{id}` | `portal/competition/detail.html.twig` (**576 l.** — the biggest page; sections: header + team-filter pills, Členové, Moje tipy, Pozvánky e-mailem, Žebříček panel, `Boost:Panel`, Rychlé pozvánky, Správa) |
| `portal_competition_create` | `/portal/souteze/nova` | `portal/competition/create.html.twig` → `Competition:CreateWizard` Live Component (4 steps) |
| `portal_competition_edit` | `/portal/souteze/{id}/upravit` | `portal/competition/edit.html.twig` |
| `portal_competition_rules` | `/portal/souteze/{id}/pravidla` | `portal/competition/rule_configuration.html.twig` |
| `portal_competition_match_selection` | `/portal/souteze/{id}/zapasy-vyber` | `portal/competition/match_selection.html.twig` |
| `portal_competition_my_tips_batch` | `/portal/souteze/{id}/moje-tipy` | `portal/competition/my_tips_batch.html.twig` |
| `portal_competition_manage_member_tips` | `/portal/souteze/{id}/spravovat-tipy` | `portal/competition/manage_member_tips.html.twig` |
| `portal_competition_premium` | `/portal/souteze/{id}/premium` | `portal/competition/premium_settings.html.twig` |
| `portal_competition_add_anonymous_member` | `/portal/souteze/{id}/clenove/bez-emailu` | `portal/competition/add_anonymous_member.html.twig` |
| `portal_competition_promote_anonymous_member` | `…/clenove/{userId}/pridat-email` | `portal/competition/promote_anonymous_member.html.twig` |
| `portal_competition_join_by_pin` | `/pripojit` | `portal/competition/join_by_pin.html.twig` |

POST-only actions (no template): `…/pripojit-se` (join global), `…/opustit`, `…/smazat`,
`…/uzamknout-tipy`, `…/odemknout-tipy`, `…/premium/zapnout`, `…/premium/prepnout-na-prispevky`,
`…/vylepseni/koupit`, `…/pin/novy`, `…/pin/zrusit`, `…/odkaz/novy`, `…/odkaz/zrusit`,
`…/pozvanky/odeslat`, `…/pozvanky/hromadne`, `portal_invitation_revoke`,
`…/clenove/{userId}/odebrat`, `…/zapasy/{sportMatchId}/uzaverka`.

### Portal — leaderboard (soutěž-scoped)
| Route | Path | Template |
|---|---|---|
| `portal_competition_leaderboard` | `/portal/souteze/{competitionId}/zebricek` | `portal/leaderboard/index.html.twig` |
| `portal_competition_leaderboard_matrix` | `…/zebricek/matice` | `portal/leaderboard/matrix.html.twig` |
| `portal_competition_leaderboard_member` | `…/zebricek/clen/{userId}` | `portal/leaderboard/member.html.twig` |
| `portal_competition_leaderboard_resolve_ties` | `…/zebricek/shoda` | `portal/leaderboard/resolve_ties.html.twig` |

### Portal — match source (zdroj zápasů) & matches
| Route | Path | Template |
|---|---|---|
| `portal_match_source_detail` | `/portal/turnaje/{id}` | `portal/match_source/detail.html.twig` |
| `portal_match_source_edit` | `/portal/turnaje/{id}/upravit` | `portal/match_source/edit.html.twig` |
| `portal_sport_match_detail` | `/portal/zapasy/{id}` | `portal/sport_match/detail.html.twig` (+ `_timeline.html.twig`) |
| `portal_sport_match_create` | `/portal/turnaje/{matchSourceId}/zapasy/novy` | `portal/sport_match/form.html.twig` |
| `portal_sport_match_edit` | `/portal/zapasy/{id}/upravit` | `portal/sport_match/form.html.twig` |
| `portal_sport_match_set_score` | `/portal/zapasy/{id}/skore` | `portal/sport_match/set_score.html.twig` |
| `portal_sport_match_import` | `/portal/turnaje/{matchSourceId}/zapasy/import` | `portal/sport_match/import.html.twig` |
| `portal_competition_sport_match_guesses` | `/portal/souteze/{competitionId}/zapasy/{sportMatchId}` | via `Guess:MatchGuessesList` |
| `portal_guess` detail | — | `portal/guess/detail.html.twig` |

Autocomplete JSON endpoints: `portal_match_source_teams` `/portal/zdroje/{id}/tymy`,
`portal_match_source_players` `/portal/zdroje/{id}/hraci`,
`portal_competition_source_filter_teams` `/portal/zdroje/{id}/filtr-tymy`.

POST-only: `…/ukoncit`, `…/obnovit`, `…/smazat` (source); `…/zrusit`, `…/odlozit`,
`…/presunout`, `…/smazat` (match).

### Invitations (public landing)
`competition_accept_invitation` `/pozvanka/{token}` · `competition_join_by_link`
`/souteze/pozvanka/{token}` → `invitation/landing.html.twig`.

### Admin (`^/admin`, ROLE_ADMIN)
`admin_match_source_list` `/admin/turnaje` (**the admin landing** — this is where every
„Administrace" link points) · `admin_competition_list` `/admin/souteze` ·
`admin_global_competition_create|edit` · `admin_team_list` `/admin/tymy` (+ create/edit) ·
`admin_user_list` `/admin/uzivatele` (+ credits/block/unblock) · `admin_rule_list`
`/admin/pravidla` · `admin_credit_purchases` `/admin/kredity` · `admin_credit_ledger`
`/admin/kredity/transakce`.

---

## 3. Shared Twig components (`templates/components/`)

**Presentational (template-only, `{% props %}`)**
`Avatar` (name, size, rank) · `Badge` (label, variant, icon) · `Pill` (label, variant, icon —
variants seen: `done`, `locked`, `warn`, `soon`, `accent`, `organizer`) · `StatCard` ·
`EmptyState` · `Breadcrumbs` (`:items`) · `TeamFlag` (`:team`, size) · `SoutezSwitcher` ·
`PremiumTeaser` · `Match/MatchRow` · `Match/TipStats` (`:stats` — **always** feed it from
`TipStatsProvider` batch, never per-row) · `Leaderboard/Podium` · `Leaderboard/Delta`
(`:delta`, `:isNew`, variant `chip`) · `Layout/Nav` · `Layout/Footer`.

**Live Components (`src/Twig/Components/`)**
`Competition/CreateWizard` (4-step wizard, `.docs/features/create-wizard.md`) ·
`Leaderboard/CompetitionLeaderboard` · `Guess/GuessSubmitForm` · `Guess/MatchGuessesList` ·
`Boost/BoostPanel` · `Notification/Bell` · `Notification/Preferences` · `CreditBalance` ·
`Profile/ProfileForm` · `Scoring/RuleFields` · `Auth/RegistrationForm`, `Auth/InvitationForm`,
`Auth/RequestPasswordResetForm`, `Auth/ResetPasswordForm`.

**Partials** `_partials/competition_rules.html.twig`, `_partials/join_by_pin_form.html.twig`.

---

## 4. Stimulus controllers (`assets/controllers/`)

`mobile_nav` · `confirm` (destructive submits — `.docs/features/confirm-modal.md`) ·
`confirm_recalculation` · `copy` · `credit_amount` · `datepicker` · `orderable_list` ·
`password_visibility` · `pin_input` · `reveal` · `score_entry` · `scorer_picker` ·
`scoring_preset` · `team_filter` · `team_picker` · `tip_fill` · `tom_select` ·
`competition_matches` · `wizard_matches`.

Plus `assets/spotlight.js` (cursor spotlight on cards — `.docs/features/cursor-spotlight.md`).

---

## 5. Styling

Tailwind + a hand-written component layer in `assets/styles/app.css`, organized as:
tokens → `@layer base` → `@layer utilities` → `@layer components` (buttons, cards, nav `.wtnav`
/ `.wt-mobile`, selects, pills, `.grad-headline`, `.stepper`/`.step-num`/`.step-bar`, delta
chips) → cursor spotlight → misc sections. Dark design system throughout; surfaces are
`bg-surface-1/2/3`, accent is `accent-300/200`.

Rebuilt live by the `tailwind` container. **Never** run `asset-map:compile` locally.

---

## 6. Known IA pain points (observations, not decisions)

Listed so item files can reference them; each becomes a decision only when the product owner says so.

1. **„Soutěže" → dashboard mismatch.** No dedicated „my competitions" list page; the dashboard
   mixes a personal summary, a competition list, a source list and a match feed in 564 lines.
2. **Competition detail is a 576-line monolith** — members, my tips, invitations, leaderboard
   preview, boosts, management all stacked vertically with no tabbing or sectioning.
3. **Two parallel object lists** (soutěže + zdroje zápasů) surfaced on the dashboard with equal
   weight, though a normal player never owns a zdroj.
4. **Žebříček nav item is soutěž-scoped** but presented as global.
5. **Kredity is hidden** — reachable only from the avatar dropdown / mobile menu.
6. **Admin is a separate shell** with no path back; „Administrace" lands on `/admin/turnaje`,
   which is arbitrary rather than an admin overview.
7. **Match pages live under three different parents** (`/zapasy`, `/portal/zapasy/{id}`,
   `/portal/souteze/{id}/zapasy/{id}`) with different chrome.
8. **Breadcrumbs are used inconsistently** — present on competition detail and leaderboard,
   absent on most other portal pages.
