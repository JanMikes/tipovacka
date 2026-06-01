# 03 — Phased execution plan

Six phases, strictly ordered (1 → 6). Within a phase, screens are mostly
independent and can be parallelized across subagents. The per-screen mapping +
complexity is in [`analysis/_gap.md`](analysis/_gap.md) §1; this file is the
execution checklist.

**Golden gate after every phase:** `docker compose exec web composer quality`
must be green. See §"Verification & CI gates" below. Append anything you changed
vs this plan to §"Deviations log" at the bottom.

---

## How subagents should run this (orchestration model)

Run **one phase at a time, in order.** Phase 1 is a single coherent change (one
agent, or a tightly-coordinated pair) because everything shares `app.css`/base
layout. Phases 2–6 fan out: one subagent per screen (or per small cluster of
related screens), because re-skins are mostly independent template edits.

Recommended loop per phase:
1. **Read** the relevant `analysis/*.md` sections + the target template(s) +
   the DS reference page/component.
2. **Implement** the re-skin using the Twig components (`02`) + utility classes.
   Reuse components; do not re-invent the tip card / leaderboard / nav per page.
3. **Self-check** against the screen's acceptance bullets below.
4. **Run `composer quality`**; fix until green.
5. **Verify visually** if possible (`/run` skill or the `verify` skill) — at
   minimum confirm the page renders dark with no light-theme leftovers
   (`grep` the template for `bg-white`, `text-navy-900`, `text-gray-`, `bg-gray-`,
   `shadow-card` used as light — replace all).
6. Mark the screen done in this checklist (edit the box to `[x]`).

A pragmatic parallel pattern (if using the Workflow tool): `pipeline` the screen
list — stage 1 = re-skin the template, stage 2 = adversarially verify (a second
agent greps for light-theme leftovers + checks acceptance + runs a targeted
render). Keep concurrency modest so `composer quality` runs don't thrash.

Per-screen "definition of done":
- Uses Wtips components/classes; **zero** hard-coded light classes
  (`bg-white`, `text-navy-900`, `text-gray-*`, `bg-gray-*`, light `shadow-card`).
- Czech copy rules honored (vykání, sentence case, decimal comma, no emoji).
- All referenced Lucide icons imported.
- `composer quality` green.

---

## Phase 1 — Foundation
**Owner: single coordinated change.** Full spec in `01-foundation.md`.
Deliverables: dark `@theme`; `--grad-canvas` body; self-hosted Montserrat;
`@layer components`; vendor skin reskins + JS class fixes; dead-code cleanup;
brand-name config; rebuilt `base.html.twig` (nav + footer + flash via `02`
components). Build the **Foundation** component group (`Nav`, `Footer`, `Button`,
`Pill`, `Badge`, `FlashMessages`).

- [ ] `templates/base.html.twig` — dark shell, `<twig:Layout:Nav>` +
      `<twig:Layout:Footer variant="app|marketing">`, reskinned flash, fonts,
      theme-color `#0f1726`, config-driven brand.
Gate: app boots dark; `composer quality` green; no Google Fonts request.

---

## Phase 2 — Errors + Emails (quick wins, low risk)

### Error pages (currently BROKEN — undefined `card`/`btn` classes, emoji headers)
- [ ] `bundles/TwigBundle/Exception/error.html.twig`
- [ ] `…/error404.html.twig` (verify the `app_profile` route ref still exists)
- [ ] `…/error403.html.twig`
- [ ] `…/error500.html.twig`
Acceptance: dark glass card, Lucide icon (no emoji), `.btn` CTA back home;
renders styled (they currently render unstyled).

### Emails (keep LIGHT — do NOT apply the dark theme; email dark-mode is unreliable)
Re-brand only (Wtips name/`brand_name`, accent `#4699d0`, logo), keep
table-based light layout.
- [ ] `emails/welcome.html.twig`
- [ ] `emails/verify_email.html.twig`
- [ ] `emails/password_reset.html.twig`
- [ ] `emails/group_invitation.html.twig`
- [ ] `emails/join_request_approved.html.twig` (consolidate toward the shared
      shell while re-branding)

---

## Phase 3 — Public + Auth + Invitation (highest external visibility)

### Public / marketing
- [ ] `home.html.twig` — **rebuild** to the DS landing "Bold sport"
      (`analysis/ds_pages.md` §2): HERO (gradient/strike headline + glass
      live-match card **as marketing decoration** + floating mini-cards), JAK TO
      FUNGUJE (3 steps), PROČ WTIPS (6 features), UKÁZKA APLIKACE (static
      screenshot of the app — **not** a live iframe), FINAL CTA card. Marketing
      nav/footer. Drop the old `hero-desktop.png`/step PNGs.
- [ ] **NEW** `Funkce` page + route + controller (`Public\FeaturesController`).
- [ ] **NEW** `Ceník` page — describe Free + paid plans **narratively** (no
      payment backend). („5 hráčů zdarma navždy", placené plány „od 99 Kč
      měsíčně" as copy.)
- [ ] **NEW** `Pro firmy` page (B2B narrative).
- [ ] **NEW** `FAQ` page (accordion; can reuse a `reveal`/`<details>` pattern).
- [ ] `public/tournaments_list.html.twig` — DS pool/discovery card grid (dark
      glass cards, player/group counts, dates) + PIN card.
- [ ] `public/tournament_detail.html.twig` — gradient hero + dark surfaces +
      rules card; groups/matches sections re-skinned.
- [ ] `public/privacy.html.twig` — dark prose; fix contact email via brand config.

### Auth (all extend `auth/_layout.html.twig`) — **NO social login**
- [ ] `auth/_layout.html.twig` — 2-col `.auth-wrap` (left value-prop with
      `PinInput` + live-pill + quick-stats; right glass `.auth-card`), dark navy +
      radial glows + **fixed stadium photo** (host it locally or use the
      `.bg-radial` glow only if no asset — do not hotlink Unsplash in prod). The
      register variant is a centered column.
- [ ] `auth/login.html.twig` — DS login **minus** Google/Apple + the „nebo
      e-mailem" divider. Email + password (eye toggle via existing
      `password-visibility` controller) + remember-me + „Zapomenuté heslo?".
      Footer „Nemáš účet? Vytvoř si ho zdarma". (This template is hand-written,
      not a component — restyle in place.)
- [ ] `auth/register.html.twig` (renders `Auth:RegistrationForm`) — DS paired
      2-col fields (email+přezdívka, jméno+příjmení, heslo+heslo znovu) + GDPR
      checkbox. **No OAuth buttons.** Maps to existing `RegistrationFormData`.
- [ ] `auth/password_reset_request.html.twig` (`Auth:RequestPasswordResetForm`)
- [ ] `auth/password_reset.html.twig` (`Auth:ResetPasswordForm`)
- [ ] `auth/password_reset_check_email.html.twig` (status card)
- [ ] `auth/verify_pending.html.twig` (status card)
- [ ] `auth/verify_error.html.twig` (status card)

### Invitation
- [ ] `invitation/landing.html.twig` (renders `Auth:InvitationForm`) — multi-state
      status cards (invalid/expired/revoked/accepted/finished/mismatch/active) →
      dark glass. **Ensure `lucide:flag` imported** (currently throws).

### Auth Live Components (re-skin templates only)
- [ ] `components/Auth/RegistrationForm.html.twig`
- [ ] `components/Auth/InvitationForm.html.twig`
- [ ] `components/Auth/RequestPasswordResetForm.html.twig`
- [ ] `components/Auth/ResetPasswordForm.html.twig`

---

## Phase 4 — Player portal (the richest, most-designed surface)

- [ ] `portal/dashboard.html.twig` — DS player dashboard (`analysis/ds_pages.md`
      §6): hero greeting + `SurfaceAccent` rank card; **soutěž switcher**
      (server-side); 4 `StatCard`s (Zbývá natipovat / Dnes se hraje[without
      "live" framing — use "Dnes" count] / Přesné tipy + úspěšnost / Streak);
      „Tvé zápasy" via `MatchRow` (filter chips Vše/Dnes/Tipovatelné/Ukončené —
      **drop „Live"**); mini `Leaderboard` + „Tvé soutěže" `pool-card`s; „Tvé
      poslední výsledky". Reuses the dashboard queries; new stats from `04`.
- [ ] **NEW** `portal/matches/index.html.twig` + `Portal\MatchesController`
      (`portal_matches`) — the **Zápasy** page: my open/upcoming matches across
      all soutěže, filter chips + soutěž filter, `MatchRow` list. See `04` §Zápasy.
- [ ] `portal/guess/detail.html.twig` — single-match page: hero `MatchCard` (3
      states) + `PickDistribution` (after lock) + per-match ranking („Pořadí za
      zápas", see `04`) + members' tips (`Guess:MatchGuessesList`, lock-pill for
      hidden). Renders `Guess:GuessSubmitForm`.
- [ ] `portal/leaderboard/index.html.twig` — DS full leaderboard
      (`analysis/ds_pages.md` §5): page-head metrics, „TY" you-strip, `Podium`
      top-3, toolbar (search + segment Celkem/Poslední kolo/Týden/Měsíc + sort),
      `Leaderboard` rich table, soutěž switcher. New columns from `04`.
- [ ] `portal/leaderboard/member.html.twig` — per-member breakdown → dark surface
      + streak/accuracy header chips. (Closest thing to a profile/tip-history page.)
- [ ] `portal/leaderboard/matrix.html.twig` — sticky-header/first-col grid → dark;
      keep top-score highlight (cyan→accent), lock icon for hidden tips.
- [ ] `portal/group/my_tips_batch.html.twig` — DS batch tip grid (2-col score
      inputs, bulk-fill 2:1/1:1/1:2/clear, `{filled}/{total}` counter, fixed save
      bar). Keep the existing fixed save bar; add bulk-fill + dark styling.
- [ ] `portal/group/join_by_pin.html.twig` — `PinInput` 8-box.
- [ ] `portal/profile/edit.html.twig` (renders `Profile:ProfileForm`) — dark
      glass form (the DS profile is a modal; a page is fine — re-skin in place).
- [ ] `portal/profile/delete_confirm.html.twig` — dark danger card.

### Player Live Components (re-skin templates)
- [ ] `components/Guess/GuessSubmitForm.html.twig` → wrap/become the **MatchCard**
      tip styling (the core component — see `02`). Keep Live wiring.
- [ ] `components/Guess/MatchGuessesList.html.twig` → members' tips, lock-pill.
- [ ] `components/Leaderboard/GroupLeaderboard.html.twig` → DS `.lb-table` + ranks.
- [ ] `components/Profile/ProfileForm.html.twig` → dark fields.
- [ ] `_partials/join_by_pin_form.html.twig` → `PinInput`.
- [ ] `_partials/tournament_rules.html.twig` → dark read-only scoring card.

---

## Phase 5 — Organizer portal

- [ ] `portal/group/detail.html.twig` — DS PoolDetail: 2-col (matchday `MatchRow`
      list + `Leaderboard`), role chips (Organizátor/Hráč), action buttons
      (Nastavení / Pozvat / Tipovat za členy / Uzamknout tipy). Keep current
      tooling (members, invites, PIN/link, rules) re-skinned; invites → email
      chips + copy-field link + PIN.
- [ ] `portal/group/create.html.twig` — re-skin (keep page-based; a full wizard
      modal is optional). Fields: název + zdroj zápasů (turnaj select) + „od
      začátku". Scoring step reuses `Scoring/RuleFields`.
- [ ] `portal/group/edit.html.twig` — dark glass settings.
- [ ] `portal/group/add_anonymous_member.html.twig` — dark form.
- [ ] `portal/group/promote_anonymous_member.html.twig` — dark form.
- [ ] `portal/group/manage_member_tips.html.twig` — DS „Tipovat za členy":
      member picker (existing Tom Select) + 2-col score grid + bulk-fill +
      `{filled}/{total}` + „Uloženo" toast. Re-skin + add bulk-fill.
- [ ] `portal/leaderboard/resolve_ties.html.twig` — drag-and-drop list
      (`orderable-list` controller) → dark glass rows. **Fix JS class strings if
      any.**
- [ ] `portal/sport_match/detail.html.twig` — organizer match view: per-group
      guess cards + result-entry section (score inputs + state toggle —
      **scorers editor deferred**). Renders `Guess:GuessSubmitForm` per group.
- [ ] `portal/sport_match/form.html.twig` — dark fields (currently bare inputs
      without focus rings — fix to DS field styling).
- [ ] `portal/sport_match/set_score.html.twig` — DS big score inputs + state
      toggle.
- [ ] `portal/sport_match/import.html.twig` — dark form (CSV import; replace
      legacy `text-gray-*`/`bg-gray-50`).
- [ ] `portal/tournament/detail.html.twig` — turnaj owner view (match source):
      dark hero + matches/groups + rules sidebar.
- [ ] `portal/tournament/create_private.html.twig` — dark form.
- [ ] `portal/tournament/edit.html.twig` — dark form.
- [ ] `portal/tournament/rule_configuration.html.twig` — `Scoring/RuleFields`
      (presets Standardní/Vlastní; **„+střelec" deferred**) + `confirm-recalculation`.

---

## Phase 6 — Admin (LAST; no bespoke DS design — apply tokens/components)

All extend `admin/layout.html.twig`. Replace raw inline `<svg>` → `twig:ux:icon`;
status → `Pill`/`Badge`; tables → dark glass; fix legacy `text-gray-*`/`bg-gray-*`.
- [ ] `admin/layout.html.twig` — dark glass sidebar shell; active nav state.
- [ ] `admin/tournament/list.html.twig`
- [ ] `admin/tournament/create_public.html.twig`
- [ ] `admin/tournament/edit.html.twig`
- [ ] `admin/tournament/rule_configuration.html.twig` — fix stray
      `bg-navy-50/40/40` typo.
- [ ] `admin/group/list.html.twig`
- [ ] `admin/user/list.html.twig` — filter form + table + status pills +
      switch-user.
- [ ] `admin/rule/list.html.twig`
- [ ] `admin/sport_match/list.html.twig` — add `Pill` mapping for `match.state`
      (currently raw value).

---

## Cross-cutting cleanups (fold into the phase that first hits them)
- [ ] `EmptyState` — reskin + render `illustration`.
- [ ] `Breadcrumbs` — reskin (+ optional `BackLink` for detail pages).
- [ ] Replace every emoji (🔥 → `lucide:flame`, ✓ → `lucide:check`, etc.).
- [ ] Replace every `text-gray-*`/`bg-gray-*`/`bg-white`/`text-navy-900` with
      Wtips classes (keep a running grep list; see verification §).
- [ ] Favicons / OG image / `site.webmanifest` → Wtips brand (`#0f1726`).

---

## Verification & CI gates

After **every** screen and **definitely** at each phase boundary:

```bash
docker compose exec web composer quality   # phpstan L8 + cs:check + tests + migrations-up-to-date + schema:validate
```

Targeted helpers:
```bash
# light-theme leftovers (should trend to zero outside emails/)
grep -rIl --include=*.twig -e 'bg-white' -e 'text-navy-900' -e 'text-gray-' -e 'bg-gray-' templates/ | grep -v templates/emails
# emoji sweep (should be empty)
grep -rIn --include=*.twig -P '[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]' templates/
# missing icons throw in dev — confirm every referenced icon exists
grep -rohP 'lucide:[a-z0-9-]+' templates/ | sort -u
```

Constraints that MUST hold (from `CLAUDE.md`):
- **Entity changes → `bin/console doctrine:migrations:diff`**, commit generated
  migration. Never hand-write schema DDL. Partial unique indexes in mapping.
- **No `DC2Type` comments.**
- **New Lucide icons imported** before use.
- **Don't break Live Component wiring** (`#[LiveProp]`/`#[LiveAction]`,
  `data-model`); re-skin templates only.
- Turbo stays globally off unless a specific element opts in with
  `data-turbo="true"`.

If `tests/` assert on old markup/copy, update the assertions to match the new
design (don't weaken coverage). Add `tests/` for new controllers (`portal_matches`)
and queries (`04`) following the existing Integration test patterns + fixtures
(`.claude/FIXTURES.md`). E2E uses PHPUnit WebTestCase + BrowserKit (no Panther).

---

## Deviations log (append as you go)
> Record anything you changed vs this plan, with one line of why. Keeps the
> overnight run auditable.

**Phase 1 (done, 2026-06-01):**
- Montserrat self-hosted as **subsetted woff2** (latin+latin-ext, 7 weights
  300–900) under `assets/fonts/montserrat/`, converted from the DS `.otf` via
  host `pyftsubset`. ~196 KB total. `@font-face` in `app.css`.
- `cyan-*` Tailwind colors kept as **aliases to the accent scale** (not removed)
  so legacy `*-cyan-*` utilities on un-migrated pages stay on-brand during the
  screen-by-screen sweep. Remove the aliases once Phases 2–6 are done.
- Avatar dropdown uses a native `<details>`/`<summary>` (no new controller).
- Mobile nav reuses the existing `mobile-nav` controller (toggles `hidden` on a
  separate `.wt-mobile` panel) rather than an `.open` root class.
- Dead deps removed via hand-editing `importmap.php` + pruning
  `assets/vendor/installed.php` (the `importmap:remove` console cmd was a no-op
  here); deleted vendor dirs leaflet/glightbox/konva/chart.js/signature_pad/
  alpinejs + `hello_controller.js`.
- Brand name: `APP_BRAND_NAME` env (default `Wtips` in `.env`) → Twig global
  `brand_name` (`config/packages/twig.php`). Override on the tipovacka branch.

**ENVIRONMENT NOTE (important for anyone running tests locally on this machine):**
- The local Docker VM is shared with several other heavy projects' containers, so
  the **test container cache warmup** (`cache:warmup --env=test`) and large
  `phpunit` batches get **SIGKILL'd (exit 137 / OOM)**, which leaves a *partial*
  compiled container (`var/cache/test/.../getContainer_EnvVarProcessorsLocatorService.php`
  missing) and makes subsequent tests fail with `Failed opening required …`.
  These are **not** code failures.
  - Mitigation used: stop idle helper containers (`docker compose stop adminer
    mailpit tailwind`) to free RAM, then `rm -rf var/cache/test && php bin/console
    cache:warmup --env=test` (completes cleanly), then run tests. Restart helpers
    after (`docker compose start adminer mailpit tailwind`).
  - Run integration tests **one file at a time** locally (multi-file batches OOM);
    pass `</dev/null` to `docker compose exec` inside read-loops (it otherwise
    eats the loop's stdin).
  - The test DB is auto-built by `tests/bootstrap.php` from the **`test`** fixture
    group (NOT `dev`) when `tests/.database.cache` is stale. Do **not** manually
    `doctrine:fixtures:load --group=dev` into `wtips_test` — it pollutes count
    assertions.
  - CI (`.github/workflows/test.yml`, clean container + dedicated RAM, empty
    `wtips_test`) runs plain `vendor/bin/phpunit` and is unaffected by all this.
- Phase-1 gate verified: **phpstan L8 [OK] No errors · cs:check 0 fixable ·
  schema:validate in sync · 238/238 unit tests · integration tests pass on a
  clean cache** (the only local failures were the OOM artifact above).
