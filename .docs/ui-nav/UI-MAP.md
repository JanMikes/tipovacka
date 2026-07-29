# Current UI surface — map

Snapshot taken 2026-07-28, before any item in this stream landed. **Keep it current**: an
implementer that adds/moves/removes a route, page or shared component must update this file
in the same commit.

Regenerate the route facts with:
```bash
docker compose exec web bin/console debug:router --show-controllers
```

Who may reach what is **not** regenerable from the router — since item 09 it lives in
`tests/Integration/Security/AnonymousReachabilityTest`, keyed by controller class. Read that
before touching security, and update it in the same commit as any new route.

---

## 1. Navigation shell

`templates/base.html.twig` → `<twig:Layout:Nav />` + `<twig:Layout:Footer />`.
`<body data-turbo="false">` — Turbo is globally OFF; opt in per element with `data-turbo="true"`.

**`templates/components/Layout/Nav.html.twig`** — sticky glass top bar, two variants driven by
`app.user`:

| Variant | Primary links | Right-hand actions |
|---|---|---|
| `app` (logged in) | Nástěnka hráče → `dashboard` · Soutěže → `competitions_list` · Žebříček → `leaderboard` | Administrace (ROLE_ADMIN, desktop only) · `<twig:Notification:Bell />` · „Vytvořit soutěž" CTA → `competition_create` · avatar `<details>` dropdown (Profil / CreditBalance / Administrace / Odhlásit se) |
| `public` (logged out) | Soutěže → `competitions_list` · Žebříček → `leaderboard` (item 05) | Přihlásit se · „Registrace zdarma" → `app_register` |

Mobile: `mobile_nav_controller.js` toggles `.wt-mobile` panel which repeats the primary links
plus Profil / Kredity / Administrace / Odhlásit se (logged in) or Přihlásit se / Registrace
zdarma (logged out).

Not in the bar (item 01 slim-down, both variants):
- **Zápasy** (`matches`, `/zapasy`) — page kept, reachable by URL only.
- **Funkce / Ceník / Pro firmy / FAQ** — kept in the footer, `noindex, nofollow` (see §2).

Notable current quirks (candidates for this stream, not yet decided):
- ~~„Žebříček" is a *resolver* route…~~ **Fixed by item 05**: `/zebricek` is a real, publicly
  viewable page. It is in **both** nav variants, needs no id (the soutěž is `?soutez=<uuid>`),
  and its three sub-pages moved under it (`/zebricek/matice`, `/zebricek/clen/{userId}`,
  `/zebricek/shoda`). The old `competition_leaderboard*` routes under `/souteze/{id}/…` are gone.
- „Soutěže" is the **context-aware** competition hub (item 07): the same `/souteze` page serves
  an anonymous visitor (public list only) and a member (plays in / organizes / can join). Active
  state is computed from a `section` variable in `Nav.html.twig` — every `competition_*` route
  lights up „Soutěže", except `leaderboard*`, which belongs to „Žebříček".
- „Kredity" only exists in the mobile menu and the avatar dropdown (via `CreditBalance`).
- The admin area has **no link back** into the portal shell other than the brand mark.

`base.html.twig` exposes `{% block meta_robots %}` (empty by default) so a page can de-index
itself; only the four marketing templates fill it.

**`templates/admin/layout.html.twig`** — separate shell for `^/admin` (own sidebar/tabs).

**`templates/auth/_layout.html.twig`** — bare centred shell for login/registration/reset.

---

## 2. Routes by area

**Fully Czech URL space, one flat tree.** Item 09 deleted the `/portal` prefix: the audience
boundary is no longer written in the path (except `/admin`) but in the code, as
`#[IsGranted('ROLE_USER')]` on every controller in `src/Controller/Portal/`. `access_control`
now holds a single rule, `^/admin → ROLE_ADMIN`.

**Route names carry no prefix either** — `competition_detail`, `dashboard`, `sport_match_edit`.
The only prefixes left are `admin_*` (the admin area), `app_*` (auth + marketing) and `public_*`.

> **Adding a route?** `tests/Integration/Security/AnonymousReachabilityTest` fails until you
> declare, per controller, whether an anonymous visitor may reach it. That test — not the URL —
> is the authoritative statement of who can see what.

Legend: 🔒 = requires a login · 🛡 = ROLE_ADMIN · everything else is anonymous-reachable.
`{id}` = UUID v7 (enforced with `Requirement::UUID`, which is what keeps `/souteze/nova` and
`/souteze/pozvanka/{token}` from colliding with `/souteze/{id}`).

### Public / marketing
| Route | Path | Template | Notes |
|---|---|---|---|
| `app_home` | `/` | `home.html.twig` | redirects a logged-in user to `/nastenka` |
| `app_features` | `/funkce` | `public/features.html.twig` | **noindex, nofollow** — footer-only |
| `app_pricing` | `/cenik` | `public/pricing.html.twig` | **noindex, nofollow** — footer-only |
| `app_for_business` | `/pro-firmy` | `public/for_business.html.twig` | **noindex, nofollow** — footer-only |
| `app_faq` | `/faq` | `public/faq.html.twig` | **noindex, nofollow** — footer-only |
| `app_privacy` | `/ochrana-soukromi` | `public/privacy.html.twig` | |
| `competitions_list` | `/souteze` | `public/competitions_list.html.twig` | the „Soutěže" nav target in **both** variants — **context-aware** since item 07, see the soutěž section |
| `app_design_styleguide` | `/_design` | `design/styleguide.html.twig` | 🛡 — gated by an in-controller `denyAccessUnlessGranted('ROLE_ADMIN')`, **not** by path |

### Auth
`app_login` `/prihlaseni` · `app_logout` `/odhlaseni` · `app_register` `/registrace` ·
`app_verify_email` `/overit-email` · `app_verify_email_pending` `/overeni-ceka` ·
`app_resend_verification_email` · `app_forgot_password_request` `/reset-hesla` ·
`app_reset_password` `/reset-hesla/token/{token}` · `app_check_email` `/reset-hesla/email-odeslan`.
Templates in `templates/auth/`, forms are Live Components in `templates/components/Auth/`.

### Player top level (🔒 all)
| Route | Path | Template | Notes |
|---|---|---|---|
| `dashboard` | `/nastenka` | `portal/dashboard.html.twig` (564 l.) | The „Nástěnka hráče" nav target. Sections: hero headline → primary-soutěž panel with `SoutezSwitcher` + 5 `StatCard`s + mini-leaderboard → 3 global `StatCard`s → „Moje soutěže" cards → „Moje zdroje zápasů" cards → „Nadcházející zápasy" |
| `matches` | `/zapasy` | `portal/matches/index.html.twig` (119 l.) | „Vaše zápasy" — cross-competition match feed, `MatchRow` + `Match:TipStats`. **No longer in the nav** (item 01); URL-only |
| `leaderboard` | `/zebricek` | `public/leaderboard.html.twig` | **public** (item 05) — see the Žebříček section below |
| `credits` | `/kredity` | `portal/credits/overview.html.twig` | + `credits_buy` `/kredity/koupit`, `credits_return` `/kredity/navrat` |
| `notifications` | `/oznameni` | `portal/notifications/center.html.twig` | + `notification_read` `/oznameni/{id}/precteno`, `notifications_read_all` `/oznameni/precteno` |
| `profile_edit` | `/profil` | `portal/profile/edit.html.twig` | |
| `account_delete` | `/ucet/smazat` | `portal/profile/delete_confirm.html.twig` | reachable while unverified (B1 escape hatch) |

### Soutěž — `/souteze` (🔒 unless noted)
The one place the flat URL space earns its keep: the public list, the invitation landing and the
members-only hub are now the same tree, `/souteze`. They are told apart by shape, not by prefix —
`{id}` must be a UUID, so `nova` and `pozvanka/…` can never be mistaken for a competition.

| Route | Path | Template |
|---|---|---|
| `competitions_list` | `/souteze` | `public/competitions_list.html.twig` — **public**, context-aware (see below) |
| `competition_join_by_link` | `/souteze/pozvanka/{token}` | `invitation/landing.html.twig` — **public** |
| `competition_create` | `/souteze/nova` | `portal/competition/create.html.twig` → `Competition:CreateWizard` Live Component (4 steps) |
| `competition_detail` | `/souteze/{id}` | `portal/competition/detail.html.twig` — **a playing surface** since item 08: header (back link, eyebrow „zdroj · kolo", name + Live/Ukončeno/Tipy-uzamčeny pills, role badges, team-filter pills) + a 4-item action bar (**Nastavení** `competition_edit` · **Pozvat** `competition_manage_join_mechanics` · **Tipovat za členy** `competition_manage_members and not isGlobal` · **Uzamknout/Odemknout tipy** `competition_edit`) + the „Tipněte si všechny zápasy najednou" banner + the match list (`Match:MatchRow` + `Match:TipStats` + per-match uzávěrka) + an aside with the žebříček (real rows, „Celý žebříček" → `/zebricek?soutez=`) and `Boost:Panel`. A plain member sees **no** action bar |
| `competition_settings` | `/souteze/{id}/nastaveni` | `portal/competition/settings.html.twig` — **everything organizer** (item 08): links to the large forms (upravit / pravidla / výběr zápasů · týmy / prémium + přepnout na příspěvky), the členové list (ranks, „Přidat e-mail", „Odebrat"), the **Pozvánky** block `#pozvanky` (e-mail, hromadně, bez e-mailu, PIN, sdílený odkaz + jejich obnovit/zrušit), read-only pravidla bodování, and „Nevratné kroky" (opustit / smazat). Page-level access = `competition_view`; every block is gated by its own voter, so a plain member sees the roster + pravidla and nothing else |
| `competition_edit` | `/souteze/{id}/upravit` | `portal/competition/edit.html.twig` |
| `competition_rules` | `/souteze/{id}/pravidla` | `portal/competition/rule_configuration.html.twig` |
| `competition_match_selection` | `/souteze/{id}/zapasy-vyber` | `portal/competition/match_selection.html.twig` |
| `competition_my_tips_batch` | `/souteze/{id}/moje-tipy` | `portal/competition/my_tips_batch.html.twig` |
| `competition_manage_member_tips` | `/souteze/{id}/spravovat-tipy` | `portal/competition/manage_member_tips.html.twig` |
| `competition_premium` | `/souteze/{id}/premium` | `portal/competition/premium_settings.html.twig` |
| `competition_add_anonymous_member` | `/souteze/{id}/clenove/bez-emailu` | `portal/competition/add_anonymous_member.html.twig` |
| `competition_promote_anonymous_member` | `/souteze/{id}/clenove/{userId}/pridat-email` | `portal/competition/promote_anonymous_member.html.twig` |
| `competition_sport_match_guesses` | `/souteze/{id → competitionId}/zapasy/{sportMatchId}` | via `Guess:MatchGuessesList` |
| `competition_join_by_pin` | `/pripojit` | `portal/competition/join_by_pin.html.twig` (+ `competition_join_by_pin_quick` `/pripojit/rychle`) |

Every action reached **from** „Nastavení" returns there (invite, bulk invite, revoke invitation,
remove member, promote/add anonymous member, PIN + link regenerate/revoke, upravit, výběr zápasů,
prémium switch). Lock/unlock tips, join and leave/delete stay on the detail page.

POST-only actions under `/souteze/{id}/` (no template): `…/pripojit-se` (join global),
`…/opustit`, `…/smazat`, `…/uzamknout-tipy`, `…/odemknout-tipy`, `…/premium/zapnout`,
`…/premium/prepnout-na-prispevky`, `…/vylepseni/koupit`, `…/pin/novy`, `…/pin/zrusit`,
`…/odkaz/novy`, `…/odkaz/zrusit`, `…/pozvanky/odeslat`, `…/pozvanky/hromadne`,
`…/clenove/{userId}/odebrat`, `…/zapasy/{sportMatchId}/uzaverka`,
`…/zapasy/{sportMatchId}/clenove/{memberId}/tip`, `…/spravovat-tipy/{memberId}`.
Plus `invitation_revoke` `/pozvanky/{invitationId}/zrusit`.

**`/souteze` — the context-aware hub (item 07).** One public route, five sections that appear
only when they have something to say:

1. **Hero** — eyebrow („Váš workspace" / „Veřejné soutěže"), „Hledat" + „Vytvořit soutěž"
   („Registrace zdarma" logged out), and three `StatCard`s fed by `GetCompetitionsPageStats`:
   Aktivní soutěže / Hráčů celkem / Sledovaných zápasů. **Every figure and sub-label is measured**
   — the scope is the viewer's own world (member of ∪ organizes) or, anonymously, the public list.
   There is **no „Výherní bank" card**: entry fees are burned credits, there are no payouts.
2. **„Soutěže, kde tipuješ"** (`#souteze-hraju`) — `ListMyPlayingCompetitions` → one
   `<twig:Competition:PlayingCard>` each (rank / body / round gain / next action). Members only.
3. **PIN join bar** — the existing `_partials/join_by_pin_form.html.twig`. Verified users only.
4. **„Tvé soutěže"** (`#souteze-organizuji`) — organizer scope. Rendered only when the viewer owns
   something.
5. **Veřejné soutěže** (`#souteze-verejne`) — the discoverable global competitions.

Sections 4 and 5 share **one** query (`ListBrowsableCompetitions`, scoped by
`CompetitionBrowseScope`), **one** card (`<twig:Competition:Card>`, `context="organizer"|"public"`)
and **one** filter bar (`<twig:Competition:FilterBar>`). Filters are query params, never JS state:
the public bar owns `sport` · `stav` · `hledat` · `strana`, the organizer bar prefixes its own with
`moje-` (and adds `moje-viditelnost`), so the two never disturb each other and any filtered view is
shareable. `CompetitionStateFilter::forScope()` decides which „Stav" chips a context offers —
discovery has no „Skončené" because a global competition over a completed source is not listed at all.

### Žebříček — `/zebricek` (item 05)
The whole feature lives under one path now. The soutěž is **always** a query parameter
(`?soutez={uuid}`), never a path segment — that is what lets `<twig:SoutezSwitcher>` (a plain
GET form) scope any of these pages.

| Route | Path | Template | Who |
|---|---|---|---|
| `leaderboard` | `/zebricek` | `public/leaderboard.html.twig` | **public** — an anonymous visitor gets a public **global** competition's board |
| `leaderboard_matrix` | `/zebricek/matice` | `portal/leaderboard/matrix.html.twig` | 🔒 + `leaderboard_details` |
| `leaderboard_member` | `/zebricek/clen/{userId}` | `portal/leaderboard/member.html.twig` | 🔒 + `leaderboard_details` |
| `leaderboard_resolve_ties` | `/zebricek/shoda` | `portal/leaderboard/resolve_ties.html.twig` | 🔒 + `leaderboard_resolve_ties` |

The page: hero (HRÁČŮ / ODEHRÁNO / KOLO / AKTUALIZACE, every figure measured) → switcher →
„Tvoje pozice" (`.you-strip`, members only) → TOP 3 podium → filter bar → table → footer.
**All state is in the URL**, so every view is linkable and JavaScript-free:
`?obdobi=celkem|kolo|7dni|mesic` (`LeaderboardTimeFilter`, tabs render from `cases()`;
„Poslední kolo" is hidden when the soutěž has no round-labelled match), `?hledat=` (search),
`?razeni=body|uspesnost|presne|streak` (`LeaderboardSort` — display order only, POZICE always
shows the real rank), `?vse=1` (expand). A long board is **condensed, not paginated**:
`Service/Leaderboard/LeaderboardTableBuilder` folds the ranks between the head, the viewer's
neighbourhood and the tail into a „… pozice 13–24 …" separator.

**Authorization** is `LeaderboardVoter`, and it has two attributes now:
`leaderboard_view` = member/owner/admin **or anybody at all when `Competition.isGlobal`**;
`leaderboard_details` = member/owner/admin only, and it is what the tip-revealing sub-pages
(matice, clen) use — widening the public board must never widen those. A private competition's
board is unreachable by guessing its UUID; the page silently falls back to one the viewer may
see (the nav entry carries no id, so it must always land somewhere).

### Zdroj zápasů (`/turnaje`) & zápasy (`/zapasy`) — 🔒 unless noted
Bare `/turnaje` no longer exists — item 07 deleted the legacy 301 (`public_match_sources_list_legacy`)
per the PLAN's „no back-compat" convention. Only the `{id}`-scoped pages below live under it.

| Route | Path | Template |
|---|---|---|
| `match_source_detail` | `/turnaje/{id}` | `portal/match_source/detail.html.twig` |
| `match_source_edit` | `/turnaje/{id}/upravit` | `portal/match_source/edit.html.twig` |
| `sport_match_create` | `/turnaje/{matchSourceId}/zapasy/novy` | `portal/sport_match/form.html.twig` |
| `sport_match_import` | `/turnaje/{matchSourceId}/zapasy/import` | `portal/sport_match/import.html.twig` (+ `…/import/potvrdit`) |
| `sport_match_template_download` | `/turnaje/zapasy/sablona.csv` | — (CSV) |
| `sport_match_detail` | `/zapasy/{id}` | `portal/sport_match/detail.html.twig` (+ `_timeline.html.twig`) |
| `sport_match_edit` | `/zapasy/{id}/upravit` | `portal/sport_match/form.html.twig` |
| `sport_match_set_score` | `/zapasy/{id}/skore` | `portal/sport_match/set_score.html.twig` |

`portal/guess/detail.html.twig` is a partial rendered inside the match/tip surfaces — no route
of its own.

Autocomplete JSON endpoints (🔒): `match_source_teams` `/zdroje/{id}/tymy`,
`match_source_players` `/zdroje/{id}/hraci`,
`competition_source_filter_teams` `/zdroje/{id}/filtr-tymy`.

POST-only: `…/ukoncit`, `…/obnovit`, `…/smazat` (source); `…/zrusit`, `…/odlozit`,
`…/presunout`, `…/smazat` (match).

### Invitations (public landing)
`competition_accept_invitation` `/pozvanka/{token}` → `invitation/landing.html.twig`
(the shareable-link twin lives at `/souteze/pozvanka/{token}`, listed above).

### Ops
`health_liveness` `/-/health-check/liveness` · `stripe_webhook` `POST /webhooks/stripe` — both public.

### Admin (`^/admin`, ROLE_ADMIN) — 🛡, unchanged by item 09
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
`EmptyState` · `Breadcrumbs` (`:items`) · `TeamFlag` (`:team`, size) ·
`PremiumTeaser` · `Match/MatchRow` · `Match/TipStats` (`:stats` — **always** feed it from
`TipStatsProvider` batch, never per-row) · `Leaderboard/Podium` · `Leaderboard/Delta`
(the Žebříček table itself is plain markup in `public/leaderboard.html.twig` — item 05 dropped
the `Leaderboard:CompetitionLeaderboard` Live Component, whose state now lives in the URL)
(`:delta`, `:isNew`, variant `chip`) · `Layout/Nav` · `Layout/Footer` ·
`Competition/Card` (`:item` = `BrowsableCompetitionItem`, `context="organizer"|"public"`,
`:walletBalance` — **the** competition card, shared by the organizer and the public grid) ·
`Competition/FilterBar` (`prefix`, `anchor`, `:sportOptions`, `:stateOptions`,
`:visibilityOptions` — **the** competition filter bar, query-param driven) ·
`Competition/PlayingCard` (`:item` = `PlayingCompetitionItem` — standing + next action).

**Live Components (`src/Twig/Components/`)**
`Competition/CreateWizard` (4-step wizard, `.docs/features/create-wizard.md`) ·
`Guess/GuessSubmitForm` · `Guess/MatchGuessesList` ·
`Boost/BoostPanel` · `Notification/Bell` · `Notification/Preferences` · `CreditBalance` ·
`Profile/ProfileForm` · `Scoring/RuleFields` · `Auth/RegistrationForm`, `Auth/InvitationForm`,
`Auth/RequestPasswordResetForm`, `Auth/ResetPasswordForm` ·
`SoutezSwitcher` (`:competitions`, `currentId`, `route`, `param`, `label`, `id` — the grouped
soutěž picker; **`route` must be reachable with no path parameter**, because the control is a
plain GET `<form>` that can only append `?<param>=<id>`. Groups „Probíhající" / „Ukončené",
each option = name + zdroj zápasů + Prague date range. Zero soutěží renders nothing, one
renders a static chip, an unknown id falls back to the first.
See [`.docs/features/competition-switcher.md`](../features/competition-switcher.md)).

**Partials** `_partials/competition_rules.html.twig`, `_partials/join_by_pin_form.html.twig`.

---

## 4. Stimulus controllers (`assets/controllers/`)

`mobile_nav` · `confirm` (destructive submits — `.docs/features/confirm-modal.md`) ·
`confirm_recalculation` · `copy` · `credit_amount` · `datepicker` · `orderable_list` ·
`password_visibility` · `pin_input` · `reveal` · `score_entry` · `scorer_picker` ·
`scoring_preset` · `team_filter` · `team_picker` · `tip_fill` · `tom_select` ·
`competition_matches` · `wizard_matches`.

`tom_select` is shared by all five pickers. Beyond the person shape (`nickname` / `fullName` /
`unverified`) an option may carry plain `data-sub` / `data-meta` attributes — a second line and
a dimmed trailing detail in the dropdown; `data-sub` is searchable. `lockOptgroupOrder: true`
keeps optgroups in DOM order. `dropdownParent: 'body'` on every construction site (B3).

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

1. ~~**No dedicated „my competitions" list page.**~~ **Fixed by item 07**: `/souteze` is now the
   one place every relationship to a competition lives — plays in / organizes / could join —
   and „Tvé soutěže" is the organizer list the app never had. The dashboard still carries its own
   „Moje soutěže" copy of the list (item 06's business).
2. ~~**Competition detail is a 576-line monolith**~~ **Fixed by item 08** („Everything →
   Nastavení"): the detail page is now header + action bar + banner + match list + žebříček
   (with real rows) + boosts, and every organizer control lives on `/souteze/{id}/nastaveni`.
3. **Two parallel object lists** (soutěže + zdroje zápasů) surfaced on the dashboard with equal
   weight, though a normal player never owns a zdroj. _(Item 07 took the soutěž half off the
   dashboard's shoulders — `/souteze` is now the canonical list — but the dashboard sections
   themselves still stand; retiring them is item 06.)_
4. ~~**Žebříček nav item is soutěž-scoped** but presented as global.~~ _(Item 05: `/zebricek` is a real page in both nav variants, scoped by `?soutez` and public for global competitions.)_
5. **Kredity is hidden** — reachable only from the avatar dropdown / mobile menu.
6. **Admin is a separate shell** with no path back; „Administrace" lands on `/admin/turnaje`,
   which is arbitrary rather than an admin overview.
7. **Match pages live under three different parents** (`/zapasy` the feed, `/zapasy/{id}` the
   match, `/souteze/{id}/zapasy/{id}` the same match inside a soutěž) with different chrome.
   _(Item 09 removed the `/portal` prefix, so they now at least share one tree — the chrome
   still differs.)_
8. **Breadcrumbs are used inconsistently** — present on competition detail and leaderboard,
   absent on most other portal pages.
9. **„Zdroj zápasů" answers to two nouns in the URL** — `/turnaje/{id}` for the pages,
   `/zdroje/{id}/…` for the autocomplete endpoints. Item 09 left both alone (it only deleted
   `/portal`); unifying them is a separate decision.
