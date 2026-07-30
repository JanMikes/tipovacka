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

`templates/base.html.twig` → `<twig:Layout:Nav />` + `<twig:Layout:Footer />`, both wrapped in
`{% block navigation %}` / `{% block page_footer %}` (B14) so a page can render **chrome-free**.
`/overeni-ceka` overrides both — brand-mark-only header, no footer — so the verification airlock cannot
advertise pages the guard will bounce the user off. **`templates/auth/_layout.html.twig` is NOT
chrome-free**: it extends `base` and renders the nav like every other page.
`<body data-turbo="false">` — Turbo is globally OFF; opt in per element with `data-turbo="true"`.

**`templates/components/Layout/Nav.html.twig`** — sticky glass top bar, two variants driven by
`app.user`:

| Variant | Primary links | Right-hand actions |
|---|---|---|
| `app` (logged in) | Nástěnka hráče → `dashboard` · Soutěže → `competitions_list` · Žebříček → `leaderboard` | Administrace (ROLE_ADMIN, ≥640 px) · **`<twig:CreditBalance />` balance chip → `/kredity#dobit`** · `<twig:Notification:Bell />` · „Vytvořit soutěž" CTA (>900 px) · avatar `<details>` dropdown (>900 px: Profil / Kredity / Administrace / Odhlásit se) |
| `public` (logged out) | Soutěže → `competitions_list` · Žebříček → `leaderboard` (item 05) | Přihlásit se (>420 px) · „Registrace zdarma" → `app_register` |

Mobile: `mobile_nav_controller.js` toggles the `.wt-mobile` panel, which repeats the primary
links plus Vytvořit soutěž / Profil / Kredity / Administrace / Odhlásit se (logged in) or
Přihlásit se / Registrace zdarma (logged out). **The panel is where the bar's dropped actions
live (item 17 / B20):** the bar shed the CTA labels at ≤1100 px (app variant), the avatar
dropdown and „Vytvořit soutěž" at ≤900 px, and the wordmark plus „Přihlásit se" at ≤420 px —
it did not fit 320 px before that (+53 px public, +7 px app). The exact link sets of both
panels are pinned in `tests/Integration/Auth/NavigationTest`.

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
- ~~„Kredity" only exists in the mobile menu and the avatar dropdown~~ **Fixed by item 17**:
  the balance is a chip in the bar itself, linking to `/kredity#dobit` („Dobít kredity").
  **`credits_buy` is POST-only** (the Stripe action), so nothing may link to it — a chip
  pointing there would 405. `CreditBalance` renders exactly once per request and memoizes the
  wallet read (3 SELECTs → 1).
- The bell's panel is `.bell-panel` (app.css, nav section): an anchored 20rem dropdown above
  900 px, a bar-wide sheet pinned to the **bar's** right edge below it (B18 — it used to be
  pinned to the trigger, which sits mid-bar on a phone, so it ran off the left edge).
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
| `app_design_styleguide` | `/_design` | `design/styleguide.html.twig` | 🛡 — gated by an in-controller `denyAccessUnlessGranted('ROLE_ADMIN')`, **not** by path — the two-half component gallery (item 13): half A the live shared components, half B the deferred elements; entirely inert, no DB |

### Auth
`app_login` `/prihlaseni` · `app_logout` `/odhlaseni` · `app_register` `/registrace` ·
`app_verify_email` `/overit-email` · `app_verify_email_pending` `/overeni-ceka` ·
`app_resend_verification_email` · `app_forgot_password_request` `/reset-hesla` ·
`app_reset_password` `/reset-hesla/token/{token}` · `app_check_email` `/reset-hesla/email-odeslan`.
Templates in `templates/auth/`, forms are Live Components in `templates/components/Auth/`.

### Player top level (🔒 all)
| Route | Path | Template | Notes |
|---|---|---|---|
| `dashboard` | `/nastenka` | `portal/dashboard.html.twig` | The „Nástěnka hráče" nav target — **the player's home** since item 06, and since item 18 **quick actions for ONE soutěž**: the hero no longer lists the sections, it says what the page is („Na této obrazovce můžeš přepínat mezi jednotlivými soutěžemi…", no link). The soutěž is picked with `<twig:SoutezSwitcher>` (`?soutez={uuid}`, unknown/foreign id falls back silently) and the scope is now **always** that one soutěž — item 18 deleted the „SOUTĚŽ" roletka (`?zapasy=vse`), the only widener there was; the cross-soutěž feed is `/zapasy`. Sections in order: hero (eyebrow, „Ahoj, {nickname}.", switcher, **„Tvoje pozice" `.hero-rank` card + „Zobrazit celou tabulku"** arrow button → `/zebricek?soutez=`) → „Poslední Tvoje tipy" (+ „Historie →" `leaderboard_member` and a **„Tipovat zápasy v soutěži"** button → `/souteze/{id}/moje-tipy`) → „Moje soutěže" (**deliberately un-scoped** — the full cross-soutěž overview; each card is clickable **as a whole** via a stretched `.card-stretch` link with „Zobrazit na nástěnce" raised above it as `.card-raise`, no „Otevřít soutěž" link any more, and **„Všechny soutěže" sits BELOW the grid**) → „Následující zápasy" (**filters in two shapes over one `?filtr=`**: `.lb-tab` chips ≥ 768 px, a native `.mf-scope` roletka with a „Použít" submit below it — both work with JavaScript off) → „Odehrané zápasy" beside the „Žebříček" sidebar (`.lb-row`, „Celý žebříček →" `/zebricek?soutez=`). Both match lists render `Match:MatchRow` (item 18 designed this card; item 21 made it the only one). Fed by `ListUserMatches` scoped to the soutěž, so „Rozložení tipů" is one `TipStatsProvider` batch, never per row. **Gone** (item 06): the PIN bar, „Moje zdroje zápasů", „Objev další soutěže", the three count `StatCard`s. **Empty state** (B15), for a viewer in no soutěž: PIN bar (primary) → „Procházet soutěže" → „Vytvoř si vlastní soutěž" (small text link) |
| `matches` | `/zapasy` | `portal/matches/index.html.twig` | „Vaše zápasy" — cross-competition match feed, one `Match:MatchRow` **card** per match with the „Rozložení tipů" strip(s) inside it — **more than one strip when the match sits in several of the viewer's soutěže**, each headed by its soutěž name (item 21). **No longer in the nav** (item 01); URL-only |
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
| `competition_detail` | `/souteze/{id}` | `portal/competition/detail.html.twig` — **a playing surface** since item 08: header (back link, eyebrow „zdroj · kolo", name + Live/Ukončeno/Tipy-uzamčeny pills, role badges, team-filter pills) + a 4-item action bar (**Nastavení** `competition_edit` · **Pozvat** `competition_manage_join_mechanics` · **Tipovat za členy** `competition_manage_members and not isGlobal` · **Uzamknout/Odemknout tipy** `competition_edit`), then **ONE column** (item 19 dissolved the aside): **popis soutěže** (cap `Competition::DESCRIPTION_MAX_LENGTH` = 1000, escaped + `whitespace-pre-line`, renders **nothing** when empty) → **„Pozvat kamaráda"** → `/souteze/{id}/nastaveni#pozvanky`, same voter as „Pozvat" → the „Tipněte si všechny zápasy najednou" banner → **„Tabulka soutěže"** + the match list, **5 rows with „Načíst všechny zápasy (N)"** below → **Žebříček** (`#zebricek`, real rows, „Celý žebříček" → `/zebricek?soutez=`) → **`Boost:Panel`** (`#vylepseni`). The match list renders `Match:MatchRow` — one design on every surface since item 21. A plain member sees **no** action bar and no „Pozvat kamaráda". **First visit as a member of a `boosts` competition opens a `<dialog>` with the boost prices** (`_boost_intro_modal.html.twig` + `boost-intro` controller); dismissal is stamped on `Membership.boostIntroSeenAt` via `POST …/vylepseni/uvod/skryt`, and it is suppressed for a non-member, on premium/`none`, and on a fully-over competition (B6) |
| `competition_settings` | `/souteze/{id}/nastaveni` | `portal/competition/settings.html.twig` — **everything organizer** (item 08): links to the large forms (upravit / pravidla / výběr zápasů · týmy / prémium + přepnout na příspěvky), the členové list (ranks, „Přidat e-mail", „Odebrat"), the **Pozvánky** block `#pozvanky` (e-mail, hromadně, bez e-mailu, PIN, sdílený odkaz + jejich obnovit/zrušit), read-only pravidla bodování, and „Nevratné kroky" (opustit / smazat). Page-level access = `competition_view`; every block is gated by its own voter, so a plain member sees the roster + pravidla and nothing else |
| `competition_edit` | `/souteze/{id}/upravit` | `portal/competition/edit.html.twig` |
| `competition_rules` | `/souteze/{id}/pravidla` | `portal/competition/rule_configuration.html.twig` |
| `competition_match_selection` | `/souteze/{id}/zapasy-vyber` | `portal/competition/match_selection.html.twig` |
| `competition_my_tips_batch` | `/souteze/{id}/moje-tipy` | `portal/competition/my_tips_batch.html.twig` |
| `competition_manage_member_tips` | `/souteze/{id}/spravovat-tipy` | `portal/competition/manage_member_tips.html.twig` |
| `competition_premium` | `/souteze/{id}/premium` | `portal/competition/premium_settings.html.twig` |
| `competition_add_anonymous_member` | `/souteze/{id}/clenove/bez-emailu` | `portal/competition/add_anonymous_member.html.twig` |
| `competition_promote_anonymous_member` | `/souteze/{id}/clenove/{userId}/pridat-email` | `portal/competition/promote_anonymous_member.html.twig` |
| `competition_sport_match_guesses` | `/souteze/{id → competitionId}/zapasy/{sportMatchId}` | via `Guess:MatchGuessesList` — heading „Jak tipovali ostatní" (was „Tipy soutěže", renamed round 2: the old name never said the block lists OTHER members' tips) |
| `competition_join_by_pin` | `/pripojit` | `invitation/join_by_pin.html.twig` — **public** since B15 (`Controller\Invitation\JoinByPinController`), as is `competition_join_by_pin_quick` `POST /pripojit/rychle`. Reachable means *typeable*, never *joinable*: an unverified account may read the landing and join through none of it |

Every action reached **from** „Nastavení" returns there (invite, bulk invite, revoke invitation,
remove member, promote/add anonymous member, PIN + link regenerate/revoke, upravit, výběr zápasů,
prémium switch). Lock/unlock tips, join and leave/delete stay on the detail page.

POST-only actions under `/souteze/{id}/` (no template): `…/pripojit-se` (join global),
`…/opustit`, `…/smazat`, `…/uzamknout-tipy` (`lock_mode=now|at` + `lock_at`, B2),
`…/odemknout-tipy` (also cancels a pending scheduled lock), `…/premium/zapnout`,
`…/premium/prepnout-na-prispevky`, `…/vylepseni/koupit`, `…/vylepseni/uvod/skryt`, `…/pin/novy`, `…/pin/zrusit`,
`…/odkaz/novy`, `…/odkaz/zrusit`, `…/pozvanky/odeslat`, `…/pozvanky/hromadne`,
`…/clenove/{userId}/odebrat`, `…/zapasy/{sportMatchId}/uzaverka`,
`…/zapasy/{sportMatchId}/clenove/{memberId}/tip`, `…/spravovat-tipy/{memberId}`.
Plus `invitation_revoke` `/pozvanky/{invitationId}/zrusit`.

**`/souteze` — the context-aware hub (item 07).** One public route, five sections that appear
only when they have something to say:

1. **Hero** — eyebrow („Váš workspace" / „Veřejné soutěže"), „Vytvořit soutěž" („Registrace zdarma"
   logged out), and three `StatCard`s fed by `GetCompetitionsPageStats`: Aktivní soutěže / Hráčů celkem
   / Sledovaných zápasů. **Every figure and sub-label is measured, and since item 15 every one is a
   platform-wide total — byte-identical logged in and logged out** (the query takes no viewer). There is
   **no „Výherní bank" card** and **no „Hledat" button** (it pointed at a search field that no longer
   exists). There is no filter or search card anywhere on this page.
2. **„Soutěže, kde tipuješ"** (`#souteze-hraju`) — `ListMyPlayingCompetitions` → one
   `<twig:Competition:PlayingCard>` each (rank / body / round gain / next action). Members only.
3. **PIN join bar** — the existing `_partials/join_by_pin_form.html.twig`. Since B15 shown to
   **anonymous visitors and verified members**; hidden only while a logged-in account is unverified.
4. **„Tvé soutěže"** (`#souteze-organizuji`) — organizer scope. Rendered only when the viewer owns
   something.
5. **Veřejné soutěže** (`#souteze-verejne`) — the discoverable global competitions.

Sections 4 and 5 share **one** query (`ListBrowsableCompetitions`, scoped by `CompetitionBrowseScope`)
and **one** card (`<twig:Competition:Card>`, `context="organizer"|"public"`). **Item 15 removed both
filter bars.** The only query params the page reads are `strana` and `moje-strana` (pagination —
„Zobrazit další", out-of-range clamps). `Competition:FilterBar` still exists but has **no production
call site**; it renders only in `/_design`, labelled „Bez použití".

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

The page (item 15 stripped it back to the content): heading „Žebříček" → switcher → „Tvoje pozice"
(`.you-strip`, members only, **no tip button**) → TOP 3 podium (**desktop only**, `hidden md:block`) →
**one bare name-search input, outside any card** → table → footer. **All state is in the URL** and the
page is JavaScript-free: `?soutez=` and `?hledat=` are the only parameters left. There are **no period
tabs, no sort and no expand control** — `?obdobi=`, `?razeni=` and `?vse=` are gone, along with
`LeaderboardTimeFilter`, `LeaderboardSort`, `GetCompetitionCurrentRound` and
`GetCompetitionMatchProgress`. The board is **all-time only**, so the Δ column always renders. A long
board is **condensed, not paginated**: `Service/Leaderboard/LeaderboardTableBuilder` folds the ranks
between the head, the viewer's neighbourhood and the tail into a „… pozice 13–24 …" separator, and the
search is how a viewer reaches somebody inside a folded stretch (the footer says so).

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
| `sport_match_detail` | `/zapasy/{id}` | `portal/sport_match/detail.html.twig` (+ `_timeline.html.twig`) — **one match, fully understood** since item 10 |
| `sport_match_edit` | `/zapasy/{id}/upravit` | `portal/sport_match/form.html.twig` |
| `sport_match_set_score` | `/zapasy/{id}/skore` | `portal/sport_match/set_score.html.twig` |

**`/zapasy/{id}` — the match page (item 10).** A match can belong to SEVERAL of the viewer's
soutěže, and members, scoring rules and boost entitlements all differ per soutěž — so **one is in
focus at a time**, chosen with `<twig:SoutezSwitcher>` (`route="sport_match_detail"`,
`:routeParams="{id: matchId}"`, `?soutez={uuid}`, unknown/foreign id falls back silently) and
**every number on the page is scoped to it**. Sections in order:

1. **Hero** — status `Pill` (Naplánován / Živě / Ukončeno / Odložen / Zrušen), meta line
   „kolo · venue · datum" (`SportMatch` has exactly ONE `round` and ONE `venue`), „Zapsat výsledek"
   for whoever passes `sport_match_set_score`, teams + `TeamFlag` + the big score (kickoff time
   before it), and the **team form** sub-label „ARG · V2 R0 P0" (`GetTeamForm`, one query for both
   teams, counted over the finished matches THIS soutěž includes; absent, never zeroed).
2. **Tip form** (`Guess:GuessSubmitForm`) with the switcher beside it, plus B4's
   **„Proč tu nejsou všechny vaše soutěže"** panel — the switcher lists what INCLUDES the match,
   the panel explains what EXCLUDES it, so no soutěž is ever described by both.
3. **„Rozložení tipů"** — `<twig:Match:TipStats :compact="false">` fed by `TipStatsProvider`,
   gated by `BoostType::TipDistribution`; locked = blurred skeleton + „Odemknout →".
4. **„Průběh zápasu"** — `_timeline.html.twig`, pure match events (minute · dot · „Gól — Messi
   (ARG)"). **No „tipovalo N hráčů" counts** — deferred to a future fantasy feature.
5. **„Pořadí za zápas"** — `GetMatchRanking`, gated by `BoostType::OthersTips` through
   `TipVisibilityGate` (entitled OR past the deadline; managers/admins get no free pass). Columns
   # · HRÁČ · TIP · PŘESNOST · BODY; before the match is scored there are no ranks/points, so those
   three columns are dropped rather than filled with dashes. Locked = the same shell + `Boost:Panel`.

Then „Správa zápasu" (upravit / odložit / přesunout / zrušit / smazat) for the source's owner.

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
`Avatar` (name, size, rank) · `Pill` (label, variant, icon, dot — the **nine** variants defined in
`app.css`: `done` · `tipped` · `success` · `soon` · `warn` · `accent` · `neutral` · `locked` ·
`live`) · `Badge` (label, variant, icon — `win` · `loss` · `draw` · `pending` · `competition` ·
`organizer` · `points`) · `StatCard` ·
`EmptyState` · `Breadcrumbs` (`:items`) · `TeamFlag` (`:team`, size) ·
`PremiumTeaser` · `Match/TipStats` (`:stats` — **always** feed it from
`TipStatsProvider` batch, never per-row; `compact=true` is the strip that lives INSIDE a match
card (item 11) and must not be placed anywhere else, `compact=false` the full „Rozložení tipů"
card of item 10: state pill + „N hráčů tipovalo" +
three labelled bars with an absolute count AND a percentage, or a blurred skeleton behind a lock
coin with the buy CTA. The real bars are `.dist-bar`/`.dist-fill`, the paywall decoration
`.dist-ghost-fill` — keep them apart, „is the split visible?" is asserted on the real ones)
· **`Match/MatchRow`** — **THE match card, one design on every surface** (item 21 retired the `variant`
prop and the item 11 row shape it selected). `.tip-row.is-dash` is the card (4 px left accent stripe per
`state` = `open|tipped|live|locked|finished`); inside it `a.tip-row-link` holds a centred header
(`.tipd-head`: kolo · Playoff · datum · čas, state pill right-aligned — centred when it wraps to its own
line on a phone), **mirrored** team blocks (`.tipd-side`, home role-above-name, away name-above-role)
around the **score, or „vs" when there is none — never a duplicated kickoff time**, and a full-width
footer holding ONE thing: „Zadat tip" (`.tipd-cta`, a *styled* `<span>`, only while `tipUrl` is set and no
tip is known), the „MŮJ TIP 2 : 1" bar (`.my-tip.is-bar`) with the points badge inline, or
`tipMissingLabel`. **The whole card is ONE `<a class="tip-row-link">`**, so the pill and the tip bar are
NOT links (this deliberately reverses B7's „the row itself is NOT a link"), and `.tip-row-extra` — the
divider, the „Rozložení tipů" strip(s) and a small foot note — stays a **sibling** of that link, because
the strip is itself a control and nothing interactive may be nested inside an `<a>`. Container-relative
throughout: no width media query anywhere, the card renders from 1088 px down to 238 px.

Props beyond the fixture: `tipMissingLabel` („Netipováno" — competition detail ONLY, a cross-competition
card cannot claim it, see B5; `/zapasy` and the Nástěnka say „Uzamčeno" in the pilulka instead), `points`
(the „+5" badge, inline in the bar), `tipStats` (`list<TipStats>` **always from the page's
`TipStatsProvider` batch, never a query per row** — on `/zapasy` and the Nástěnka the list can hold MORE
than one entry, because a match sits in several of the viewer's soutěže, and each strip is then headed by
its own soutěž name) and `footNote` („zdroj · venue" on the cross-competition lists, „Uzávěrka …" on
competition detail). There is no „Tipovat →" action and no `tipPrompt` any more: the card itself links to
the match, so a locked card is never a dead end. **`/zapasy` passes no `myHome`/`myAway`** (cross-
competition: whose tip?), so it gates `tipUrl` on a tip actually **MISSING** somewhere — otherwise a card
whose pilulka reads „Tip odeslán (2/2)" would invite a tip in the same breath (item 21). Rendered in
`/_design` half A in all five states, in a 380 px column and in the two-soutěže case. · `Leaderboard/Podium` · `Leaderboard/Delta`
(the Žebříček table itself is plain markup in `public/leaderboard.html.twig` — item 05 dropped
the `Leaderboard:CompetitionLeaderboard` Live Component, whose state now lives in the URL)
(`:delta`, `:isNew`, variant `chip`) · `Layout/Nav` · `Layout/Footer` ·
`Competition/Card` (`:item` = `BrowsableCompetitionItem`, `context="organizer"|"public"`,
`:walletBalance` — **the** competition card, shared by the organizer and the public grid) ·
`Competition/FilterBar` (`prefix`, `anchor`, `:sportOptions`, `:stateOptions`, `:visibilityOptions` —
query-param driven; **no production call site since item 15**, rendered only in `/_design` half A where
it carries a „Bez použití" pill)
`Competition/PlayingCard` (`:item` = `PlayingCompetitionItem` — standing + next action).

**Live Components (`src/Twig/Components/`)**
`Competition/CreateWizard` (4-step wizard, `.docs/features/create-wizard.md`) ·
`Guess/GuessSubmitForm` (`:bare="true"` drops its card chrome — B19; the default ships `rounded-xl border …`, so an embedded call site must say `bare`, never re-neutralise it with utilities) · `Guess/MatchGuessesList` ·
`Boost/BoostPanel` (owned boosts render a **jump link** to what they unlocked, unowned a purchase CTA; both disappear once the competition is fully over — B6. Round 2: panel rows carry marketing headlines („Jak tipují ostatní?" / „Přesné tipy soupeřů" / „Počkejte si na sestavy") while **`BoostType::label()` still names the confirm dialog, the prémium toggles, `/cenik` and the ledger** — the headline is a label above the canonical product, not a rename) · `Notification/Bell` · `Notification/Preferences` · `CreditBalance` ·
`Profile/ProfileForm` · `Scoring/RuleFields` · `Auth/RegistrationForm`, `Auth/InvitationForm`,
`Auth/RequestPasswordResetForm`, `Auth/ResetPasswordForm` ·
`SoutezSwitcher` (`:competitions`, `currentId`, `route`, `:routeParams`, `param`, `label`, `id` —
the grouped soutěž picker; **`route` must be reachable with no path parameter carrying the
COMPETITION**, because the control is a plain GET `<form>` that can only append `?<param>=<id>`.
Any other path parameter of the route — e.g. the match id on `/zapasy/{id}` (item 10) — goes in
`:routeParams`, which is constant across every option. Groups „Probíhající" / „Ukončené",
each option = name + zdroj zápasů + Prague date range. Zero soutěží renders nothing, one
renders a static chip, an unknown id falls back to the first.
See [`.docs/features/competition-switcher.md`](../features/competition-switcher.md)).

**Every presentational component above is rendered live in half A of `/_design`** (item 13), through
its real tag off literal sample DTOs, so the gallery cannot drift from production. The **Live
Components are deliberately absent** — they need entities and DB queries, which that page forbids;
`Match/TipStats` covers the boost paywall a player actually meets.

**Partials** `_partials/competition_rules.html.twig`, `_partials/join_by_pin_form.html.twig`
(call sites since B15: **`/souteze`, `home.html.twig` and the Nástěnka empty state**), plus page-scoped ones next to
their template: `portal/_dashboard_match_row.html.twig`, `portal/_dashboard_leaderboard_row.html.twig`,
`portal/sport_match/_timeline.html.twig`.

---

## 4. Stimulus controllers (`assets/controllers/`)

`mobile_nav` · `confirm` (destructive submits — `.docs/features/confirm-modal.md`) ·
`confirm_recalculation` · `copy` · `credit_amount` · `datepicker` · `orderable_list` ·
`password_visibility` · `pin_input` · `reveal` · `score_entry` · `scorer_picker` ·
`scoring_preset` · `team_filter` · `team_picker` · `tip_fill` · `tom_select` ·
`competition_matches` · `wizard_matches` · `boost_intro`.

`reveal` **collapses, never hides** (B25): the server renders every item visible and the toggle `hidden`;
the controller collapses to the first N on connect and unhides the button, and restores the server shape
on disconnect. So JavaScript-off never removes content — it previously left the 6th+ match unreachable by
any means. Both call sites (competition detail, Nástěnka) use it.

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

Item 18 added a small shared pattern at the end of the component layer: `.card-clickable` +
`.card-stretch` (a link painted over a whole card, last in the DOM) + `.card-raise` (the one control
that must stay reachable above it) — the way a card becomes clickable as a whole **without nesting
interactive elements**.

Item 21 deleted the retired match-row shape from that layer (≈110 lines): the original 7-track `.tip-row`
grid and **both** `@media (max-width: 900px)` collapses, B7's four wrapping zones (`.tip-row-line`,
`.tip-row-when`, `.tip-row-teams`, `.tip-row-score`, `.tip-row-match`, `.tip-row-end`, `.tip-row-outcome`,
`.tip-row-actions`), the linked pilulka (`.tip-row-pill-link`) and the small „můj tip" box's dashed empty
state (`.my-tip.empty`, `a.my-tip*`). What survives is the card chrome + the five state stripes, `.my-tip*`
(now only in its `.is-bar` shape), `.result-tip*` (the Nástěnka's tip column, not the card), the footer
region (`.tip-row-extra`/`-boost-label`/`-note`), `.tip-stats-*` and item 18's `.tipd-*` block — which stays
scoped to `.tip-row.is-dash`, kept as the one card's hook rather than re-scoped for a cosmetic rename.

Rebuilt live by the `tailwind` container. **Never** run `asset-map:compile` locally.

---

## 6. Known IA pain points (observations, not decisions)

Listed so item files can reference them; each becomes a decision only when the product owner says so.

1. ~~**No dedicated „my competitions" list page.**~~ **Fixed by item 07**: `/souteze` is now the
   one place every relationship to a competition lives — plays in / organizes / could join —
   and „Tvé soutěže" is the organizer list the app never had. _(Item 06 kept a „Moje soutěže" grid
   on the Nástěnka on purpose: it is the one section the switcher does NOT scope, so the player's
   home still answers „where else do I play" — and every card links back into `/souteze`.)_
2. ~~**Competition detail is a 576-line monolith**~~ **Fixed by item 08** („Everything →
   Nastavení"): the detail page is now header + action bar + banner + match list + žebříček
   (with real rows) + boosts, and every organizer control lives on `/souteze/{id}/nastaveni`.
3. ~~**Two parallel object lists** (soutěže + zdroje zápasů) surfaced on the dashboard with equal
   weight, though a normal player never owns a zdroj.~~ **Fixed by item 06**: „Moje zdroje zápasů"
   is gone from the Nástěnka (a zdroj is an organizer object — it belongs to `/souteze` and the
   soutěž's Nastavení), and so are the PIN bar and „Objev další soutěže", which `/souteze` owns.
4. ~~**Žebříček nav item is soutěž-scoped** but presented as global.~~ _(Item 05: `/zebricek` is a real page in both nav variants, scoped by `?soutez` and public for global competitions.)_
5. ~~**Kredity is hidden** — reachable only from the avatar dropdown / mobile menu.~~
   **Fixed by item 17**: the balance is a chip in the top bar of every authenticated page and
   one tap from „Dobít kredity". It is absent for anonymous visitors and absent on the
   verification airlock (B14 stays chrome-free).
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
