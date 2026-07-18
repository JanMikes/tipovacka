# Wtips — domain & business decision record

**Purpose of this file**: the authoritative, always-current record of *what the product is
and why the business rules are the way they are*. Any Claude/developer session should read
this before touching domain behavior, and **append/update it whenever a business decision
is made or changed** (add a dated row to the Decision log, update the relevant section).
Implementation status of the 2026-07 rebuild lives in [`rebuild/PLAN.md`](rebuild/PLAN.md);
visual/design conventions live in [`redesign/`](redesign/).

Wtips is a Czech friendly match-tipping app ("bez sázek — jen pro radost a vychloubání",
never the word „sázka"). People tip match scores in competitions with friends/colleagues,
earn points by configurable rules, and compete on leaderboards. Revenue comes from an
in-app credit currency (Stripe-backed), never from gambling mechanics or payouts.

## Glossary (Czech UI ↔ code)

| UI (Czech) | Code | Meaning |
|---|---|---|
| zdroj zápasů | `MatchSource` | A match schedule + results. `curated` (admin-managed, reusable) or `private` (hidden internal of one from-scratch competition) |
| soutěž | `Competition` | The thing players are in: members, rules, leaderboard, monetization. THE central user-facing unit |
| globální soutěž | `Competition` with `isGlobal` | Admin-run, publicly discoverable, joinable by paying an entry fee in credits (fee may be 0) |
| zápas | `SportMatch` | Belongs to a MatchSource; state machine scheduled→live→finished (+postponed/cancelled) |
| tip | `Guess` | Per (user, match, **competition**) — same user may tip the same match differently in different competitions |
| organizátor / manažer | Competition `owner` | A relation, not a role. Admin = `ROLE_ADMIN` |
| hráč / tipující | `Membership` | Active membership (leftAt null); anonymous members (no email) exist for offline players |
| kredit | `CreditWallet` balance | **1 credit = 1 Kč**, bought via Stripe Checkout. All in-app prices are in credits so costs stay flexible |
| body | `GuessEvaluation.totalPoints` | Per-guess evaluation, per-rule breakdown stored |
| střelec | `MatchEvent` type `goal` + `GuessScorer` | Scorer guessing (v1 simple rule; fantasy lineups later) |

## Core model

- **MatchSource kinds**: `curated` — created by admin only, browsable in the create-competition
  wizard, scores entered by admin, results propagate to every competition using it.
  `private` — auto-created behind a from-scratch competition, invisible as a concept to users,
  scores entered by the competition manager. *Why one entity*: one shared pipeline for
  matches/import/state machine/score entry; no polymorphic match ownership.
- **Competition ← source linkage**: `selectionMode: all | subset`. `all` inherits every
  source match automatically (new playoff matches flow in ⇒ "new match" notification);
  `subset` uses explicit `CompetitionMatchSelection` rows (the source is never mutated by
  competitions). `includePlayoff` (bool) excludes/includes playoff-flagged matches for
  `all` mode.
- **Rules are per-competition** (`CompetitionRuleConfiguration`), NOT per source. Defaults
  provisioned on creation; each rule = identifier + enabled + points. Rule classes are
  binary/count evaluators; points policy lives entirely in configuration. Guess-feature
  toggles (period tips, scorer tips, overtime tip) ARE the enabled states of the
  corresponding rules — no duplicate flags.
- **Competition end**: a competition ends when all its matches are finished+evaluated AND
  the schedule is known-complete — for source-driven competitions the admin/manager ticks
  „poslední zápas" when entering the final result (`MatchSource.markCompleted`). *Why*:
  playoffs mean future matches are unknowable; an explicit human confirmation beats guessing.

## Business rules & why

### Creating competitions (users)
Multi-step wizard (4 steps: základy+zdroj → pravidla → pozvánky → podpora). Three source
options: from scratch (manual matches + CSV/XLSX import), from curated source (all matches),
from curated source (subset picker). Only admins create curated sources; users never see
"private sources" as a concept. *Why*: clarity over confusion — users think in competitions,
not in data plumbing.

### Global competitions (admin)
Always public, discoverable on the public listing (the ONLY publicly listed competitions).
Entry fee in credits (0 = free) charged once at join; **non-refundable, burned** (revenue —
no payout mechanics, gambling-adjacent features are explicitly out). Admin configures rules
and monetization (`none | premium | boosts`). Rejoining after leaving charges again.
On-behalf tipping and anonymous members are disabled in global competitions (each player
owns their tips). User competitions are joined via PIN / shareable link / email invite only.

### Monetization of a competition — premium XOR boosts
Single column `monetization: none | premium | boosts` — structurally impossible to combine.
- **premium** („Zaplatím za celou skupinu"): manager pays 10 credits per player, charged at
  each join. Insufficient wallet ⇒ join still succeeds, charge recorded as *uncovered*,
  manager notified (also at low balance). At competition start: any uncovered charge ⇒
  ALL premium charges auto-refunded, competition downgraded to `boosts`, manager notified.
  Manager may re-enable premium anytime: charges all current members atomically (must fully
  succeed) and refunds all active boost purchases. *Why charge-at-join*: gradual cost,
  early warnings; *why refund-all on failure*: fairness + a clean binary state.
  Premium managers toggle features for everyone: Lišta tipů, Konkrétní tipy, Měnit tip
  (+ own change-deadline offset).
- **boosts** („Nechám příspěvek na jednotlivcích"): each player may buy per-competition
  boosts. Prices (constants in one config class): Lišta tipů ostatních 10, Konkrétní tipy
  kolegů 20 (includes Lišta — superset, not prerequisite purchase), Měnit tip během
  turnaje 40. Buying is always optional; the competition itself is free.
- Switching direction refunds the other side's payments. Premium price and boost prices are
  business-tunable constants — never scatter literals.

### Tips visibility
Before a match's tip deadline, others' tips are hidden. Entitlements (premium toggle or
boost) unlock: distribution bar (anonymous percentages) and/or concrete member tips.
After the deadline everything is visible to everyone. *Why*: fairness (no copying),
monetizable curiosity.

### Tip locking (deadlines)
Default: **all tips lock at competition start** — first match kickoff, or earlier via the
manager's manual „Uzamknout tipy". Matches added after lock (typically playoffs, often known
only ~2 days ahead) get their own deadline: their kickoff by default, manager per-match
override allowed (never after kickoff). With the „Měnit tip" entitlement: tips changeable
until 1 h before the day's first competition match (offset configurable by premium manager).
*Why lock-at-start*: classic office-tipovačka model — everyone commits before the
tournament; late matches must stay tippable or playoffs would be untippable.

### Scoring
Base rules: exact score (5), correct outcome (3), correct home goals (1), correct away
goals (1) — additive, an exact hit scores all four. Optional per-competition rules:
per-period exact / per-period tendency (tendency excludes exact), overtime final score
(input shown ONLY when the user tips a draw and the rule is enabled — regular-time score
remains the primary evaluated result), scorer hit (points × number of correctly guessed
scorers). Presets in UI: Standardní / Standard + střelec / Vlastní. Changing rules after
evaluations triggers full recalculation (with confirm). Manual tie resolution by the
manager after the competition finishes (drag & drop order) persists as rank overrides.

### Sports
Football (2 poločasy) and hockey (3 třetiny) in v1. Sport lives on the MatchSource, chosen
at creation; it drives period structure, overtime semantics, and copy. Model is
sport-config-driven (period count/labels), so adding sports = data, not code.

### Scorers & roster (phased)
V1: match results include a timeline of `MatchEvent`s (goal / yellow / red, side, minute,
player) entered by admin/manager with autocomplete against the source's `Player` pool
(free-typed names auto-create players). Players guess scorers per match (`GuessScorer`);
`scorer_hit` rule awards points per correct scorer. *Why Player/MatchEvent as first-class
entities now*: the future fantasy-lineup feature (pick a sestava, earn from goals/assists/
cards) needs rosters and event history — the data model must not require re-migration.

### Credits
1 credit = 1 Kč, top-up via Stripe Checkout (min 100). Wallet + immutable ledger
(`CreditTransaction` with `balanceAfter`); balance never negative; every business charge
is a typed ledger entry with references (competition, member, boost). Types: purchase,
admin_adjustment, entry_fee, premium_charge, boost_purchase, premium_refund, boost_refund.
Refunds exist ONLY for premium/boost switching flows — entry fees are final.

### Notifications
In-app center (bell + feed) + email, per-user preference per type × channel; email defaults
on only for important types (guess reminder, premium problems, competition ended). Types:
guess reminder (sweep, missing tips with deadline < 24 h), new match added after start,
match evaluated (your points + standing), competition ended (final standing), premium
low-balance / uncovered / downgraded / re-enabled, boost refunded. Delivery is event-driven
via messenger; reminders + premium reconciliation + snapshots run via symfony/scheduler
(consumed by the prod worker).

### Leaderboard delta
Daily `LeaderboardSnapshot` (competition × user × date → points, rank); Δ shown = movement
vs previous snapshot day. *Why daily, not per-match*: multiple matches per day make
per-match deltas noisy; a day is the natural "round" of a tipovačka.

## Decision log

| Date | Decision | Why |
|---|---|---|
| 2026-06 | Redesign: brand Wtips, dark DS, soutěž=Group, turnaj=Tournament | see `redesign/` |
| 2026-07-10 | Credit wallet: 1 credit = 1 Kč, Stripe Checkout, ledger with balanceAfter | flexible in-app pricing, auditable |
| 2026-07-18 | Full rename Tournament→MatchSource, Group→Competition incl. DB | no users yet; names must match the domain or every future change pays a tax |
| 2026-07-18 | Users no longer create standalone "tournaments"; from-scratch competitions auto-create hidden private sources | one mental model for users; one pipeline in code |
| 2026-07-18 | Premium = charge-at-join + reconcile-at-start (refund-all + downgrade on uncovered) | gradual cost + fair binary outcome |
| 2026-07-18 | Premium XOR boosts via single `monetization` column | make the illegal state unrepresentable |
| 2026-07-18 | Prices: premium 10 cr/player; boosts 10/20/40 (Konkrétní includes Lišta) | cheap enough to be impulsive; superset beats prerequisite-purchase UX |
| 2026-07-18 | Tips lock at competition start; late-added matches get own deadlines; „Měnit tip" = until 1 h before day's first match (premium-configurable) | office-tipovačka tradition + playoff reality |
| 2026-07-18 | Scorers phased: v1 simple scorer rule; Player + MatchEvent first-class now for future fantasy | avoid re-migration later |
| 2026-07-18 | Football + hockey in v1; sport config drives periods | requested; generic model keeps sports = data |
| 2026-07-18 | Notifications in-app + email with per-type×channel prefs | communication is key, user control mandatory |
| 2026-07-18 | symfony/scheduler on existing worker for reminders/reconciliation/snapshots | robust, testable, no new infra process |
| 2026-07-18 | Entry fees burned, non-refundable; global = only public competitions; join-request flow retired; delta = daily snapshots | simplicity, no gambling adjacency, clear join paths |
