Verified. The reports are accurate (template count discrepancy: 69 actual vs the 79 routes — templates ≠ routes, expected). I have enough to write the report.

---

# GAP ANALYSIS — Tipovačka (current) → Wtips (new dark design system)

This drives a full visual-redesign migration plan. The current app is a feature-complete Symfony 8 CQRS app with a **light navy/cyan** Tailwind v4 theme. The Wtips design system is **dark-first glassmorphism** (navy `#0f1726`, electric-blue `#4699d0`, Montserrat 100–900, gold/red/green status colors) and introduces several **new product surfaces** (pick distribution, freemium paywall, payouts/contributions, brackets, group-stage labels) that the current backend does not model.

The single most important structural fact: **the DS's "soutěž / pool" = the current app's `Group`**, and the DS's "Zdroj zápasů / turnaj" = the current app's `Tournament`. The DS collapses these into one user-facing "soutěž" with a tournament chosen as a match-source template. This mismatch shapes every screen mapping below.

---

## 1. SCREEN MAPPING TABLE

All 69 current templates. Surface key: P=public, A=auth, INV=invitation, PL=player portal, ORG=organizer portal, ADM=admin, SYS=error, EMAIL=email, CMP=component/partial/theme.

### Layouts / shells (migrate FIRST — everything inherits)

| Current screen | Surface | Closest DS reference | Complexity | Notes |
|---|---|---|---|---|
| `base.html.twig` | all | `.wtnav` (sticky glass header) + marketing `.wtfoot` + app mini-footer; `body` dark `#0f1726` | **High** | Single biggest change. Body goes dark; sticky top nav rebuilt as `.wtnav` (brand-mark "W" gradient tile, primary links, nav-cta "Vytvořit soutěž", bell icon, avatar). Current nav links (Nástěnka/Turnaje/Profil) → DS labels (Soutěže/Zápasy/Žebříček). DS has **two nav variants** (hard-coded vs `_partials.js` with "Hráč" chip + hamburger) — pick one canonical. Footer: DS marketing 4-col `.wtfoot` for public, mini-footer for app. Flash messages: re-tone to dark glass status pills. `theme-color` → `#0f1726`. |
| `auth/_layout.html.twig` | A | `prihlaseni.html` / `registrace.html` background system | **High** | Current centered-card-on-white → DS 2-col `.auth-wrap` (left value-prop with PIN-card + live stats, right glass form). Dark navy + radial glows + **fixed stadium photo** (Unsplash, opacity 0.28, blue-toned). Registration variant is centered column. Major restructure, not just recolor. |
| `admin/layout.html.twig` | ADM | extrapolate from tokens (no DS admin design) | **Med** | Apply tokens/components, no bespoke design. Keep 2-col sidebar shell but restyle to dark glass surfaces; replace raw inline `<svg>` + `text-gray-*` with `twig:ux:icon` + DS tokens. Sidebar nav active state → `.active` blue-underline / accent fill. |

### Public

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `home.html.twig` | P | `Landing.html` / `landing-bold.html` ("Bold sport") | **High** | Full rebuild to DS landing: HERO (gradient strikethrough headline, `.hero-live` glass match-card with pick %), HOW IT WORKS (3 `.step`), FEATURES (6 `.feat`), SHOWCASE (embedded app iframe → static screenshot), FINAL CTA (`.cta-card` blue gradient). Current hero image `hero-desktop.png` likely replaced by DS live-match glass card. Copy direction shifts to "Bez sázek, jen pro radost a vychloubání." |
| `public/tournaments_list.html.twig` | P | DS Pools grid (organizer kit `PoolsDashboard` card grid) + `_partials/join_by_pin_form` → DS `.pin-card` | **Med** | DS shows public/private pool cards with progress bars, player counts, payout strings. Re-skin card grid to dark glass `.card-glass`; PIN form → DS 8-box `.pin-inputs`. Note DS term mismatch: DS "soutěž" = Group, current page lists Tournaments. |
| `public/tournament_detail.html.twig` | P | DS `PoolDetail` (organizer kit) | **High** | DS PoolDetail is 2-col (matchday tip-cards left, leaderboard right) with role chips. Current page is tournament-centric (groups + matches). Re-skin hero to gradient H1, sidebar `<dl>` → DS surface, `_partials/tournament_rules` → DS scoring card. Pick-distribution teaser optional. |
| `public/privacy.html.twig` | P | extrapolate from tokens (prose) | **Low** | Dark prose page; apply tokens. Update contact email (current `kontakt@tipovacka.cz` has a TODO) and DS legal copy ("Vše hraje, nic se nesází."). |

### Auth (all extend `auth/_layout`)

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `auth/login.html.twig` | A | `prihlaseni.html` | **High** | DS login: left PIN-card + live-pill ("247 hráčů tipuje právě teď") + quick-stats; right glass form with **Google/Apple OAuth** social-grid + divider + email/password + remember-me. **Current login is hand-written (not a component)** and has **no OAuth** — OAuth is purely visual in DS unless backend added (see §2). Decision: include OAuth buttons as non-functional/hidden or implement. |
| `auth/register.html.twig` | A | `registrace.html` (renders `Auth:RegistrationForm`) | **High** | DS form: 2-col paired field-rows (email+přezdívka, jméno+příjmení, heslo+heslo znovu), GDPR checkbox. Maps cleanly to current `RegistrationFormData` (email, firstName, lastName, nickname, password, gdprConsent). Restyle the live component. OAuth sign-up buttons visual-only unless backend. |
| `auth/password_reset_request.html.twig` | A | extrapolate (DS has no reset screen) | **Med** | Apply auth-card tokens to `Auth:RequestPasswordResetForm`. |
| `auth/password_reset.html.twig` | A | extrapolate | **Med** | Restyle `Auth:ResetPasswordForm` to DS glass card. |
| `auth/password_reset_check_email.html.twig` | A | extrapolate (status card) | **Low** | Dark glass confirmation card; recolor icon tile. |
| `auth/verify_pending.html.twig` | A | extrapolate (status card) | **Low** | Dark glass; recolor. |
| `auth/verify_error.html.twig` | A | extrapolate (status card) | **Low** | Dark glass; recolor; fix `lucide:flag` missing-icon issue lives in invitation, not here. |

### Invitation

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `invitation/landing.html.twig` | INV | extrapolate (DS has PIN-card + auth-card, no explicit invite-landing) | **Med** | Multi-state (invalid/expired/revoked/accepted/finished/mismatch/active) status cards → dark glass. Renders `Auth:InvitationForm`. **Fix:** references `lucide:flag` which is missing locally (`assets/icons/lucide/flag.svg` absent) → import or replace during migration. |

### Portal — player-facing

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `portal/dashboard.html.twig` | PL | `dashboard-hrac.html` (canonical) | **High** | DS player dashboard is the richest screen: hero greeting + rank card (7./42, 147 b, +3), 4 stat cards (incl. **streak 🔥**, accuracy %), "Tvé zápasy dnes" tip-cards with pick-distribution teaser, mini-leaderboard, "Tvé soutěže" pool cards with progress, "Poslední výsledky" history. Current dashboard has the pieces (my groups, upcoming matches, evaluated guesses) but lacks streak/accuracy stats & pick-distribution. See §2 for the new data. Pool switcher `<select>` is a DS pattern current app lacks (current uses separate pages per group). |
| `portal/group/join_by_pin.html.twig` | PL | DS `.pin-card` 8-box inputs | **Low** | Replace single PIN field with DS 8-box auto-advance/paste inputs. |
| `portal/group/my_tips_batch.html.twig` | PL | DS `TipForMembersScreen` grid (bulk fill) + tip-card | **Med** | DS shows 2-col score-input grid with bulk-fill (2:1/1:1/1:2/clear), `{filled}/{total}` counter, fixed save bar. Current already has fixed bottom save bar; add DS bulk-fill UX + dark styling. |
| `portal/guess/detail.html.twig` | PL | `LiveMatch` (organizer kit) — the 3-state tip card + pick distribution | **High** | DS single-match view = hero match card + **pick distribution (premium-gated)** + match timeline + per-match ranking ("Pořadí za zápas"). Renders `Guess:GuessSubmitForm` + `Guess:MatchGuessesList`. Distribution/timeline/per-match-ranking are NEW (see §2). The 3-state tip card (Brzy/Tipováno/Ukončeno) is the DS centerpiece — map to current Scheduled/has-guess/Finished. |
| `portal/leaderboard/index.html.twig` | PL | `zebricek.html` (full) + `Leaderboard:GroupLeaderboard` | **High** | DS leaderboard: page-head metrics, "you-strip" banner, **podium top-3** (gold/silver/bronze cards), toolbar (search + segment Celkem/Poslední kolo/Týden/Měsíc + sort), rich table (Pozice/Δ/Hráč/Body/Úspěšnost/Přesné/Trefa/Streak), sticky "TY" row, gap-rows. Current leaderboard has Pořadí/Hráč/Body only + winner celebration. **Úspěšnost/Přesné/Trefa/Streak/Δ columns are NEW data** (see §2). Podium is a new component. |
| `portal/leaderboard/member.html.twig` | PL | extrapolate from `zebricek` row + DS profile/stats | **Med** | Per-match breakdown table → dark surface; add streak/accuracy header chips to match DS. |
| `portal/leaderboard/matrix.html.twig` | PL | extrapolate from tokens (no DS matrix) | **Med** | Apply tokens/components, no bespoke design. Sticky-header/first-col grid restyled to dark; keep cyan→blue top-score highlight, lock icon for hidden tips. Widest container preserved. |
| `portal/profile/edit.html.twig` | PL | DS `ProfileModal` (Osobní údaje / Heslo / Smazat účet tabs) | **Med** | DS profile is a modal with tabs; current is a page. Re-skin to dark; renders `Profile:ProfileForm`. The DS "tip history / statistics" profile page was **requested but never built** — current `member.html.twig` is the closest history surface. |
| `portal/profile/delete_confirm.html.twig` | PL | extrapolate (danger card) | **Low** | Dark glass danger card; recolor red. |

### Portal — organizer

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `portal/group/detail.html.twig` | ORG/PL | `PoolDetail` (organizer kit) — heavy organizer tooling | **High** | DS PoolDetail = matchday tip-cards + leaderboard + role chips (Organizátor/Hráč) + action buttons (Nastavení/Pozvat/Tipovat za členy/Uzamknout tipy). Current detail is the closest analog (members accordion, my tips, invites, PIN/link, rules, management). Major restructure to DS 2-col + dark glass. Invites map to DS `InvitePlayersModal` (email chips + URL + PIN). |
| `portal/group/create.html.twig` | ORG | `CreatePoolModal` Step 1 (Soutěž) + Step 2 (Pravidla) | **Med** | DS create is a **4-step wizard modal**; current is a page. Decision: keep page-based or adopt wizard. Step1 (název + zdroj zápasů select), Step2 (scoring rules), Step3 (invite), Step4 (payment — NEW, see §2). |
| `portal/group/edit.html.twig` | ORG | `PoolSettingsModal` | **Med** | Re-skin to DS glass; settings recycle scoring-rule fields. |
| `portal/group/add_anonymous_member.html.twig` | ORG | extrapolate from tokens | **Low** | Apply tokens; small form. |
| `portal/group/promote_anonymous_member.html.twig` | ORG | extrapolate from tokens | **Low** | Apply tokens; single email field. |
| `portal/group/manage_member_tips.html.twig` | ORG | `TipForMembersScreen` (organizer kit) — full DS analog | **Med** | DS gives exact design: pool+member pickers, 2-col score grid, bulk-fill, `{filled}/{total}`, late badge, "Uloženo" toast. Current already has Tom Select member picker + batch grid. Re-skin + add bulk-fill. |
| `portal/leaderboard/resolve_ties.html.twig` | ORG | extrapolate from tokens (no DS tie-resolution) | **Med** | Apply tokens. Drag-and-drop list (`orderable-list`) restyled from legacy `bg-gray-*` to dark glass rows. |
| `portal/sport_match/detail.html.twig` | ORG/PL | `LiveMatch` + `SetResultModal` | **High** | DS LiveMatch hero + per-group guess cards. Match management section maps to DS `SetResultModal` (score + state toggle + **scorers editor** — NEW, see §2). Current embeds `Guess:GuessSubmitForm` per group. |
| `portal/sport_match/form.html.twig` | ORG | extrapolate from tokens | **Low** | Apply tokens; currently uses bare inputs without focus rings — fix to DS field styling. |
| `portal/sport_match/set_score.html.twig` | ORG | `SetResultModal` (score inputs) | **Low–Med** | DS gives big 84px score inputs + state toggle + scorers. Minimum: re-skin 2 score inputs to DS. |
| `portal/sport_match/import.html.twig` | ORG | extrapolate from tokens | **Med** | Apply tokens; replace legacy `text-gray-*`/`bg-gray-50`. CSV import has no DS analog. |
| `portal/tournament/detail.html.twig` | ORG | `PoolDetail` (partial) / extrapolate | **Med** | Tournament owner view. Re-skin hero + match/group sections + rules sidebar to dark. DS treats this as the match-source layer. |
| `portal/tournament/create_private.html.twig` | ORG | `CreatePoolModal` Step 1 | **Low–Med** | Re-skin form; could feed DS "Zdroj zápasů" concept. |
| `portal/tournament/edit.html.twig` | ORG | extrapolate | **Low–Med** | Re-skin form. |
| `portal/tournament/rule_configuration.html.twig` | ORG | `CreatePoolModal` Step 2 / `PoolSettingsModal` scoring fields | **Med** | DS scoring-rule cards (Standardní/+střelec/Vlastní presets + NumberField steppers). Current is per-rule cards with enable/points. **Note:** DS adds a "Trefený střelec zápasu" rule the current app lacks (see §2). Re-skin + `confirm-recalculation` controller. |

### Admin (all extend `admin/layout`; no DS admin design)

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `admin/tournament/list.html.twig` | ADM | apply tokens/components, no bespoke design | **Med** | Table re-skin to dark glass; replace raw `<svg>` → `twig:ux:icon`; status → DS pills. |
| `admin/tournament/create_public.html.twig` | ADM | apply tokens | **Low** | Form re-skin. |
| `admin/tournament/edit.html.twig` | ADM | apply tokens | **Low** | Form re-skin. |
| `admin/tournament/rule_configuration.html.twig` | ADM | apply tokens (mirror portal variant) | **Low–Med** | Fix stray `bg-navy-50/40/40` typo; re-skin. |
| `admin/group/list.html.twig` | ADM | apply tokens | **Low–Med** | Table re-skin. |
| `admin/user/list.html.twig` | ADM | apply tokens | **Med** | Filter form + table + status pills + switch-user; re-skin. |
| `admin/rule/list.html.twig` | ADM | apply tokens | **Low** | Read-only table re-skin. |
| `admin/sport_match/list.html.twig` | ADM | apply tokens | **Low–Med** | Add pill mapping for `match.state` (currently raw value); re-skin. |

### Error / system (currently BROKEN — undefined `card`/`btn` classes)

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `error.html.twig` | SYS | apply tokens (DS `.btn`/`.surface`) | **Low** | Currently renders unstyled (undefined `card`/`btn-primary`). Replace emoji headers with `twig:ux:icon`; dark glass card. High-priority quick win. |
| `error404.html.twig` | SYS | apply tokens | **Low** | Fix broken styling; references `app_profile` route — verify. |
| `error403.html.twig` | SYS | apply tokens | **Low** | Fix broken styling. |
| `error500.html.twig` | SYS | apply tokens | **Low** | Fix broken styling. |

### Reusable components (`templates/components/` + PHP)

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `Auth/RegistrationForm` | CMP | `registrace.html` form fields | **Med** | Re-skin live component to DS dark fields. |
| `Auth/InvitationForm` | CMP | extrapolate (auth-card) | **Med** | Re-skin; multi-kind branching preserved. |
| `Auth/RequestPasswordResetForm` | CMP | extrapolate | **Low** | Re-skin single field. |
| `Auth/ResetPasswordForm` | CMP | extrapolate | **Low** | Re-skin repeated password. |
| `Guess/GuessSubmitForm` | CMP | DS `.tip-card` (3-state) + score inputs | **High** | The CORE component. DS 3-state (Brzy/Tipováno/Ukončeno) maps to current locked/editable/finished. Re-skin to DS dark glass tip-card with proper score inputs, morphing button labels. Reused on guess detail + sport-match detail + dashboard. |
| `Guess/MatchGuessesList` | CMP | DS pick-distribution / members list | **Med** | Re-skin members' tips table; lock-pill for hidden. |
| `Leaderboard/GroupLeaderboard` | CMP | `zebricek.html` table + podium | **High** | Currently off-convention (`shadow-lg`/gray). Rebuild to DS dark table; add podium top-3 styling, rank colors. Drives §2 new columns. |
| `Profile/ProfileForm` | CMP | DS `ProfileModal` | **Low–Med** | Re-skin fields. |
| `Breadcrumbs` (template-only) | CMP | DS uses back-links (`← Zpět na soutěže`), not breadcrumbs | **Med** | DS has no breadcrumb chrome — it uses single back-links. Decision: keep breadcrumbs re-skinned, OR replace with DS back-link pattern. Recommend keeping breadcrumbs (used on ~all portal pages) but re-toning. |
| `EmptyState` (template-only) | CMP | DS empty-state cards (e.g. "Žádné soutěže neodpovídají filtru") | **Low–Med** | Re-skin; finally render the `illustration` prop (currently dead — accepted but no output). |

### Shared partials / theme

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `_partials/join_by_pin_form.html.twig` | CMP | DS `.pin-card` 8-box inputs | **Med** | Replace with DS 8-box auto-advance/paste PIN inputs. Used on dashboard + public list + tournament detail. |
| `_partials/tournament_rules.html.twig` | CMP | DS scoring card | **Low** | Re-skin read-only rules card. |
| `form/_form_theme.html.twig` | CMP | DS field styling (`label` uppercase 10px, dark input bg, blue focus ring) | **High** | All form widgets re-themed: dark input bg `rgba(0,0,0,0.22)`, blue focus ring `0 0 0 3px rgba(70,153,208,0.18)`, uppercase labels. Affects every form in the app. Datepicker/choice/checkbox widgets re-skinned. |

### Email (no DS email design — apply tokens/brand)

| Current screen | Surface | DS reference | Complexity | Notes |
|---|---|---|---|---|
| `emails/welcome.html.twig` | EMAIL | apply brand tokens, no bespoke design | **Low** | Table-based HTML email. Re-brand to Wtips name/colors (`#4699d0` accent). Note: emails should stay **light** (email-client dark-mode is unreliable) — apply brand colors, not the dark glass theme. |
| `emails/verify_email.html.twig` | EMAIL | apply brand tokens | **Low** | Re-brand. |
| `emails/password_reset.html.twig` | EMAIL | apply brand tokens | **Low** | Re-brand. |
| `emails/group_invitation.html.twig` | EMAIL | apply brand tokens | **Low** | Re-brand. |
| `emails/join_request_approved.html.twig` | EMAIL | apply brand tokens | **Low** | Diverges from shared email shell — consolidate + re-brand. |

---

## 2. NEW FEATURES / SCREENS the design implies

Effort: S = ≤1 day frontend-only, M = multi-day or some backend, L = significant backend + frontend.

### 2.1 — 3-state tip/match card (Brzy / Tipováno / Ukončeno)
- **Design shows:** The DS centerpiece. Three states mapped to three surfaces: upcoming (score inputs + "Odeslat tip"), tipped ("Upravit tip" + shown score), finished (final score + result banner + points earned). Used on player single-match, dashboard, pool detail.
- **Backend exists:** YES — fully. `SportMatch.state` (Scheduled/Live/Finished/…), `SportMatch.isOpenForGuesses`, `Guess` (home/away score), `GuessEvaluation.totalPoints`, `Guess:GuessSubmitForm` already morphs button labels (Odeslat/Upravit/Smazat tip). The current locked/editable/finished logic maps 1:1.
- **Must add:** Pure styling/restructure of `Guess:GuessSubmitForm` + the surrounding card. No backend.
- **Effort: S (frontend-only).**

### 2.2 — Live match PICK DISTRIBUTION ("Distribuce / Rozložení tipů" 1/X/2)
- **Design shows:** Signature feature. Home/draw/away breakdown with **counts AND percentages** ("Výhra Argentiny 58 %", "248 hráčů tipovalo"), color-coded blue/gold/red bars, on LiveMatch + every tip-card + matches rows + dashboard.
- **Backend exists:** PARTIAL — `Guess` rows exist per (user, match, group), so aggregation is computable. But there is **no query** for distribution, and crucially **no notion of 1/X/2 outcome bucketing** at query level. Distribution within a `Group` is trivial; "248 hráčů" (cross-group/global) is a different aggregation.
- **Must add:** A `GetMatchPickDistribution` query (group `Guess` by outcome sign → home-win/draw/away-win counts + %). Decide scope (per-group vs tournament-wide). Respect `Group.hideOthersTipsBeforeDeadline`. Frontend bars + premium gate (see 2.3).
- **Effort: M (new query + frontend).**

### 2.3 — Freemium / PRÉMIUM paywall + contributions ("Pozvete nás na pivo?")
- **Design shows:** Distribution gated behind a gold premium teaser (blurred numbers, lock, "Odemknout →", `wtips:open-premium` event). Create-wizard Step 4: org pays **10 Kč/player** to unlock for all, OR individuals buy tiers (**50/100/200 Kč** for distribution bar / concrete tips / mid-tournament edits). Group benefits: control tip visibility, control deadline, funny badges, auto results, custom scoring.
- **Backend exists:** NONE. No payment, pricing, entitlement, subscription, or contribution concept anywhere in entities/enums/commands/routes. This is a whole **commerce subsystem**.
- **Must add:** Payment integration (e.g. Stripe/GoPay), entitlement entities (per-group "premium unlocked" flag, per-user purchased tiers), pricing logic, billing-after-lock workflow, webhooks. The "funny badges (Smolař/Šťastlivec)" are a separate gamification feature.
- **Effort: L (entire new backend subsystem).** **Honest recommendation: stub frontend-only initially** (teaser visuals, no real payment) and defer the commerce backend to a dedicated later phase.

### 2.4 — Streak / accuracy / Δ analytics (leaderboard + dashboard stats)
- **Design shows:** Leaderboard columns Úspěšnost (accuracy %), Přesné (exact-tip count), Trefa (partial hits), Streak (🔥 n consecutive scoring tips), Δ (rank movement). Dashboard stat cards (Přesné tipy 9/38, Streak 🔥 4, 23,7 % úspěšnost).
- **Backend exists:** PARTIAL — raw data exists. `GuessEvaluationRulePoints` records which rules fired per guess (so "exact_score" fired = Přesné; any rule fired = Trefa; accuracy = scored/total). Streak requires ordering guesses by match chronology and counting consecutive non-zero evaluations. Δ requires **snapshotting prior leaderboard rank** (no historical rank storage exists).
- **Must add:** Query extensions on `GetGroupLeaderboardQuery`/member breakdown to compute accuracy, exact count, partial count, streak. **Δ (rank change) needs a stored historical rank snapshot** — a new lightweight entity or scheduled snapshot. Without history, Δ can only be faked/omitted.
- **Effort: M** (accuracy/exact/partial/streak are derivable from existing data) **+ S/M for Δ** (needs new snapshot storage; recommend omitting Δ in phase 1).

### 2.5 — Podium top-3 leaderboard component
- **Design shows:** Gold/silver/bronze raised podium cards (avatar initials on medal gradients, points, per-player micro-stats Přesné/Úspěšnost/Streak), above the full table.
- **Backend exists:** YES — leaderboard ranking already computed (`GetGroupLeaderboardQuery`, tie resolution). Per-player micro-stats need 2.4's data.
- **Must add:** Twig component; medal-gradient avatar styling. Wire to leaderboard query (+ 2.4 stats).
- **Effort: S–M (frontend + reuses 2.4 query).**

### 2.6 — Match timeline + per-match ranking + scorers ("Pořadí za zápas", "Zapsat výsledek")
- **Design shows:** LiveMatch shows a match-event timeline (Gól/Žlutá/Výkop with minute + "tipovalo N hráčů") and "Pořadí za zápas" (per-match top scorers). `SetResultModal` lets organizers add **scorers (team/minute/name)** feeding a "Trefený střelec" scoring bonus.
- **Backend exists:** NONE for timeline/scorers. `SportMatch` has only home/away score + state — **no event log, no goalscorer model**. Per-match ranking IS derivable (`GuessEvaluation` per match within a group).
- **Must add:** A `MatchEvent`/goalscorer entity (team, minute, player name, type) + UI; a "correct scorer" rule. Per-match ranking = a new query over `GuessEvaluation` filtered by match. Timeline is L; per-match ranking is S–M.
- **Effort: L for scorers/timeline (new entities + new rule); S–M for per-match ranking only.** Recommend per-match ranking now, scorers/timeline deferred.

### 2.7 — Pick distribution premium → tied to scoring rule "Trefený střelec zápasu"
- **Design shows:** Create-wizard Step 2 includes a 5th scoring field "Trefený střelec zápasu" (bonus 2 b) when variant ≠ standard. DS scoring presets: standard `{home 1, away 1, result 3, exact 5}`, +scorer `{…, scorer 2}`.
- **Backend exists:** PARTIAL — the four current rules (`exact_score 5`, `correct_outcome 3`, `correct_home_goals 1`, `correct_away_goals 1`) map exactly to DS's four standard fields. **The "scorer" rule (and the underlying goalscorer guess + match scorer data) does NOT exist.**
- **Must add:** New `Rule` implementation + `Guess` extension to capture a predicted scorer + match scorer data (2.6). The rule registry/config UI already supports adding rules.
- **Effort: M–L (new rule + new guess/match data).** Recommend deferring; current 4 rules cover the "Standardní" preset fully.

### 2.8 — Bracket / pavouk visualization
- **Design shows:** Referenced only as **text/labels** in DS ("Pavouk je živě", round labels "Osmifinále", "Čtvrtfinále úv."). **Crucially: the organizer kit explicitly does NOT draw a bracket** — no bracket/pavouk visualization exists in the DS. Group-stage is also only label text ("Skupina A/C").
- **Backend exists:** NONE. `SportMatch` is a **flat list** per tournament — no round/stage/parent-match/advancement modeling. The untracked CSVs (`ms-hokej-2026-qf.csv`, `ms-hokej-2026-sf.csv`) hint at bracket data imported as flat matches.
- **Must add:** Round/stage field on `SportMatch` (enum: group/round-of-16/QF/SF/final), plus advancement linkage for a true bracket. Full bracket viz = significant new entity + rendering.
- **Effort: L for a true bracket; S to just add a round/stage label** (matches DS, which only shows labels). **Recommend: add a `round`/`stage` label field only** — the DS never actually visualizes a bracket, so a full pavouk is out of scope for design parity.

### 2.9 — Group-stage standings tables
- **Design shows:** Only "Skupina A/C" labels — **no group-stage standings table drawn anywhere in the DS.**
- **Backend exists:** NONE (no group/round modeling).
- **Must add:** Nothing required for design parity (DS doesn't draw it). A real standings table would need round/stage + team-results aggregation.
- **Effort: N/A for parity; L if genuinely wanted.** **Recommend: skip** — not in the DS.

### 2.10 — Payouts / výplata / prize bank ("Výherní bank", "105 000 Kč v banku", nav "Výplaty")
- **Design shows:** Pools carry money: `Výherní bank` stat (205 000 Kč), per-pool payout strings ("105 000 Kč v banku", "Jen o čest", "18 000 Kč rozděleno"), "V úschěvném účtu" (escrow). **BUT** the chat explicitly **removed "Výplaty" from the nav** during iteration, and the hard-coded nav vs `_partials.js` nav disagree on whether it exists.
- **Backend exists:** NONE. No payout/prize/stake/money/settlement concept in entities. The DS's own tagline is "Bez sázek, nic se nesází" — payouts are **prize-pot tracking, not betting**.
- **Must add:** A pot/payout field on `Group` + display, OR full escrow tracking. Given the nav removal and "no betting" stance, this is ambiguous design intent.
- **Effort: S for a display-only `payout`/`prizeNote` string on Group; L for real pot/escrow tracking.** **Recommend: skip or display-only string** — design intent was walked back (nav item removed).

### 2.11 — Marketing subpages: Funkce / Ceník / Pro firmy / FAQ
- **Design shows:** Requested in the page set (`questions_v2`) and named in the index chooser, but **NEVER BUILT** — only landing variant A exists. The `index.html` chooser links to `features.html`, `pricing.html` etc. which don't exist.
- **Backend exists:** N/A — pure static marketing pages. No current routes/controllers for these.
- **Must add:** New controllers + routes + templates (4 pages). Ceník depends on the pricing model (2.3) being decided. Pro firmy = B2B narrative. FAQ = accordion.
- **Effort: M (4 new static pages + routes; pure frontend).** Ceník blocked on pricing decisions.

### 2.12 — 3 landing variants (Bold / Product showcase / Editorial)
- **Design shows:** Page set requested 3 landing concepts; **only variant A "Bold sport" (`landing-bold.html`) was built.** Variants B (product showcase, side-by-side mockup) and C (editorial/magazine) remain to-do.
- **Backend exists:** N/A.
- **Must add:** Build only the chosen variant for `home.html.twig` (variant A is canonical). Variants B/C are speculative — not needed for the app.
- **Effort: S (one landing) — ignore B/C unless A/B testing desired.**

### 2.13 — Profile / tip-history page
- **Design shows:** Requested in page set; DS only delivered a **Profile modal** (Osobní údaje / Heslo / Smazat účet). No standalone tip-history/statistics page was built.
- **Backend exists:** PARTIAL — `member.html.twig` (per-member point breakdown) is the closest. Tip history across all groups would need a cross-group query (current breakdown is per-group). Stats need 2.4's data.
- **Must add:** Optional cross-group tip-history query + page; or just re-skin existing profile + member breakdown.
- **Effort: S to re-skin existing; M for a true cross-group history page.** **Recommend: re-skin existing profile + member breakdown.**

### 2.14 — OAuth (Google / Apple) social login
- **Design shows:** Login + registration show full-color Google + Apple OAuth buttons with a "nebo e-mailem" divider.
- **Backend exists:** NONE. Current firewall is form-login only; no OAuth client/bundle.
- **Must add:** OAuth bundle (e.g. `knpuniversity/oauth2-client-bundle` + Google/Apple providers), user-linking logic, callback routes.
- **Effort: L (full OAuth backend).** **Recommend: stub buttons visually-only or hide in phase 1**; implement later if desired.

### 2.15 — Pool switcher (`<select>` on dashboard/leaderboard)
- **Design shows:** Dashboard/leaderboard have a `<select>` to switch active pool, re-rendering stats/leaderboard client-side.
- **Backend exists:** YES — user's groups are queryable (dashboard already lists `my_groups`). Current app uses separate per-group URLs instead of a switcher.
- **Must add:** A switcher that navigates between group routes (server-side) — simplest. Client-side re-render (DS's JS `POOLS` object) would need an API/Live Component.
- **Effort: S (navigation switcher) — M for live client re-render.**

### 2.16 — Tweaks panel (Vibe / Density / Type pressure)
- **Design shows:** A design-exploration dev tool, not an end-user feature.
- **Backend exists:** N/A.
- **Recommend: DO NOT build** — it's a design-system authoring tool, irrelevant to production.

---

## 3. OBSOLETE / TO-REMOVE

### CSS / theme (light-theme assumptions — all must change for dark)
- **`@theme` light token block** (`app.css:5–27`): `navy-*`/`cyan-*` scales redefined to DS dark palette (`#0f1726`, `#4699d0`, plus status gold/red/green). `cyan` currently overrides Tailwind's built-in — keep that override pattern but with DS values.
- **`.hero-bg` block** (`app.css:287–322`): explicitly light (`#ffffff→#eaf2f9` gradient, navy dot-grid on light). **Remove/replace** with DS dark hero (radial blue glows + dark gradient + masked grid).
- **Flatpickr skin** (`app.css:71–235, ~165 lines`): light-assuming (white text on navy header, light weekday bar). **Rework** for dark surfaces.
- **Tom Select skin** (`app.css:324–419`): hard-codes `background:#fff`, light dropdown. **Rework** for dark.
- **`.confirm-dialog`** built in `confirm_controller.js`: hard-coded light classes (`bg-white`, `ring-navy-900/5`, `text-navy-900`). **Rework** to dark glass.
- **`datepicker_controller.js`** clear button + **`tom_select_controller.js`** option renderer: hard-coded light class strings (`text-navy-900/40`, `text-gray-400`). **Rework.**

### Dead JS/CSS libs (remove — verified present but unused)
- **`alpinejs`** — imported + `Alpine.start()`ed in `app.js` but **zero directives** anywhere. Remove import + dep.
- **`leaflet`** + `leaflet/dist/leaflet.min.css` + marker PNGs — in `importmap.php`, **zero references**. Remove.
- **`glightbox/dist/css/glightbox.min.css`** — in `importmap.php`, **zero references**. Remove.
- **Orphaned vendor dirs** (present in `assets/vendor/`, NOT in importmap): **`konva`, `chart.js`, `signature_pad`** — zero references. Delete dirs. (Note: if 2.4's analytics charts are ever wanted, `chart.js` could be re-added — but currently dead.)
- **`hello_controller.js`** — Symfony scaffold demo. Remove.

### Images / assets (likely obsolete under DS)
- **`hero/hero-desktop.png`** — light-theme homepage hero illustration; DS uses a glass live-match card instead. Likely **remove**.
- **`how-it-works/step-*.png`** (3) — light-theme step images; DS "Jak to funguje" uses CSS mini-mocks (pill chips, mini leaderboard), not photos. Likely **remove/replace**.
- **`winner/winner-*.png`** (3) — winner celebration; DS uses podium component instead. **Re-evaluate** (may keep for OG/share).
- **`logo/logo-icon.png`** — replace with DS brand-mark ("W" gradient tile) + `Wtips.svg` wordmark.
- **Favicons / `og-default.png` / `theme-color #081e44`** — rebrand to Wtips (`#0f1726`).

### Broken / legacy markup
- **Error pages** reference undefined `card`/`card-body`/`btn`/`btn-primary` CSS classes (render unstyled) + emoji headers. Rebuild (also a §1 task).
- **Admin templates + several portal forms** (`sport_match/form`, `import`, `rule_configuration`, `resolve_ties`): legacy `text-gray-*`/`bg-gray-*` + raw inline `<svg>`. Migrate to DS tokens + `twig:ux:icon`.
- **`Leaderboard:GroupLeaderboard`** off-convention `shadow-lg`/gray table → DS dark table.
- **`EmptyState` dead `illustration` prop** — wire it up or remove.
- **Stray typo** `bg-navy-50/40/40` in `admin/tournament/rule_configuration.html.twig`.
- **Missing icon** `lucide:flag` referenced in `invitation/landing.html.twig` — import or replace (throws in dev).

### Conceptually redundant
- **Brand name "Tipovačka"** throughout UI/emails/footer/privacy contact (`kontakt@tipovacka.cz`) → **"Wtips"** + new domain. (Note: dual-deployment memory — main→wtips.cz, tipovacka branch→tipovacka.thedevs.cz; brand string may need to be config-driven, not hard-coded, to serve both.)

---

## 4. CROSS-CUTTING REDESIGN WORK

These touch many templates; sequence them first.

- **Base layout (`base.html.twig`)** — dark body `#0f1726`; rebuild sticky nav as `.wtnav` (W brand-mark gradient tile, primary links, nav-cta CTA, bell, avatar); marketing `.wtfoot` (4-col) for public + app mini-footer; mobile hamburger. **Must change first** — every page inherits.
- **Nav** — reconcile DS's two nav variants (hard-coded Soutěže/Zápasy/Žebříček/Výplaty vs `_partials.js` Přehled/Dashboard/Žebříček with "Hráč" chip). Drop "Výplaty" (chat removed it). Active state = blue underline / accent fill. Admin shield-check pill preserved.
- **Footer** — DS marketing footer copy (tagline "Tipovací soutěže pro firmy, partičky…", legal "© 2026 Wtips. Vše hraje, nic se nesází.", "Vyrobeno v Praze.").
- **Flash messages** — re-tone the 4 configs (success/error/warning/info) from light alert rows to dark glass status pills (DS status colors: green `#3ed598`, red `#ff5d7a`, gold `#f5b544`, blue `#4699d0`).
- **Form theme (`form/_form_theme.html.twig`)** — dark inputs (`rgba(0,0,0,0.22)` bg), uppercase 10px labels, blue focus ring `0 0 0 3px rgba(70,153,208,0.18)`, DS checkbox/radio accent. Affects every form. Datepicker/Tom Select widget hooks preserved but reskinned.
- **Buttons** — adopt DS `.btn` system: `.btn-primary` (blue gradient + glow), `.btn-success` (green gradient, used for "+ Přidat zápas"), `.btn-ghost`, `.btn-link`, sizes `.btn-sm/.btn-lg`, marketing `.btn-light`/`.btn-clear`. Current navy submit + cyan CTA → DS gradients. Uniform 12px radius (per chat).
- **Cards / surfaces** — DS `.card-glass` (translucent + `blur(18px) saturate(140%)`, accent radial glow), `.surface`, `.surface-accent`. Current universal `rounded-2xl bg-white shadow-card ring-navy-900/5` → DS glass. `--shadow-card` tokens re-tuned for dark.
- **Pills / chips** — DS `.pill` + variants (`.pill-live` pulsing, `.pill-success`, `.pill-warn`, `.pill-neutral`, `.pill-accent`). Match-state pill convention (Naplánován/Živě/Odehrán/Odložen/Zrušen) → DS status colors.
- **Empty states** — DS empty cards; finally render the `illustration` prop (currently dead).
- **Breadcrumbs** — decide keep-and-reskin vs replace with DS back-links. Recommend reskin (broad usage).
- **Icons** — keep UX Icons (lucide, `currentColor`) — theme-agnostic. Migrate admin/error/legacy raw `<svg>` → `twig:ux:icon`. Import missing `lucide:flag`. Remove unused `chevron-left`, `medal`, `star` (or use `medal` for podium). Stroke ~1.75 matches DS.
- **Fonts** — adopt **Montserrat 100–900** (current app uses system/Tailwind default). DS uses tight tracking (`-0.04em` display, eyebrow uppercase letter-spaced). See §5(b) for delivery.
- **Eyebrow pattern** — two DS forms (boxed marketing chip vs plain uppercase blue text). Adopt per surface.
- **Avatars** — DS initials-on-gradient (medal gradients for top-3, muted for rest). Current uses initials in plain circles. Adopt gradient scheme.

---

## 5. MIGRATION STRATEGY — DECISION POINTS + RECOMMENDATIONS

### (a) Tailwind v4 vs DS hand-written CSS vs hybrid
**Recommendation: HYBRID — keep Tailwind v4, rewrite `@theme` to Wtips dark tokens, ADD a Wtips component CSS layer (`@layer components`) for the DS's composite components.**

Rationale: The current app is **deeply invested in Tailwind utilities** across 69 templates + the AssetMapper/`tailwind-bundle` pipeline (no Node build). Dropping Tailwind would mean rewriting every template's class soup by hand — enormous, error-prone, and throws away the working pipeline. But the DS's signature elements (`.tip-card` 7-column grid, `.pin-card`, `.wtnav`, `.podium`, `.btn` gradients, pick-distribution bars, `.lb-table`) are **composite components better expressed as semantic CSS classes** than long utility chains. So: (1) rewrite the `@theme` block to DS dark tokens (navy→`#0f1726` family, cyan→`#4699d0` accent, add gold/red/green status colors, dark shadows); (2) add `@layer components` with the DS's component classes ported from the design system's hand-written CSS (`.btn`, `.pill`, `.card-glass`, `.tip-card`, `.wtnav`, `.pin-card`, `.podium`, `.lb-table`); (3) keep using utilities for layout/spacing. This preserves the pipeline, gets DS fidelity on complex components, and keeps utility ergonomics. The DS's CSS is already organized this way (`site.css` + `colors_and_type.css`), so it ports cleanly.

### (b) Font delivery — self-host Montserrat .otf via AssetMapper vs Google Fonts CDN
**Recommendation: SELF-HOST Montserrat via AssetMapper (woff2), NOT the CDN.**

Rationale: The DS uses Google CDN **only because of a preview-environment auth limitation** (the `.otf` files 401'd in the preview iframe) — the chat explicitly notes "production export is expected to use the local fonts." For production, self-hosting wins on: privacy/GDPR (no third-party request — the app has a privacy-policy surface and GDPR consent already), performance (no extra DNS/connection, no render-blocking external CSS), and CSP simplicity. **Convert the provided `.otf` to `woff2`** (smaller, all-browser), place under `assets/fonts/`, serve via AssetMapper's versioned URLs, declare `@font-face` in `app.css` with `font-display: swap`. Use variable-font or a curated weight subset (the DS uses 100–900, but in practice needs ~300/400/500/600/700/900 — subset to those to cut payload). This matches the CLAUDE.md no-Node-build constraint (AssetMapper handles it).

### (c) Component approach — Twig components vs utility classes only
**Recommendation: ANONYMOUS Twig components for the high-reuse DS primitives; utility classes for one-off layout.**

Rationale: The app already uses Twig components (`Breadcrumbs`, `EmptyState` are template-only anonymous components; the Live Components are PHP-backed). The DS's reusable primitives — **match/tip-card, badge/pill, leaderboard-row, button, stat-card, avatar, PIN-input** — appear on many screens and benefit from single-source-of-truth anonymous Twig components (`<twig:Pill variant="live">`, `<twig:TipCard ...>`, `<twig:StatCard ...>`). This keeps templates readable and makes future tweaks one-file changes. Pair with the `@layer components` CSS from (a): the Twig component wraps the semantic class. Do **not** over-componentize page-specific layout — utilities are fine there. The existing PHP Live Components (`Guess:GuessSubmitForm`, `Leaderboard:GroupLeaderboard`, etc.) stay PHP-backed (they have behavior); only their templates get re-skinned.

### (d) Scope / phasing order across surfaces
**Recommendation, in order:**
1. **Foundation (cross-cutting §4): tokens + `@layer components` + fonts + base layout/nav/footer + form theme + buttons/cards/pills.** Nothing else can land correctly until the dark theme + shell exist. Includes flatpickr/tom-select/confirm-dialog reskin.
2. **Error pages** (quick win — currently broken) + **emails** (re-brand, low risk).
3. **Public + Auth + Invitation** (highest external visibility, fewest moving parts, maps to most-finished DS pages: `Landing.html`, `prihlaseni.html`, `registrace.html`). Includes OAuth-button-stub decision.
4. **Player portal** (dashboard, single-match/tip-card, leaderboard, profile) — the DS's richest, most-designed surfaces (`dashboard-hrac.html`, `zebricek.html`, `LiveMatch`). Land the 3-state tip card + podium here.
5. **Organizer portal** (group/tournament/match management, create flows) — maps to organizer kit, more screens, more state.
6. **Admin LAST** (no bespoke DS design — pure token application; lowest external visibility).

Rationale: theme/shell must precede everything; external-facing surfaces have the best DS fidelity and ROI; admin is internal and undesigned. This also front-loads the screens where the DS is most complete, reducing extrapolation risk early.

### (e) Build new backend-dependent features now vs stub frontend-only
**Recommendation: STUB frontend-only for everything that needs new backend; build only what existing data supports.**

- **Build now (data exists):** 3-state tip card (2.1), podium (2.5), accuracy/exact/partial/streak stats (2.4, derivable from `GuessEvaluationRulePoints`), pick distribution within a group (2.2, new query but trivial aggregation), per-match ranking (2.6 partial), pool switcher (2.15, navigation), round/stage label field (2.8 label-only).
- **Stub frontend-only / defer:** premium paywall + contributions + pricing (2.3 — entire commerce subsystem), OAuth (2.14), scorers/timeline + scorer rule (2.6/2.7 — new entities + rule), payouts/bank (2.10 — design intent walked back), full bracket viz (2.8 — DS never draws it), group-stage tables (2.9 — DS never draws it).
- **Marketing subpages (2.11) / landing (2.12):** build landing variant A + the 4 marketing pages as pure static templates (no backend); Ceník blocked on pricing decisions — stub or omit.

Rationale: the redesign's value is **visual** and should ship without waiting on a payments backend. The DS's premium/payout surfaces are speculative product bets (and partially walked back in chat) — render their teasers if desired but gate them behind a feature flag / non-functional state. Δ rank-change (2.4) needs historical snapshots — omit in phase 1 rather than build snapshot infra prematurely.

---

## 6. RISKS & SEQUENCING CONSTRAINTS

1. **Base layout must change first.** `base.html.twig` + `auth/_layout` + `admin/layout` gate every page. The dark body + `.wtnav` + form theme must land before any page re-skin, or pages will mix light/dark and look broken mid-migration. **Sequence: foundation PR before any screen PR.**
2. **Dark theme affects EVERY template.** Switching `@theme` to dark tokens instantly re-colors all 69 templates — but those still using **hard-coded light classes** (`bg-white`, `text-gray-*`, `bg-gray-50`, undefined `card`/`btn` in error pages, `Leaderboard:GroupLeaderboard`'s `shadow-lg`/gray) will look wrong until individually fixed. Expect a "broken middle" period. **Mitigation:** do the token flip + cross-cutting components in one foundation change, then sweep legacy-class templates immediately after; keep a checklist of every `bg-white`/`text-gray-*`/raw-`<svg>` site.
3. **Flatpickr + Tom Select reskins are hand-written CSS (~260 lines combined) hard-coded light.** Both assume white backgrounds/light dropdowns. Re-skinning to dark is fiddly (these are vendor-DOM, not utility-classable) and easy to miss states (hover/selected/disabled/focus). **Test date pickers and member-pickers on every form after the reskin.**
4. **`confirm_controller.js` / `datepicker_controller.js` / `tom_select_controller.js` build DOM with hard-coded light class strings in JS** — these won't be caught by template sweeps. **Audit JS class strings explicitly.**
5. **CI checks must stay green** (per CLAUDE.md): `composer quality` = phpstan L8 + cs:check + tests + `migrations-up-to-date` + `schema:validate`. Any new entity (round/stage field, distribution query, scorer model) **must be added to entities first, then `doctrine:migrations:diff` — never hand-write migrations** (drift breaks CI). Partial unique indexes must be expressed in mapping (`#[ORM\UniqueConstraint(... options: where)]`). New icons must be imported (`ux:icons:import`) before use or **render throws in dev** (`ignore_not_found: false`).
6. **Live Components have PHP behavior** — `Guess:GuessSubmitForm`, `Leaderboard:GroupLeaderboard`, `Auth:*`, `Profile:ProfileForm`. Re-skinning their **templates** is safe; don't break the `#[LiveProp]`/`#[LiveAction]` wiring or the `data-model on(change)` bindings. The DS's React/JS interactivity (pool switcher re-render, premium reveal) must be reimplemented as Stimulus/Live, not ported verbatim.
7. **Turbo is globally disabled** (`data-turbo="false"`). The DS's SPA-like screen switching (pool switcher, modal flows) is React; the Symfony app navigates server-side. Don't assume DS's client-side state machines — re-express as server routes or Live Components. If enabling Turbo for smoother nav, do it surgically (`data-turbo="true"`) per CLAUDE.md.
8. **DS terminology vs domain split.** DS "soutěž/pool" = current `Group`; DS "turnaj/zdroj zápasů" = current `Tournament`. Re-labeling UI to DS terms risks confusing the two-level model. **Decide a consistent Czech vocabulary mapping up front** (e.g. keep Tournament=turnaj as "match source", Group=soutěž as "pool") and apply uniformly, or user-facing copy will contradict itself across screens.
9. **Brand name is hard-coded "Tipovačka" in many places** (nav, footer, emails, privacy contact, `theme-color`, favicons). Dual-deployment (wtips.cz vs tipovacka.thedevs.cz) means the brand string ideally becomes **config-driven**, not a global find-replace — otherwise the `tipovacka` branch deploy shows "Wtips". Coordinate with the dual-deployment setup.
10. **8-box PIN input** — current `Group.pin` is **8 chars**; DS create-wizard shows a **6-digit** PIN in places and 8-char elsewhere (inconsistent in DS). Confirm the PIN length and the join route (`/pripojit`) still align with the new 8-box UI (auto-advance/paste). Backend PIN is already 8 — match the DS 8-box variant, not the 6-digit one.
11. **Email dark-mode caveat.** Do NOT apply the dark glass theme to emails — email-client dark-mode rendering is unreliable; keep emails light with Wtips brand colors only. (Listed as Low effort but flagged so no one "darkens" them.)
12. **OAuth buttons are visual-only in DS.** If shipped as-is without backend, they're dead buttons — either implement (L) or hide/disable to avoid broken UX. Decide before the auth-surface PR.
13. **Premium/paywall teaser** dispatches `wtips:open-premium` with no handler in the app. If the teaser ships, it must either open a real modal or be gated behind a feature flag so it's not a dead-end. Recommend feature-flagging the entire premium surface off until commerce backend exists.