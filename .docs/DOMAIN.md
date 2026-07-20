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
| žebříček | `GetCompetitionLeaderboard` result | Standings; a daily `LeaderboardSnapshot` (per competition × user × Prague-day) drives the Δ (movement vs the previous snapshot day) |
| vylepšení / boost | `BoostPurchase` (`BoostType`) | A per-competition boost a player buys in a `boosts` competition: Lišta tipů / Konkrétní tipy / Měnit tip |
| prémium | `Competition.monetization = premium` + `CompetitionPremiumCharge` | Manager pays per player; charge lifecycle `Charged` / `Uncovered` / `Refunded` |
| oznámení | `Notification` (+ `NotificationPreference`) | In-app bell + center and email, per user × type × channel |
| peněženka / kredity | `CreditWallet` + `CreditTransaction` | Balance + immutable typed ledger (`balanceAfter` always reconciles; never negative) |

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
low-balance / uncovered / downgraded / re-enabled, boost refunded, nový hráč se připojil
do soutěže, kterou spravujete (member_joined — in-app default on / email off, skipped when
the joiner is the owner). Delivery is event-driven via messenger; reminders + premium
reconciliation + snapshots run via symfony/scheduler (consumed by the prod worker).

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
| 2026-07-19 | S06 guess-extension semantics: period tips are all-or-nothing (all periods of the sport or none); guess overtime tip mirrors match OT invariants (draw only, not a draw itself, ≥ regular tip); `overtime_exact` compares the OT tip to the match OT directly (the regular draw score is scored by base rules); max 5 scorer tips per guess; disabled-feature payload parts are rejected with 422, never silently dropped; updates are full-replace (partial UIs pass untouched parts through) | keeps rules composable and per-part evaluation independent; explicit rejection beats silent data loss |
| 2026-07-19 | Period tips must SUM to the main (regular-time) tip — mirrors the match-side period-sum invariant | an inconsistent tip (periods 1:0+1:1 with main 3:0) is meaningless and would double-score |
| 2026-07-19 | Editing a tip after a feature was disabled intentionally normalizes (drops) that tip's disabled parts; no-op saves never touch them | full-replace updates must not resurrect disabled parts, but an unchanged „Uložit vše" must never destroy legacy tips |
| 2026-07-19 | Tip deadline = extend-only `max()` composition: the entitlement („Měnit tip") and manager per-match override may only ever EXTEND a window, never shorten it; an override survives a later competition lock; a lock-defining match that is postponed/deleted after its moment passed pins `tipsLockedAt` so tips never silently reopen | a paid boost or an explicit manager decision must be generous, and once tips have closed they must stay closed regardless of schedule churn |
| 2026-07-19 | Tip visibility is competition-wide (userless deadline), never per-viewer: a viewer's entitlement changes only when THEY may tip, never what they may SEE of others' tips | fairness — an entitlement that revealed others' tips early would defeat the hide-before-deadline rule |
| 2026-07-19 | Entitlement/visibility split into two services: `CompetitionEntitlements` (deadline-INDEPENDENT — `canChangeTips` + `isEntitledTo{Distribution,OthersTips}`) and `TipVisibilityGate` (composes the entitlement with the userless deadline: see others iff entitled OR past deadline) | breaks the DI cycle (the deadline resolver injects entitlements for `canChangeTips`, so entitlements must not inject the resolver) while keeping the per-viewer entitlement + userless-deadline-openness composition correct — a viewer with the OthersTips boost sees others' tips before the deadline, others don't; everyone sees post-deadline |
| 2026-07-19 | Manager/admin auto-entitlement applies to VISIBILITY only (`isEntitledTo{Distribution,OthersTips}` — managers/admins always see all tips, matching the pre-S10 GuessMatrix behavior), NOT to `canChangeTips` | tip locking („Uzamknout tipy" + per-match deadlines) is a hard, universal freeze (S07): auto-granting `canChangeTips` to managers would (a) let an owner keep editing after they froze their own competition and (b) hand every system admin a tip-change window in every competition — both break the freeze, so the „Měnit tip" window opens only via the premium toggle or the paid boost |
| 2026-07-19 | Buying OthersTips while already owning TipDistribution charges the FULL OthersTips price (no differential); owning OthersTips hides/blocks the TipDistribution offer (superset entitlement, not an auto-created row); `hideOthersTipsBeforeDeadline=false` on a `none` competition = everyone entitled (the pre-monetization „show all" switch) | keep boost pricing dumb and predictable; the superset is an entitlement fact, not a second purchase; preserve pre-S10 „don't hide" behaviour under the new gate |
| 2026-07-19 | Rejoining a premium competition does NOT re-charge an already-paid slot: the join hook re-spends only when the (competition,member) charge row is Uncovered or Refunded; an already-`Charged` row early-returns | the row is refundable exactly once and leaving never refunds it, so a second debit would be a permanent PREMIUM_PER_PLAYER loss and break refund symmetry |
| 2026-07-19 | `EnablePremium` is idempotent: re-invoking on an already-premium competition throws `PremiumAlreadyEnabled` before any wallet movement (controller → friendly „Soutěž už je prémiová.") | enabling charges N×PREMIUM_PER_PLAYER, so a double-submit would debit the owner again with no new rows; re-enable is only meaningful from a non-premium state |
| 2026-07-19 | Visibility boosts (Lišta/Konkrétní) are never sold to a buyer already entitled for free — a manager/admin is auto-entitled to see tips, so the Boost:Panel hides the offer and `PurchaseBoostHandler` rejects it (`BoostNotAvailable::becauseAlreadyEntitled`); tip_change stays buyable (managers are NOT auto-entitled to tip changes, subject to the freeze) | buying what you already get free just burns credits; the visibility/tip-change auto-entitlement split mirrors the S07 tip-freeze decision |
| 2026-07-19 | S11 notification dedup is delivery-level & channel-agnostic: the `Notifier` writes ONE `Notification` row whenever it delivers on ANY channel (in-app OR email), stamping `inAppVisible` = the user's in-app preference (feed/unread queries filter it, so email-only rows never surface); `competition_ended` fires only when the source is completed AND every included match is finished+evaluated (no match still Scheduled/Live/Postponed), driven off BOTH `MatchSourceCompleted` and per-match `GuessesEvaluatedForMatch`, guarded once by `endedNotifiedAt`; a source reopen clears the guard + deletes the sent rows so a corrected standing re-sends | a channel-dependent dedup re-sent the hourly guess-reminder email forever to in-app-off users (spam); stamping „ended" before the last evaluation committed froze stale/missing points permanently |
| 2026-07-19 | S12 leaderboard delta = a daily `LeaderboardSnapshot` (competition × user × Prague-calendar day → rank + points), captured 03:00 Europe/Prague by the scheduler and idempotent per (competition, day); the Δ shown on the board is movement vs the latest snapshot day **strictly before** today (a member absent from that baseline shows „nový"); a member breakdown „Vývoj" list reads the same rows | a day is the natural „round" of a tipovačka — per-match deltas are noisy when several matches land the same day; comparing to a fixed prior day keeps the arrow stable through the day |
| 2026-07-20 | S13 admin consolidation: the admin area **deep-links into the voter-guarded portal** (competition detail, source detail = the matches-management page) rather than keeping duplicate admin views — the only admin-owned surfaces are the cross-cutting lists (sources, competitions, users, credits, rules) + the global-competition create/edit forms; „Kredity → Transakce" is a cross-wallet ledger filterable by transaction type and competition, and the global-competition edit page shows a read-only premium-charges / active-boosts panel; all project docs reconciled to the as-built system | one page per concern with no duplicate controllers to drift; admins see the exact same detail members do, plus the money movements (`entry_fee` / `premium_charge` / `boost_purchase` / refunds) the ledger surfaces |
