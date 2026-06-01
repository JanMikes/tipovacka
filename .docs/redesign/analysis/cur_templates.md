I now have a complete picture. Here is the full catalog.

---

# Tipovačka — Current UI Catalog

Symfony app, Twig + Tailwind v4 (tokens defined via `@theme` in `assets/styles/app.css`, **no `tailwind.config.js`**), Hotwire Turbo present but disabled globally (`data-turbo="false"` on `<body>`), UX Live Components, UX Icons (lucide), Flatpickr, Tom Select, plus custom Stimulus controllers (`mobile-nav`, `password-visibility`, `reveal`, `confirm`, `confirm-recalculation`, `orderable-list`, `tom-select`, `datepicker`). Language is Czech throughout.

## Design tokens / global visual language

Defined in `assets/styles/app.css` (`@theme` block):
- **Navy scale** (`navy-50` `#eef2f9` → `navy-900` `#081e44`): primary text, dark surfaces, nav bar, footer, dark hero/gradient cards.
- **Cyan scale** (`cyan-100` `#d6f0fa`, `cyan-400` `#3eb5e6`, `cyan-500` `#149ad5`, `cyan-600` `#0f84b8`): CTA buttons, focus rings, links, highlights, active states.
- **`--shadow-card`** and **`--shadow-card-hover`** → utilities `shadow-card` / `shadow-card-hover` (soft navy-tinted shadows). Cards are universally `rounded-2xl bg-white shadow-card ring-1 ring-navy-900/5`.
- **`.hero-bg`** class (homepage hero): white→`#eaf2f9` linear gradient + cyan/navy radial blobs + masked dot-grid via `::before` + hairline `::after`.
- Recurring dark "feature/hero" surface: `bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700` with `pointer-events-none absolute ... rounded-full bg-cyan-500/15 blur-3xl` decorative blobs.
- Status pill convention (match states), reused on many pages: scheduled = `bg-blue-100 text-blue-800` ("Naplánován"); live = `bg-orange-100 text-orange-800 animate-pulse` ("Živě"); finished = `bg-green-100 text-green-800` ("Odehrán"); postponed = `bg-yellow-100 text-yellow-800` ("Odložen"); cancelled = `bg-gray-200 text-gray-800` ("Zrušen").
- CSS-driven form affordances: `label.required::after` red asterisk; `[aria-invalid="true"]` red border (set automatically by form theme). Flatpickr and Tom Select are reskinned to navy/cyan in the same CSS file.
- Container width convention: most pages `max-w-6xl` (some `max-w-5xl`/`max-w-4xl`/`max-w-3xl`/`max-w-2xl`/`max-w-md`); navbar/footer/admin use `max-w-[88rem]`; matrix uses `max-w-[min(100vw-2rem,120rem)]`.

---

## Layout / shell templates

### `templates/base.html.twig` — global shell
- Used by: virtually every page (directly or via `auth/_layout`, `admin/layout`).
- Surface: all (public/auth/portal/admin).
- Contents: full `<head>` with OG/Twitter meta blocks (`title`, `meta_description`, `og_title`, `og_description`, `twitter_title`, etc.), favicons, `theme-color #081e44`, `importmap('app')`.
- **Sticky top nav** (`header.sticky top-0 z-40 bg-navy-900 text-white`, `data-controller="mobile-nav"`): logo (`images/logo/logo-icon.png` + "Tipovačka"); desktop links computed from `nav_links` — authed: Nástěnka / Turnaje / Profil; guest: Domů / Turnaje; admin gets a cyan "Admin" pill (`lucide:shield-check`); active link underlined with `bg-cyan-500` bar. Logout/Login + cyan "Registrace" button. Mobile hamburger (`lucide:menu`/`lucide:x`) toggling a `data-mobile-nav-target="menu"` panel.
- **Flash messages**: `app.flashes` captured once; `flash_config` map keyed `success/error/warning/info` → icon + color classes; rendered as left-border alert rows inside `max-w-[88rem]`.
- `{% block body %}` for page content.
- **Footer** (`bg-navy-900 text-white/70`): 3-col grid — logo + tagline, "Navigace" links (Domů, Turnaje, Přihlášení/Registrace for guests, Ochrana soukromí), "O projektu" blurb, copyright line.
- Blocks: `title`, `meta_description`, `og_*`, `twitter_*`, `stylesheets`, `javascripts`, `importmap`, `body_class` (default `bg-navy-50/40`), `body`.

### `templates/auth/_layout.html.twig` — auth shell
- Extends base; `body_class` = `bg-white`. Centered card on white with three blurred cyan/navy radial accent blobs. Exposes `{% block auth_panel_tagline %}` (small centered copy above card) and `{% block auth_card %}` (required). Card width `max-w-md`.

### `templates/admin/layout.html.twig` — admin shell
- Extends base. Two-col grid `md:grid-cols-[260px_1fr]` inside `max-w-[88rem]`. **Left sidebar** card with "Administrace" header (shield icon) and nav: Turnaje (`lucide:trophy`), Skupiny (`lucide:users`), Uživatelé (`lucide:user`), Pravidla (`lucide:activity`) — active item `bg-navy-900 text-white` with cyan icon; plus a "Nový veřejný turnaj" link. **Right section**: breadcrumb `<ol>` (root "Admin" + `block('admin_breadcrumbs')`), header rendering `admin_title`/`admin_subtitle`/`admin_actions`, then `{% block admin_body %}`. Child overridable blocks: `admin_title`, `admin_subtitle`, `admin_actions`, `admin_breadcrumbs`, `admin_body`. NOTE: admin action buttons/breadcrumb separators use **raw inline `<svg>`** and `text-gray-*` (older style) rather than `twig:ux:icon` + navy tokens.

---

## PUBLIC pages

### `templates/home.html.twig`
- Controller/route: `HomeController` → `app_home`. Surface: public.
- `body_class bg-navy-900`. Sections: (1) **Hero** `.hero-bg`, 2-col grid, badge pill, big headline "Tipuj zápasy. / Poraz kamarády.", subcopy, two CTAs (cyan "Zaregistruj se zdarma" → `app_register`, outline "Procházet turnaje" → `public_tournaments_list`), trust-badge strip (`shield-check`/`users`/`list-ordered`/`clock`), desktop hero image `images/hero/hero-desktop.png`. (2) **Jak to funguje** (white) — 3 numbered step cards with `images/how-it-works/*.png`. (3) **Funkce** (`bg-navy-50`) — 3 feature cards w/ gradient icon tiles. (4) **Final CTA** (navy gradient + blur blobs) → `app_register`.
- Components/partials: none (only `twig:ux:icon`).

### `templates/public/tournaments_list.html.twig`
- Controller/route: `PublicTournamentsListController` → `public_tournaments_list`. Surface: public (but personalizes when logged in).
- Breadcrumb (inline). Header. For verified users: includes **`_partials/join_by_pin_form.html.twig`** (`size:'large'`, collapsible by `user_has_groups`). Section "Soukromé turnaje" (amber-accented cards, shown when `private_tournaments`), section "Veřejné turnaje" (cyan cards or `twig:EmptyState` illustration `search` with branching CTA for guest vs user), and a navy-gradient "Vytvoř si vlastní soukromý turnaj" CTA section. Cards are anchor cards → `public_tournament_detail`, with visibility/finished pills, owner, date range.
- Components/partials: `twig:EmptyState`, `_partials/join_by_pin_form`.

### `templates/public/tournament_detail.html.twig`
- Controller/route: `PublicTournamentDetailController` → `public_tournament_detail`. Surface: public.
- Inline breadcrumb. Navy-gradient **hero card** (visibility/finished pills, owner, dates, description). Two-column `lg:grid-cols-12` (8/4). **Left**: "Skupiny" section (`#groups`) — member rows link to `portal_group_detail`; non-members see Login / "Žádost čeká" / "Požádat o připojení" POST form / verify-email hint, gated by `is_granted('tournament_create_group')`, `canUsePinGate`, finished/verified flags. "Zápasy" section — match rows with status pills. **Right sidebar** (sticky): "O turnaji" `<dl>`, **`_partials/tournament_rules.html.twig`**, conditional compact PIN form, navy "Založ si vlastní skupinu" CTA, guest "Ještě nemáš účet?" card.
- Components/partials: `twig:EmptyState` (none here actually), `twig:ux:icon`, `_partials/tournament_rules`, `_partials/join_by_pin_form` (compact).

### `templates/public/privacy.html.twig`
- Controller/route: `PrivacyController` → `app_privacy`. Surface: public.
- `max-w-3xl` prose page: breadcrumb + h1 + 7 `<section>` blocks (data, visibility, sharing, password, DB, account deletion, contact `kontakt@tipovacka.cz` — has a `TODO` to add real contact). No components.

---

## AUTH pages (all extend `auth/_layout.html.twig`)

### `templates/auth/login.html.twig`
- Controller/route: `LoginController` → `app_login`. Surface: auth.
- **Hand-written** (non-component) form posting to `app_login`: email input w/ `lucide:mail` prefix icon; password field wrapped in `data-controller="password-visibility"` with eye toggle and "Zapomněli jste heslo?" link; "Zapamatovat si mě" checkbox; navy submit; `error.messageKey|trans(...,'security')` alert; footer link to register. Inputs use the same `rounded-lg border border-navy-100 ... focus:ring-cyan-500` pattern as the form theme but inline.

### `templates/auth/register.html.twig`
- Controller/route: `RegistrationController` → `app_register`. Surface: auth.
- Card with heading + tagline; renders **`<twig:Auth:RegistrationForm />`** (live component); footer link to login.

### `templates/auth/password_reset_request.html.twig`
- Route: `app_forgot_password_request` (`PasswordResetRequestController`). Renders **`<twig:Auth:RequestPasswordResetForm />`**.

### `templates/auth/password_reset.html.twig`
- Route: `app_reset_password` (`PasswordResetController`). Renders **`<twig:Auth:ResetPasswordForm />`**.

### `templates/auth/password_reset_check_email.html.twig`
- Route: `app_check_email` (`PasswordResetCheckEmailController`). Static confirmation card (green `lucide:mail` tile, "Zkontroluj svoji schránku", link back to login).

### `templates/auth/verify_pending.html.twig`
- Route: `app_verify_email_pending` (`VerifyEmailPendingController`). Cyan `lucide:mail` tile + copy; conditional POST resend form (`app_resend_verification_email`, CSRF `resend_verification`).

### `templates/auth/verify_error.html.twig`
- Route: rendered by `VerifyEmailController` on failure (`app_verify_email`). Red `lucide:triangle-alert` tile; `errorMessage`; conditional "Vyžádat nový ověřovací e-mail" → `app_verify_email_pending` (when `showResend`); back-to-login link.

---

## INVITATION pages

### `templates/invitation/landing.html.twig`
- Controller/route: `JoinByShareableLinkController` (`group_join_by_link`) / `AcceptEmailInvitationController` (`group_accept_invitation`). Surface: auth (extends `auth/_layout`).
- State machine on `step`: `invalid` / `expired` / `revoked` / `accepted` / `tournament_finished` / `email_mismatch` (each a centered icon-tile message, some with logout/login actions) / else **active** state: cyan info banner naming group/tournament/inviter + **`<twig:Auth:InvitationForm kind="..." token="..." />`** + "mám už účet" link.

---

## PORTAL pages (player + organizer; all extend `base.html.twig` unless noted)

### `templates/portal/dashboard.html.twig`
- Controller/route: `DashboardController` → `portal_dashboard`. Surface: portal (player-facing landing).
- Header greeting `app.user.nickname`. Includes **`_partials/join_by_pin_form`** (collapsible if user has groups). Sections: **Moje turnaje** (owned, anchor cards w/ visibility pills), **Moje soutěže** (`my_groups` cards or `twig:EmptyState` illustration `tournaments`), **Nadcházející zápasy** (`data-controller="reveal"` show-more list of 5; per-match "Chybí tip"/"Tip odeslán" badges; "Tipovat" CTA → `portal_sport_match_detail`), **Moje vyhodnocené tipy** (reveal list; points pill, "Přesně" badge), **Objev další turnaje** (`discoverable_tournaments` cards → `public_tournament_detail`).
- Components/partials: `twig:EmptyState`, `_partials/join_by_pin_form`.

### `templates/portal/group/detail.html.twig`
- Controller/route: `GroupDetailController` → `portal_group_detail`. Surface: portal (player + organizer; heavy organizer tooling).
- `twig:Breadcrumbs`. Hero header (name + "Turnaj ukončen" pill, tournament link, owner). Optional description card. Two-col `lg:grid-cols-12`. **Left**: cyan "Tipnout všechny zápasy najednou" CTA (members, not finished) → `portal_group_my_tips_batch`; **Členové** `<details>` accordion (avatar initials, owner/anonymous pills, rank/points, organizer actions: "Přidat e-mail" → promote, "Odebrat" via `confirm` controller); **Moje tipy** accordion (rows → `portal_sport_match_detail`, status pills); **Žádosti o připojení** (public tournaments, organizer) approve/reject POST forms; **Pozvánky e-mailem** (organizer) `form_start(invitationForm)`, optional `bulkInvitationForm` in `<details>`, "Přidat tipujícího bez e-mailu" link, pending-invitation list w/ revoke. **Right sidebar**: navy "Žebříček" CTA → `portal_group_leaderboard`; **`_partials/tournament_rules`**; **Rychlé pozvánky** (PIN `<code>` + regenerate/revoke; shareable link `<code>` via `url('group_join_by_link')` + regenerate/revoke; all using `confirm` controller); **Správa** (edit, batch tips, "Tipovat za členy", leave, delete — voter-gated).
- Components/partials: `twig:Breadcrumbs`, `_partials/tournament_rules`, raw Symfony forms.

### `templates/portal/group/create.html.twig`
- Route `portal_group_create` (`CreateGroupController`). Surface: portal/organizer. `max-w-2xl`. `twig:Breadcrumbs`; raw `form_start` with optional purple PIN-gate block (`pinGate`), name/description, `withPin` checkbox, "Tipy a uzávěrka" section (`hideOthersTipsBeforeDeadline` checkbox + `tipsDeadline` flatpickr). Submit/cancel.

### `templates/portal/group/edit.html.twig`
- Route `portal_group_edit` (`UpdateGroupController`). Same structure as create minus PIN-gate; `withPin` may be disabled.

### `templates/portal/group/join_by_pin.html.twig`
- Route `portal_group_join_by_pin` (`JoinByPinController`). `max-w-md` single PIN field form (mono, tracked) + submit.

### `templates/portal/group/add_anonymous_member.html.twig`
- Route `portal_group_add_anonymous_member` (`AddAnonymousMemberController`). Surface: organizer. `max-w-2xl` form: firstName/lastName/nickname; explains anonymous-member flow.

### `templates/portal/group/promote_anonymous_member.html.twig`
- Route `portal_group_promote_anonymous_member` (`PromoteAnonymousMemberController`). Surface: organizer. `max-w-2xl` single email field to convert anonymous member → invited account.

### `templates/portal/group/my_tips_batch.html.twig`
- Route `portal_group_my_tips_batch` (`MyTipsBatchController` GET / `SubmitMemberTipsBatchController` POST). Surface: player. `max-w-3xl pb-32`. Plain POST form, list of upcoming matches each with two `<input type=number>` home/away score; **fixed bottom action bar** ("Uložit vše", `lucide:save`). Empty state inline.

### `templates/portal/group/manage_member_tips.html.twig`
- Route `portal_group_manage_member_tips` (`ManageMemberTipsController` / `SubmitGuessOnBehalfController`). Surface: organizer. Like my_tips_batch but with a **Tom Select member picker** (`data-controller="tom-select"`, submit-on-change, custom option `data-data` JSON with nickname/fullName/unverified) at top; then the batch score grid for the selected member + fixed bottom save bar.

### `templates/portal/guess/detail.html.twig`
- Route `portal_group_sport_match_guesses` (`SportMatchGuessesController`). Surface: player + organizer. `max-w-4xl`. `twig:Breadcrumbs`; navy-gradient **match hero** (status pill, kickoff, effective deadline line, teams + score). Renders **`<twig:Guess:GuessSubmitForm>`** and **`<twig:Guess:MatchGuessesList applyHiding=...>`**. Optional per-match deadline form (`deadline_form`). Organizer "Tipy členů" section: per-member inline `home:away` POST forms (`portal_group_guess_on_behalf`), disabled when not editable.
- Components: `twig:Breadcrumbs`, `twig:Guess:GuessSubmitForm`, `twig:Guess:MatchGuessesList`.

### `templates/portal/leaderboard/index.html.twig`
- Route `portal_group_leaderboard` (`GroupLeaderboardController`). Surface: player. `max-w-5xl`. `twig:Breadcrumbs`. Optional **winner celebration section** (responsive `<picture>` `images/winner/winner-*.png` + "Gratulujeme" when tournament finished). Header w/ "Tabulka" link (`portal_group_leaderboard_matrix`) and organizer "Rozřadit shody" link. Renders **`component('Leaderboard:GroupLeaderboard', {group})`**.

### `templates/portal/leaderboard/member.html.twig`
- Route `portal_group_leaderboard_member` (`MemberBreakdownController`). Surface: player. `max-w-5xl`. `twig:Breadcrumbs`. Header w/ avatar initial + total-points navy chip. Per-match breakdown table (Zápas / Výsledek / Tvůj tip / Detail rule-by-rule list / Body).

### `templates/portal/leaderboard/matrix.html.twig`
- Route `portal_group_leaderboard_matrix` (`GuessMatrixController`). Surface: player. Widest container `max-w-[min(100vw-2rem,120rem)]`. `twig:Breadcrumbs`. Legend (1./2./3. místo color swatches). **Sticky-header, sticky-first-column scrollable matrix table**: rows = members (rank, name → member page, points), columns = matches (abbrev `homeTeam|slice(0,3)` + score/live/postponed/date); cells show tip + points with cyan top-score highlights or `lucide:lock` when hidden. Empty-state branch.

### `templates/portal/leaderboard/resolve_ties.html.twig`
- Route `portal_group_leaderboard_resolve_ties` (`ResolveTiesController`). Surface: organizer. `max-w-3xl`. `twig:Breadcrumbs`. **Drag-and-drop ordering** (`data-controller="orderable-list"`): each tie bucket is a draggable `<ul>`; hidden `form.orderedUserIds` synced; "Uložit pořadí" submit. NOTE uses older `bg-gray-50/100` styling on draggable rows.

### `templates/portal/profile/edit.html.twig`
- Route `portal_profile_edit` (`ProfileController`). Surface: player. `max-w-4xl`. `twig:Breadcrumbs`. 3-col grid: read-only "Účet" card (nickname/email), "Osobní údaje" card rendering **`<twig:Profile:ProfileForm />`**, and red "Nebezpečná zóna" sidebar → `portal_account_delete`.

### `templates/portal/profile/delete_confirm.html.twig`
- Route `portal_account_delete` (`AccountDeleteController`). Surface: player. `max-w-md` centered red-warning card; POST form (CSRF `delete_account`) "Ano, smazat můj účet" + cancel.

### `templates/portal/sport_match/detail.html.twig`
- Route `portal_sport_match_detail` (`SportMatchDetailController`). Surface: player + organizer. `max-w-5xl`. `twig:Breadcrumbs`. Navy-gradient hero (status pill, kickoff, teams/score, venue). **Inline per-group guess cards** (`my_groups_for_tournament`): each card has a yellow "Nevyplněno" badge + ring when no guess, header link to group + "Tipy členů" link, and an embedded **`<twig:Guess:GuessSubmitForm>`** (styled bare via `!rounded-none !bg-transparent ...`). **Match management** section (voter-gated): Upravit, Zadat/Upravit skóre, Odložit (inline datepicker POST), Přesunout zpět (reschedule), Zrušit zápas (confirm), Smazat (confirm).
- Components: `twig:Breadcrumbs`, `twig:Guess:GuessSubmitForm`.

### `templates/portal/sport_match/form.html.twig`
- Routes `portal_sport_match_create` / `portal_sport_match_edit` (`CreateSportMatchController` / `UpdateSportMatchController`); `mode` toggles create/edit. Surface: organizer. `max-w-2xl`. `twig:Breadcrumbs` (branching). Form: homeTeam, awayTeam, kickoffAt (datepicker), venue. Submit/cancel. NOTE: bare inputs `border border-navy-100 rounded-lg` (no focus ring classes — relies less on the polished pattern).

### `templates/portal/sport_match/set_score.html.twig`
- Route `portal_sport_match_set_score` (`SetFinalScoreController`). Surface: organizer. `max-w-lg`. `twig:Breadcrumbs`. 2-col homeScore/awayScore number inputs + save/cancel.

### `templates/portal/sport_match/import.html.twig`
- Routes `portal_sport_match_import` (`BulkImportPreviewController`) + commit `portal_sport_match_import_commit` (`BulkImportCommitController`) + template `portal_sport_match_template_download`. Surface: organizer. `max-w-3xl`. `twig:Breadcrumbs`. Upload card (column spec, "Stáhnout šablonu (CSV)" link, multipart file form). **Preview** block: error list (red), valid-rows table, "Potvrdit import (N zápasů)" green button or error guidance. NOTE uses `text-gray-*`/`bg-gray-50` in places.

### `templates/portal/tournament/detail.html.twig`
- Route `portal_tournament_detail` (`TournamentDetailController`). Surface: portal/organizer (owner view of own tournament). `max-w-6xl`. `twig:Breadcrumbs`. Navy-gradient hero (visibility/finished pills). Two-col `lg:grid-cols-12`. **Left**: "Zápasy" section (organizer "Přidat zápas"/"Nahrát z Excelu" buttons; `twig:EmptyState` illustration `matches` with secondary CTA; else match rows → detail). "Skupiny" section (create-group CTA / PIN-gate; rows → group detail). **Right sidebar**: `_partials/tournament_rules`; "Správa turnaje" (Upravit, Pravidla, Ukončit (confirm warning), Smazat (confirm)) voter-gated.
- Components/partials: `twig:Breadcrumbs`, `twig:EmptyState`, `_partials/tournament_rules`.

### `templates/portal/tournament/create_private.html.twig`
- Route `portal_tournament_create` (`CreatePrivateTournamentController`). Surface: portal/organizer. `max-w-2xl`. `twig:Breadcrumbs`. Form: name, description, startAt/endAt (datepickers), optional `creationPin` (mono). Submit.

### `templates/portal/tournament/edit.html.twig`
- Route `portal_tournament_edit` (`UpdateTournamentController`). Same shape as create_private + breadcrumb to detail; "Uložit změny".

### `templates/portal/tournament/rule_configuration.html.twig`
- Route `portal_tournament_rule_configuration` (`TournamentRuleConfigurationController`). Surface: organizer. `max-w-3xl`. `twig:Breadcrumbs`. Form `data-controller="confirm-recalculation"` (warns if `evaluationCount` guesses already scored). Per-rule cards (label, description, default points, `enabled` checkbox, `points` input). Save/back. NOTE: uses `text-gray-*` and `bg-gray-100` for the back button.

---

## ADMIN pages (all extend `admin/layout.html.twig`)

> Visual note: admin templates are an **older style** — raw inline `<svg>` icons (not `twig:ux:icon`), `text-gray-*`/`text-cyan-500` link colors, `bg-navy-50/40` table headers. Tables share a pattern: `bg-white rounded-2xl shadow-card ring-1 ring-navy-900/5 overflow-hidden` → `overflow-x-auto` → `<table>` with `thead.bg-navy-50/40` and `tbody.divide-y divide-navy-100/70`, `{% else %}` empty row.

### `templates/admin/tournament/list.html.twig`
- Route `admin_tournament_list` (`ListTournamentsController`). Table: Název/Viditelnost/Sport/Vlastník/Skupiny/Stav/Akce. Row actions: Otevřít, Upravit, Pravidla, Zápasy, Skupiny, Ukončit (confirm), Smazat (confirm). Active/finished/deleted styling. Header action "Nový turnaj".

### `templates/admin/tournament/create_public.html.twig`
- Route `admin_tournament_create` (`CreatePublicTournamentController`). Form card (name/description/startAt/endAt) "Vytvořit turnaj".

### `templates/admin/tournament/edit.html.twig`
- Route `admin_tournament_edit` (`AdminUpdateTournamentController`). Same form + optional `creationPin`; "Uložit změny".

### `templates/admin/tournament/rule_configuration.html.twig`
- Route `admin_tournament_rule_configuration` (`AdminRuleConfigurationController`). Per-rule cards with `confirm-recalculation`. (Has a stray double class `bg-navy-50/40/40`.)

### `templates/admin/group/list.html.twig`
- Route `admin_group_list` (`ListGroupsController`). Table Název/Turnaj/Vlastník/Členové/Akce; Detail link + Smazat (confirm, `admin_group_delete` → `AdminDeleteGroupController`).

### `templates/admin/user/list.html.twig`
- Route `admin_user_list` (`ListUsersController`). Top **filter form** card (search/verified/active). Table E-mail/Uživatel/Role/Stav/Akce; anonymous handling; status pills (neověřený/zablokovaný/aktivní); actions: "Přihlásit se jako" (`_switch_user`), Zablokovat (confirm)/Odblokovat (`admin_user_block`/`admin_user_unblock`).

### `templates/admin/rule/list.html.twig`
- Route `admin_rule_list` (`ListRulesController`). Read-only table Identifikátor/Název/Popis/Výchozí body.

### `templates/admin/sport_match/list.html.twig`
- Route `admin_sport_match_list` (`ListMatchesController`). Table Zápas/Výkop/Místo/Stav/Skóre/Akce (Detail/Upravit → portal routes). Header actions Nový zápas / Nahrát z Excelu (raw SVG icons). NOTE: state shown as raw `match.state.value` (no pill mapping).

---

## ERROR pages (`templates/bundles/TwigBundle/Exception/`)

`error.html.twig`, `error404.html.twig`, `error403.html.twig`, `error500.html.twig` — all extend base, surface = public/system. **Distinctly off-brand / legacy**: emoji headers (⚠️ 🔍 🚫 💥), `bg-gray-50`, and undefined utility classes **`card` / `card-body` / `btn btn-primary` / `btn-secondary` / `btn-ghost`** (these CSS classes are NOT defined in `app.css`, so they render unstyled). 404 references route `app_profile` and 403/404 reference `app_login`. Good migration candidates (broken styling).

---

## Reusable Twig Components (`templates/components/`) + PHP (`src/Twig/Components/`)

All eight component PHP classes are **`#[AsLiveComponent]`** (Symfony UX Live Components); the form ones use `ComponentWithFormTrait` + `DefaultActionTrait`. `Breadcrumbs` and `EmptyState` are template-only (no PHP class — pure `{% props %}` components).

### `components/Auth/RegistrationForm.html.twig` (`Auth:RegistrationForm`)
- PHP `src/Twig/Components/Auth/RegistrationForm.php` — extends `AbstractController`, `#[LiveAction] register()`, dispatches `RegisterUserCommand`, catches `UserAlreadyExists`/`NicknameAlreadyTaken`.
- Renders `RegistrationFormType` (`RegistrationFormData`) live (`data-model on(change)`). Fields: email, firstName/lastName (2-col), nickname (+helper), password (with `password-visibility` toggle), confirm, gdprConsent checkbox (links to `app_privacy`). Cyan submit "Vytvořit účet".

### `components/Auth/InvitationForm.html.twig` (`Auth:InvitationForm`)
- PHP `.../Auth/InvitationForm.php` — `#[LiveProp]` `kind`, `token`; virtual `userKind` (`new`/`has_password`/`stub`) + `emailLocked` derived from `InvitationContext`; branches login vs complete-registration vs new-account. Submit text varies per kind. Uses `InvitationFormData::KIND_*` constants.

### `components/Auth/RequestPasswordResetForm.html.twig` (`Auth:RequestPasswordResetForm`)
- Single email field live form → "Odeslat odkaz pro obnovení" (navy button).

### `components/Auth/ResetPasswordForm.html.twig` (`Auth:ResetPasswordForm`)
- `newPassword.first`/`second` (RepeatedType) with `password-visibility` toggle → cyan "Uložit nové heslo".

### `components/Guess/GuessSubmitForm.html.twig` (`Guess:GuessSubmitForm`)
- PHP `.../Guess/GuessSubmitForm.php` — `#[LiveProp]` `sportMatch`, `groupId`; `#[LiveProp(writable:true)]` `homeScore`/`awayScore`; computed `isLocked`/`existingGuess`/`hasExistingGuess`; `#[LiveAction] submit()`; `errorMessage`/`successMessage`. Renders locked state (shows existing score) OR a two number-input `home:away` live form. Button label morphs: "Odeslat tip" / "Upravit tip" / red "Smazat tip" (when clearing). Default wrapper `rounded-xl bg-white p-4 shadow-card`; overridable via `attributes.defaults`.

### `components/Guess/MatchGuessesList.html.twig` (`Guess:MatchGuessesList`)
- PHP `.../Guess/MatchGuessesList.php` — `#[LiveProp]` sportMatch/groupId/applyHiding. Table: Tipující / Tip / Odesláno; "(ty)" marker, `bg-cyan-50/50` row highlight for self; hidden tips show `lucide:lock` "skryto" pill.

### `components/Leaderboard/GroupLeaderboard.html.twig` (`Leaderboard:GroupLeaderboard`)
- PHP `.../Leaderboard/GroupLeaderboard.php` — `#[LiveProp]` group. Standings table Pořadí/Hráč/Body; tie-override "(R)" marker; names link to member breakdown. Empty → `twig:EmptyState` illustration `leaderboard` (with "Pozvat kamarády" CTA for managers). NOTE: default wrapper uses older `rounded-2xl shadow-lg p-6` and table uses `text-gray-500`/`border-gray-200` (slightly off the `shadow-card`/navy convention).

### `components/Profile/ProfileForm.html.twig` (`Profile:ProfileForm`)
- PHP `.../Profile/ProfileForm.php` — `#[LiveAction] save()`. firstName/lastName (2-col) + phone → navy "Uložit změny".

### `components/Breadcrumbs.html.twig` (template-only, `{% props items %}`)
- Used by ~all portal pages. Mobile: single "← parent" back link; desktop: full chevron trail. `items` = list of `{label, path?}`, last = current page.

### `components/EmptyState.html.twig` (template-only, `{% props illustration, title, body, ctaLabel, ctaPath, secondaryLabel, secondaryPath %}`)
- Centered title/body + optional cyan primary CTA + secondary link. `illustration` prop is accepted but **currently not rendered** (no image/SVG output — just a documented enum `leaderboard|tournaments|matches|search`).

---

## Shared partials (`templates/_partials/`)

### `_partials/join_by_pin_form.html.twig`
- Params `size` (`large`|`compact`) + `collapsible`. Posts to `portal_group_join_by_pin_quick` (CSRF `join_by_pin_quick`, `redirect_to` hidden). Three render modes: compact sidebar form; collapsible navy-gradient `<details>` ("Máš PIN od skupiny?"); full navy-gradient banner with 8-digit mono PIN input. Used by dashboard, public tournaments list, public tournament detail.

### `_partials/tournament_rules.html.twig`
- Read-only scoring-rules card (filters `enabled` items): "Pravidla bodování" with per-rule label + "N b." pill. Used by public tournament detail, portal tournament detail, portal group detail.

---

## Form theme (`templates/form/_form_theme.html.twig`)
- `{% use 'form_div_layout.html.twig' %}`. Overrides: `form_errors` (red `<ul>`), `form_label` (adds default label classes; suppresses per-option `required` asterisk for checkbox/radio), `choice_widget_expanded` (flex wrap), `date/datetime/time_widget` (auto-wire `datepicker` Stimulus controller when `single_text`), `form_widget_simple`/`textarea_widget`/`choice_widget_collapsed` (default `w-full ... rounded-lg focus:ring-cyan-500` + `aria-invalid` on errors), `checkbox_widget`/`radio_widget` (cyan accent), `button_widget` (default navy button). Per-field `attr.class` overrides win via `|default`.

---

## Email templates (`templates/emails/`) — surface: email
All are **standalone table-based HTML emails** (not extending base), `#081e44` navy header bar, `#149AD5` cyan or navy CTA buttons, `#f3f4f6` page bg, white rounded card, gray footer with `© {{ "now"|date("Y") }}` copyright.

- **`welcome.html.twig`** — vars `nickname`, `loginUrl`. "Vítejte v Tipovačce, {nickname}!" + cyan "Přihlásit se".
- **`verify_email.html.twig`** — vars `nickname`, `verificationUrl`, `expiresAt`. Navy "Ověřit e-mailovou adresu" + fallback link.
- **`password_reset.html.twig`** — vars `userEmail`, `resetUrl`. Navy "Obnovit heslo" + 1h validity + fallback link.
- **`group_invitation.html.twig`** — vars `inviterNickname`, `groupName`, `tournamentName`, `invitationUrl`, `expiresAt`. Navy "Přijmout pozvánku" + fallback.
- **`join_request_approved.html.twig`** — vars `nickname`, `groupName`, `tournamentName`, `groupUrl`. **Simpler/inline-styled** (no card table wrapper) — cyan "Otevřít skupinu" button. Good consolidation candidate (diverges from the shared email shell).

---

## Flat migration checklist — ALL distinct page templates

**Layouts / shells (3)** — refactor first, everything inherits:
- `base.html.twig`
- `auth/_layout.html.twig`
- `admin/layout.html.twig`

**Public (4):**
- `home.html.twig` — public
- `public/tournaments_list.html.twig` — public
- `public/tournament_detail.html.twig` — public
- `public/privacy.html.twig` — public

**Auth (7):**
- `auth/login.html.twig` — auth (hand-written form)
- `auth/register.html.twig` — auth
- `auth/password_reset_request.html.twig` — auth
- `auth/password_reset.html.twig` — auth
- `auth/password_reset_check_email.html.twig` — auth
- `auth/verify_pending.html.twig` — auth
- `auth/verify_error.html.twig` — auth

**Invitation (1):**
- `invitation/landing.html.twig` — auth/invitation

**Portal — player-facing (8):**
- `portal/dashboard.html.twig`
- `portal/group/join_by_pin.html.twig`
- `portal/group/my_tips_batch.html.twig`
- `portal/guess/detail.html.twig`
- `portal/leaderboard/index.html.twig`
- `portal/leaderboard/member.html.twig`
- `portal/leaderboard/matrix.html.twig`
- `portal/profile/edit.html.twig`
- `portal/profile/delete_confirm.html.twig`

**Portal — organizer (owner/manager) (12):**
- `portal/group/detail.html.twig` (mixed player+organizer)
- `portal/group/create.html.twig`
- `portal/group/edit.html.twig`
- `portal/group/add_anonymous_member.html.twig`
- `portal/group/promote_anonymous_member.html.twig`
- `portal/group/manage_member_tips.html.twig`
- `portal/leaderboard/resolve_ties.html.twig`
- `portal/sport_match/detail.html.twig` (mixed player+organizer)
- `portal/sport_match/form.html.twig`
- `portal/sport_match/set_score.html.twig`
- `portal/sport_match/import.html.twig`
- `portal/tournament/detail.html.twig`
- `portal/tournament/create_private.html.twig`
- `portal/tournament/edit.html.twig`
- `portal/tournament/rule_configuration.html.twig`

**Admin (8):**
- `admin/tournament/list.html.twig`
- `admin/tournament/create_public.html.twig`
- `admin/tournament/edit.html.twig`
- `admin/tournament/rule_configuration.html.twig`
- `admin/group/list.html.twig`
- `admin/user/list.html.twig`
- `admin/rule/list.html.twig`
- `admin/sport_match/list.html.twig`

**Error / system (4)** — currently broken styling (undefined `card`/`btn` classes), high-priority:
- `bundles/TwigBundle/Exception/error.html.twig`
- `bundles/TwigBundle/Exception/error404.html.twig`
- `bundles/TwigBundle/Exception/error403.html.twig`
- `bundles/TwigBundle/Exception/error500.html.twig`

**Reusable components (10)** — shared, migrate to propagate styling:
- `components/Auth/RegistrationForm.html.twig` (+ PHP)
- `components/Auth/InvitationForm.html.twig` (+ PHP)
- `components/Auth/RequestPasswordResetForm.html.twig` (+ PHP)
- `components/Auth/ResetPasswordForm.html.twig` (+ PHP)
- `components/Guess/GuessSubmitForm.html.twig` (+ PHP)
- `components/Guess/MatchGuessesList.html.twig` (+ PHP)
- `components/Leaderboard/GroupLeaderboard.html.twig` (+ PHP) — uses off-convention `shadow-lg`/gray table
- `components/Profile/ProfileForm.html.twig` (+ PHP)
- `components/Breadcrumbs.html.twig` (template-only)
- `components/EmptyState.html.twig` (template-only; `illustration` prop accepted but not rendered)

**Shared partials (2):**
- `_partials/join_by_pin_form.html.twig`
- `_partials/tournament_rules.html.twig`

**Form theme (1):**
- `form/_form_theme.html.twig`

**Email (5):**
- `emails/welcome.html.twig`
- `emails/verify_email.html.twig`
- `emails/password_reset.html.twig`
- `emails/group_invitation.html.twig`
- `emails/join_request_approved.html.twig` (diverges from shared email shell)

**Cross-cutting inconsistencies worth flagging for any redesign:** (1) admin templates + error pages + several portal forms (`sport_match/form`, `import`, `rule_configuration`, `resolve_ties`) use legacy `text-gray-*`/`bg-gray-*` and raw `<svg>` instead of the navy/cyan + `twig:ux:icon` system used everywhere else; (2) error pages reference undefined `card`/`btn` CSS classes (render unstyled); (3) `Leaderboard:GroupLeaderboard` uses `shadow-lg`/gray rather than `shadow-card`/navy; (4) `EmptyState`'s `illustration` is dead; (5) login form is hand-written (not a component) while register/reset are; (6) stray `bg-navy-50/40/40` typo in `admin/tournament/rule_configuration.html.twig`.