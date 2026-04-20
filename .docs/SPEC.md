# Tipovačka — Product & Build Spec

Brief for Claude Code. The tech skeleton (FrankenPHP, PHP 8.5, Symfony 8.0, CQRS-like with `symfony/messenger` and query objects, Clean Architecture) is provided separately by the owner. This document specifies **business behavior, domain model, and UX expectations** only.

All user-facing copy is in **Czech**. Strings live inline in templates — no i18n translation files in this phase.

---

## 1. Product in one paragraph

A web application where groups of people submit "guesses" (tips) on upcoming football match scores, earn points based on configurable per-tournament rules, and compete on a group leaderboard. Tournaments can be **public** (discoverable, anyone can create a group inside) or **private** (unlisted, joined only by direct link/PIN). Only football is implemented in v1; the domain model reserves a `Sport` aggregate so other sports can be added later without refactoring.

---

## 2. Domain model

```
Sport  →  Tournament  →  Group  →  Membership (User ↔ Group)
                 │           │
                 │           └── Guess (User's score prediction for a Match)
                 └── Match (home vs. away, scheduled time, state, final score)
                 └── RuleConfiguration (which rules are active + points per rule)
```

**Notes**
- `Sport` is seeded with `football` only. The UI does not expose sport selection yet, but every domain boundary that touches a tournament carries a sport reference so adding hockey later is additive.
- A `Group` belongs to exactly one `Tournament`. Many groups can compete in the same public tournament independently.
- Team names are **free-text per match** (no global Team catalog). Home/away team strings are stored directly on the match. A future iteration may introduce a Team aggregate — keep `Match` shaped so that a later migration to team IDs is straightforward.
- `Guess` in v1 captures an exact final score (`homeScore`, `awayScore`). The rule engine, however, must be built so that future guess types (outcome-only, halftime score, first scorer, etc.) can be added without touching existing rule code. Model `Guess` as a value object attached to a user-match pair, with room to evolve into a polymorphic guess structure.

---

## 3. Roles & permissions

Only **two persisted roles**: `ROLE_ADMIN` and `ROLE_USER`. Group management privileges are **not** a separate role — they derive from ownership: a user who owns a group gets group-scoped management rights via Symfony voters, not a role flag.

- **Admin** — platform-wide. Can create/edit public tournaments, edit any match, manage any user, and override any group.
- **Member (ROLE_USER)** — the default authenticated user. Can register, join groups, submit guesses.
  - If a member **creates a group**, they become the group owner and gain management rights *only within that group* (invite/remove members, edit group settings, manually resolve leaderboard ties, configure the group's rules).
  - If that group's tournament is **private**, the same user also owns the tournament and can edit its matches.
  - For **public** tournaments, only admins can create/edit matches; group owners only manage membership and group-local settings.

Authorization must be implemented via Symfony voters (`GroupVoter`, `TournamentVoter`, `MatchVoter`), never with inline role string checks.

---

## 4. Authentication & account lifecycle

- Registration: email + password + nickname (displayed on leaderboards). Real name optional at registration, can be added later in profile.
- Email verification required. Unverified users land on a dedicated "ověření čeká" page and cannot access any tournament functionality — the verification email contains a one-time tokenised link.
- Password reset: request → email link → set new password. Tokens single-use, short TTL (e.g. 1 hour).
- Login with email + password. "Remember me" optional.
- Account soft-delete: users and members are never hard-deleted. A `deletedAt` / `isActive` flag hides them from active lists, but their guesses and historical points remain intact so leaderboards stay consistent. A follow-up GDPR erasure flow (anonymisation) is out of scope for v1 but domain events should be emitted on deletion to enable it later.

---

## 5. Group join flow

A user joins a group through **any one** of four mechanisms:

1. **Email invitation** — any existing member of the group can enter an email. The system emails a one-time tokenised link. Clicking it lets the recipient register/log in and auto-joins them to the group on success.
2. **Shareable join link** — group owner generates a reusable URL. Anyone with the link can join after authenticating. The owner can revoke the link, which invalidates it immediately; a new link can be generated.
3. **PIN entry** — group owner optionally sets an 8-digit numeric PIN on the group. PINs are **unique across the entire platform** (enforce with a DB unique constraint + retry-on-collision generator). A public `/pripojit` page lets any authenticated user enter a PIN to join.
4. **Request-to-join** — on public tournaments, any authenticated user can browse groups and request to join. The group owner sees pending requests and approves/rejects.

**Join eligibility rule**: a group can only be joined while its tournament is **not finished** (no more unfinished matches remain, or the tournament has been explicitly marked closed). Attempting to join a finished tournament must show a clear error.

---

## 6. Tournament & match management

### Tournaments
- Admins create and manage public tournaments.
- Private tournaments are created by a regular user when they start a new group outside of any public tournament; that user becomes the tournament owner.
- Private tournaments are **never** listed on any discovery surface — they're reachable only via direct group link or PIN.
- A tournament carries: name, sport (football), visibility (public/private), description, start/end window (optional), and its RuleConfiguration.

### Matches
- Creation methods, all required:
  1. **Manual single-match form** (home team, away team, kickoff date + time, venue optional).
  2. **Excel/CSV bulk import**. Provide a downloadable template. Validate row-by-row; show preview + error list before commit.
  3. **Manual final score entry** after a match ends — triggers points recalculation.
- External sports API integration is **deferred to phase 2**. Design the `MatchRepository` + match-creation commands so that an API-driven match source can plug in later without rewriting the domain.

### Match state machine
States: `scheduled → live → finished`. Two side states: `postponed` and `cancelled`.
- **Scheduled**: default on creation. Accepts guesses until kickoff.
- **Live**: optional; can be auto-derived from kickoff time passing, or set explicitly. Guesses are locked.
- **Finished**: admin enters the final score. Points are computed for all guesses.
- **Postponed**: kickoff date/time changes. **Existing guesses are preserved** — users are simply notified (domain event) and can edit their guess up to the new kickoff.
- **Cancelled**: all guesses for this match are voided. Any points already awarded are removed. Leaderboards recalculate.

### Editing matches
- Admin (or tournament owner for private tournaments) can edit any field on any match.
- Editing the final score triggers a full recalculation of points for every guess on that match.
- Deleting a match requires confirmation and voids guesses (same behavior as Cancelled).

---

## 7. Guess mechanics

- Each member submits a single guess per match consisting of `homeScore` and `awayScore`.
- Submission and edits are allowed up to **kickoff time** (the scheduled start). At kickoff the guess becomes read-only.
- **Visibility**: all guesses within a group are **always visible to all group members**, including before kickoff. This is an intentional choice — the app is social, not a sealed contest.
- No guess ⇒ no points for that match. Do not auto-fill defaults.

---

## 8. Rule engine (modular points system)

This is the domain's hot spot. It must be trivially extensible.

### Design
- Define a `RuleInterface` with methods like `getIdentifier(): string`, `getDefaultPoints(): int`, `getLabel(): string` (Czech), `evaluate(Guess $guess, Match $match): int` (returns points awarded by this rule for this guess).
- Each rule is a PHP class implementing `RuleInterface`. Tag/attribute discovery via Symfony DI (autoconfigure + a `#[AsRule]` attribute or a container tag) so a developer can add a new rule by dropping **one file** into `src/Domain/Rule/` with no other registration needed.
- A `RuleRegistry` service exposes all discovered rules.
- A `RuleConfiguration` per tournament stores, for each rule identifier, `{enabled: bool, points: int}`. When a rule is enabled, the configured `points` override the rule's `getDefaultPoints()`; if a rule ever returns a fractional/multiplier value, the configured points are the multiplier — but for v1 keep it a flat awarded amount.
- A `GuessEvaluator` iterates enabled rules for a given guess+match and sums points.

### Initial rule set (from the Czech brief)
- **Přesný výsledek** (exact score, e.g. 4:1 guessed correctly) — default **5 points**.
- **Správný tip výsledku** (correct outcome: home win / draw / away win) — default **3 points**.
- **Počet gólů domácí** (correct home team goal count, regardless of away) — default **1 point**.
- **Počet gólů hosté** (correct away team goal count, regardless of home) — default **1 point**.

In the tournament settings UI, the owner sees the full list of discovered rules as checkboxes, with an adjacent points input when enabled. Defaults prefill.

### Rule-edit recalculation
When a rule is toggled or its point value changed **after matches have already been played**, the system runs a **full recalculation** of every guess in the tournament. The UI must present a confirmation modal explaining this before saving (e.g. *"Tato změna přepočítá body všech dosud odehraných zápasů. Pokračovat?"*). Recalculation runs asynchronously via `symfony/messenger`.

---

## 9. Leaderboard & tie-breaking

- Leaderboard is computed per group (not per tournament across groups).
- Ranking is by total points, descending.
- **Tie-breaking is manual**: when two or more members are tied on points at any moment, the system simply presents them as tied. At the end of the tournament, the group owner has an "Resolve ties" UI to drag/drop or assign final positions among tied members. Until resolved, ties display with shared rank.
- Expose a leaderboard drilldown: for each member, show per-match breakdown — their guess, actual score, and points earned (so users can audit scoring).

---

## 10. Notifications

- v1 ships **only transactional email**: account verification, password reset, group email invitations.
- The owner explicitly wants the domain to be ready for richer notifications later. Therefore: emit **domain events** for every meaningful transition (MatchFinished, MatchPostponed, MatchCancelled, GuessDeadlineApproaching, GuessEvaluated, RulesChanged, MemberJoinedGroup, etc.) but **do not implement listeners** for anything beyond the transactional set. Document deferred listeners in `docs/future-notifications.md` inside the repo.
- Email sender: Czech copy, plain templates. Use Symfony Mailer; don't pick a specific ESP here.

---

## 11. Discovery & navigation

- **Marketing/landing page** at `/` for unauthenticated visitors: brand story, how it works, call to action to register. Keep it simple and fast.
- Below the fold (or on a dedicated `/turnaje` page), list all **active public tournaments** with a "Join" CTA. Visitors can browse without authenticating; clicking join prompts login/registration.
- **Logged-in dashboard** at `/nastenka`: shows "My tournaments / My groups" first, then a suggestion row of public tournaments the user isn't in.
- **Private tournaments** never appear on discovery surfaces. They are reachable only via direct group URL, invitation, or PIN entry at `/pripojit`.

---

## 12. UX/UI guidelines

- **Mobile-first responsive** web. No PWA, no native app in v1. Expect the common case to be users submitting guesses from their phone while the match is about to start.
- Stack: **Tailwind CSS** for styling, **Symfony UX Live Components** for interactive server-driven UI (guess submission, leaderboard live updates, rule configuration form), **Hotwired Stimulus** for small client-side behaviors (tabs, modals, copy-to-clipboard for invite links, countdown to kickoff).
- Visual language: modern, clean, sport/e-sport energy. Clear typographic hierarchy. Generous whitespace.
- Color palette:
  - Background: white (`#FFFFFF`).
  - Primary dark: `#081e44`.
  - Primary accent: `rgb(20, 154, 213)` (`#149AD5`).
  - Use the two primaries for headers, CTAs, active states, and data emphasis. Keep accent color for interactive/highlight moments; avoid painting large surfaces with it.
- Iconography: use a single icon library consistently (e.g. Lucide) loaded via a lightweight Twig helper.
- Every page must work well at 360px wide.

---

## 13. Admin surfaces

An admin-only area (`/admin`) built with the same Twig + Live Components stack — **not** a separate admin framework — covering:
- Users list: search, filter by verified/active, block/unblock, view group memberships.
- Tournaments: full CRUD for public tournaments, rule configuration per tournament, match CRUD, Excel import, final score entry.
- Groups: read-only oversight, ability to override an ownership or remove a group in exceptional cases.
- Rules registry view: list of all discovered rules and their default points (read-only; edit is at tournament scope).

Keep routes, controllers, and templates clearly namespaced under `admin/`.

---

## 14. Out of scope for v1 (explicitly)

- Other sports (hockey etc.) — domain ready, UI not exposed.
- External match data APIs.
- Guess types other than exact final score.
- Rich in-app notifications, push, SMS.
- GDPR hard-erasure flow (only soft-delete in v1).
- Team catalog with logos.
- Multi-language UI.
- Tournament-wide leaderboards aggregating across groups.
- Monetization, paid tournaments, prize distribution.

---

## 15. Non-negotiable constraints (summary)

- Czech UI copy, inline in templates.
- Clean Architecture + CQRS-like separation: Commands via `symfony/messenger`, reads via dedicated query objects. No business logic in controllers.
- Rule engine must be extensible by adding **one file** — enforce via autoconfiguration.
- Voters for all non-trivial authorization.
- Domain events emitted for future listener attachment, even if unused today.
- Soft-delete everywhere; no hard deletes of users, groups, guesses, matches.
- Recalculation of points on rule or score changes must be idempotent and async.
