# Wtips domain rebuild — master plan

This is the single source of truth for the autonomous rebuild of the app domain
(data sources → competitions → premium/boosts → notifications → delta).
It is designed to be **idempotent and resumable**: any session can read this file,
see exactly which stage is where, and continue.

- Detailed per-stage specs live in [`stages/`](stages/) — one file per stage.
- Status is tracked ONLY in the status board below (stage files carry no status).
- Every stage ends with a green quality gate and a pushed commit on `main`
  (pushing to `main` auto-deploys to production wtips.cz — acceptable, no real users yet).

## How to resume (orchestrator protocol)

1. Read this file. Find the first stage that is not `done`.
2. If `in-progress`: inspect `git status`/diff against the stage's Acceptance checklist,
   finish the remainder (or reset the working tree and restart the stage — stages are
   scoped to be restartable).
3. Implement via subagent(s): give the agent the stage file path + this file's
   Conventions section; the agent reads specs itself. Big stages may be split across
   several agents (backend / UI / tests), sequentially or in parallel worktrees.
4. Run the full quality gate (below). Fix until green.
5. Review the stage diff (adversarial code review — subagents), fix confirmed findings.
6. Commit (`S<NN>: <summary>`), push, wait for CI green (`gh run watch`), then set the
   stage `done` with the commit sha here, commit and push the plan update.

## Quality gate (every stage)

All inside Docker (`docker compose exec web …`):

```
composer cs:fix
composer quality              # phpstan level 8 + unit tests
vendor/bin/phpunit tests/Integration/<dir>   # chunked! full-suite in one go OOMs (exit 137)
                                             # chunks: Command, Query, Event, Portal, Admin,
                                             #         Auth, Invitation, Public, Webhook, rest
bin/console lint:twig templates/
bin/console doctrine:schema:validate
bin/console doctrine:migrations:migrate --no-interaction   # against dev DB, from scratch when schema changed
composer db:reset             # fixtures still load
```

Rules that apply to every stage (in addition to `CLAUDE.md`):

- Czech UI copy, vykání, no emoji (Lucide icons; import them: `bin/console ux:icons:import lucide:<name>`).
- Migrations: generate with `doctrine:migrations:diff`. **Exception — renames**: hand-write
  `ALTER TABLE … RENAME` (diff produces destructive DROP/CREATE); afterwards
  `schema:validate` + CI `migrations-up-to-date` must pass.
- Domain behavior & business decisions always get tests (entity unit tests for invariants +
  handler integration tests + flow tests for the happy path). Not chasing coverage %.
- New entities follow the house style: UUIDv7 from `ProvideIdentity`, property hooks,
  `recordThat()` domain events, no flush in repositories.
- Fixtures: keep `fixtures/AppFixtures.php` constants authoritative; update
  `.docs/FIXTURES.md` when fixtures change.

## Locked product decisions (answered by Honza, 2026-07-18)

1. **Scorers**: phased. V1 = simple "trefený střelec" rule (tip which players score,
   points per correct scorer). Data model prepared for later fantasy lineups:
   `Player` (per-source roster pool) and `MatchEvent` (goal/yellow/red, minute, player)
   are first-class from the start.
2. **Rename**: full rename incl. DB: `Tournament` → `MatchSource` (zdroj zápasů),
   `Group` → `Competition` (soutěž). User-facing "create private tournament" disappears;
   users create competitions (from scratch ⇒ hidden private source). Only admins manage
   visible (curated) sources.
3. **Premium charging**: charge manager per player at join time. Insufficient wallet ⇒ join
   still succeeds, uncovered charge recorded + manager notified. At competition start:
   any uncovered ⇒ refund all premium charges, downgrade to `boosts`, notify. Manager can
   re-enable premium anytime (charges all current members atomically, refunds active boosts).
4. **Tip locking**: tips lock at competition start (first match kickoff, or earlier manual
   "Uzamknout tipy"). Matches added after lock (playoffs) get their own deadline (their
   kickoff by default, manager per-match override allowed). "Měnit tip" boost/premium
   toggle: change tips until 1 h before the day's first competition match
   (offset configurable by manager on premium competitions).
5. **Pricing** (constants in one config class, 1 credit = 1 Kč): premium 10 credits/player;
   boosts per player per competition: Lišta tipů ostatních 10, Konkrétní tipy kolegů 20
   (includes Lišta), Měnit tip během turnaje 40.
6. **Notifications**: in-app (bell + center) + email, per-user preference per type × channel.
   Email defaults on only for important types (reminder, premium issues, competition ended).
7. **Sports**: football + hockey fully functional in v1. Sport chosen on source creation
   (and from-scratch wizard); sport drives period structure (2 halves / 3 thirds) and copy.
8. **Scheduling**: the recurring jobs (premium reconcile, guess reminders, daily
   leaderboard snapshots) run as host-cron console commands (`app:premium:reconcile` /
   `app:guess-reminders:send` / `app:leaderboard:capture-snapshots`) invoked by the box
   crontab (lily.srv `apps/wtips/cron.d/wtips`). symfony/scheduler was introduced in S10
   and later removed for ops visibility/monitorability (see DOMAIN.md decision log, 2026-07-20).

Default assumptions taken (veto anytime): entry fees are non-refundable burned credits
(no payouts, leaving refunds nothing); global competitions are the only publicly
discoverable competitions (user competitions join via PIN/link/invite); the public
join-request flow is retired in S09; on-behalf tipping and anonymous members are
disabled for global competitions; rejoining a paid global competition charges the fee again.

## Target domain (summary)

- **MatchSource** (`kind: curated | private`): owns `SportMatch` rows + `Player` roster pool.
  Curated = admin-managed, reusable by any competition, scores by admin. Private = hidden
  1:1 internal of a from-scratch competition, scores by that manager. One shared pipeline
  (import, state machine, score entry) for both.
- **SportMatch**: + `isPlayoff`, period scores (per sport), after-overtime score (draw only),
  `MatchEvent` timeline (goal/yellow/red + minute + player), live score updates.
- **Competition**: membership/join/leaderboard machinery (from Group) + `matchSource`,
  selection `all | subset` (+`CompetitionMatchSelection`), `includePlayoff`,
  `isGlobal` + `entryFeeCredits`, `monetization: none | premium | boosts` (XOR by column),
  premium feature toggles + tip-change offset, own `CompetitionRuleConfiguration`.
- **Guess**: + period scores, overtime score (shown only on draw tip when rule enabled),
  `GuessScorer` rows. New rules: `scorer_hit`, `period_exact`, `period_tendency`,
  `overtime_exact` (evaluator generalized to count × points).
- **Credits**: generic debit/refund with new ledger types (`entry_fee`, `premium_charge`,
  `boost_purchase`, `premium_refund`, `boost_refund`) + references (competition, member).
- **Notifications**: `Notification` + `NotificationPreference`, bell + center, event-driven
  + scheduled reminder sweep.
- **Delta**: daily `LeaderboardSnapshot` per competition; leaderboard Δ vs previous day.

## Status board

| Stage | Name | Status | Commit |
|---|---|---|---|
| S01 | Domain rename (MatchSource / Competition) | done | 22ee7c4 |
| S02 | Source kinds & competition–match linkage | done | 598337c |
| S03 | Credit spending core (debit/refund API) | done | 00a5729 |
| S04 | Rules per competition | done | 9b542ff |
| S05 | Sports & match result model (periods, OT, events, roster, live) | done | b255cbd |
| S06 | Guess extensions & new scoring rules | done | e08171c |
| S07 | Tip locking model | done | 06e5c1a |
| S08 | Create-competition wizard | done | 9b49a1d |
| S09 | Global competitions & entry fees | done | d5c89a1 |
| S10 | Premium & boosts commerce (+ scheduler infra) | done | c969e43 |
| S11 | Notification center | done | ed27a54 |
| S12 | Leaderboard delta & snapshots | done | f4d1818 |
| S13 | Admin consolidation, docs, e2e, final review | done | 33d54d0 |

Statuses: `todo` → `in-progress` → `done (sha)`. Update immediately after each transition.

## Stage dependency notes

S01 → S02 → everything. S03 independent after S01 (needed by S09/S10).
S04 needed by S06/S08. S05 needed by S06. S07 after S02 (uses selection timestamps).
S08 after S04+S07 (wizard configures rules + shows deadline copy). S09 after S03+S08.
S10 after S09 (wizard monetization intent becomes live commerce; brings scheduler).
S11 after S10 (premium notifications; uses scheduler). S12 after S06 (evaluation events).
S13 last.
