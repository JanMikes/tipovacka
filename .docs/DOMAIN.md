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
| tým | `Team` | A club/side as a first-class identity across all its matches. Hybrid scope: global admin **directory** (curated sources) or **local** to a private source. Optional short name / country (ISO alpha-2) / brand color / uploaded logo; anchors the per-team `Player` roster |
| tip | `Guess` | Per (user, match, **competition**) — same user may tip the same match differently in different competitions |
| organizátor / manažer | Competition `owner` | A relation, not a role. Admin = `ROLE_ADMIN` |
| hráč / tipující | `Membership` | Active membership (leftAt null); anonymous members (no email) exist for offline players |
| kredit | `CreditWallet` balance | **1 credit = 1 Kč**, bought via Stripe Checkout. All in-app prices are in credits so costs stay flexible |
| body | `GuessEvaluation.totalPoints` | Per-guess evaluation, per-rule breakdown stored |
| střelec | `MatchEvent` type `goal` + `GuessScorer` | Scorer guessing (v1 simple rule; fantasy lineups later) |
| žebříček | `GetCompetitionLeaderboard` result | Standings; a daily `LeaderboardSnapshot` (per competition × user × Prague-day) drives the Δ (movement vs the previous snapshot day) |
| vylepšení / boost | `BoostPurchase` (`BoostType`) | A per-competition boost a player buys in a `boosts` competition: „Jak tipují ostatní?" / „Přesné tipy soupeřů" / „Počkejte si na sestavy". Those three names are the ONLY user-facing names — `BoostType::label()` is their single home |
| prémium | `Competition.monetization = premium` + `CompetitionPremiumCharge` | Manager pays per player; charge lifecycle `Charged` / `Uncovered` / `Refunded` |
| oznámení | `Notification` (+ `NotificationPreference`) | In-app bell + center and email, per user × type × channel |
| peněženka / kredity | `CreditWallet` + `CreditTransaction` | Balance + immutable typed ledger (`balanceAfter` always reconciles; never negative) |

## Core model

- **MatchSource kinds**: `curated` — created by admin only, browsable in the create-competition
  wizard, scores entered by admin, results propagate to every competition using it.
  `private` — auto-created behind a from-scratch competition, invisible as a concept to users,
  scores entered by the competition manager. *Why one entity*: one shared pipeline for
  matches/import/state machine/score entry; no polymorphic match ownership.
- **Competition ← source linkage — a soutěž draws from MANY zdroje.** Its match scope is
  the UNION of its `CompetitionSource` **layers**, each naming one zdroj and carrying its
  OWN `selectionMode: all | subset | teams` + `includePlayoff`. `all` inherits every match
  of that layer's source automatically (new playoff matches flow in ⇒ "new match"
  notification); `subset` uses explicit `CompetitionMatchSelection` rows (the source is
  never mutated by competitions); `teams` uses explicit `CompetitionTeamFilter` rows and
  dynamically includes every match OF THAT LAYER'S SOURCE where a filter team plays (home
  OR away) — so a team's later-added match (e.g. a playoff fixture) auto-joins, just like
  `all`. `includePlayoff` excludes/includes playoff-flagged matches for `all` mode only
  (`teams`/`subset` always keep playoff). Multiple teams filter as a union; a team must
  belong to its layer's source resolution scope (curated → global directory team of the
  sport; private → local team of the source), and team-filter uniqueness is per LAYER, not
  per competition (one club plays in several zdroje). `teams` is offered in both the private
  and the admin/global create flow (a global competition never hand-picks, so it is `all`
  or `teams`, never `subset`).
  - A match belongs to the soutěž iff the layer fed by ITS OWN zdroj exists and accepts it.
    `Competition.headlineSource` (column `headline_source_id`) is layer 0's zdroj, kept
    denormalised so display, sport derivation and „who may create a competition here" stay
    one join away and stay paginable. It is **not** the scope, which is why it is no longer
    called `matchSource`; it is repointed automatically whenever layer 0 changes.
    `competitions.selection_mode` / `include_playoff` are **gone** — the mode is a per-zdroj
    answer and lives only on the layer.
  - **One sport per soutěž.** Every layer must share the first one's sport
    (`CompetitionSourcesSportMismatch`), because rules are configured once per competition
    and phrased in that sport's vocabulary (2 poločasy vs 3 třetiny).
  - **The same fixture can arrive from two zdroje** and there is no cross-source identity
    for it (`externalId` is unique per zdroj; a club is a global directory `Team` in a
    curated zdroj and a local one in a private zdroj). The wizard therefore WARNS on a
    name+kickoff heuristic (`ScopeDraftResolver`) and never silently drops one — a cup
    replay or a two-legged tie is a legitimate pair.
- **Rules are per-competition** (`CompetitionRuleConfiguration`), NOT per source. Defaults
  provisioned on creation; each rule = identifier + enabled + points. Rule classes are
  binary/count evaluators; points policy lives entirely in configuration. Guess-feature
  toggles (period tips, scorer tips, overtime tip) ARE the enabled states of the
  corresponding rules — no duplicate flags.
- **Competition end**: a competition ends when all its matches are finished+evaluated AND
  the schedule is known-complete — i.e. EVERY zdroj it draws from has been ticked
  „poslední zápas" (`Competition.scheduleIsComplete`, an AND over the layers; one
  still-running zdroj keeps the whole soutěž open). For source-driven competitions the
  admin/manager ticks it when entering the final result (`MatchSource.markCompleted`). *Why*:
  playoffs mean future matches are unknowable; an explicit human confirmation beats guessing.

## Business rules & why

### Creating competitions (users)
Multi-step wizard (4 steps: základy+zdroje → pravidla → pozvánky → podpora). „Zápasy
soutěže" is a **basket**: one zdroj's editor (from scratch / curated all / curated subset /
curated by team) commits into a card, and the basket then offers „Přidat zdroj zápasů" and
„Vlastní zápasy". Leaving the step with a usable editor open commits it, so the single-zdroj
path — still the common one — is unchanged. The picker narrows as you go: other sports and
already-basketed zdroje stop being offered. Only admins create curated sources; users never
see "private sources" as a concept — „Vlastní zápasy" creates one on demand. *Why*: clarity over confusion — users think in competitions,
not in data plumbing.

**The basket stays editable for the life of the soutěž** — same form, at
`/souteze/{id}/zapasy` (see [`features/competition-scope.md`](features/competition-scope.md)).
Zdroje may be added and removed at any time; a zdroj joining late carries its own tip
deadlines. Editing a soutěž never changes what another soutěž tips: the screen only ever
writes THIS competition's layers, and the **matches** of a zdroj are editable in place only
when that zdroj is the soutěž's own private one (organizer-owned, no other soutěž draws from
it). A global soutěž's scope stays an admin decision.

### Global competitions (admin)
Always public, discoverable on the public listing (the ONLY publicly listed competitions).
Entry fee in credits (0 = free) charged once at join; **non-refundable, burned** (revenue —
no payout mechanics, gambling-adjacent features are explicitly out). Admin configures rules
and monetization (`none | premium | boosts`). Rejoining after leaving charges again.
On-behalf tipping and anonymous members are disabled in global competitions (each player
owns their tips). User competitions are joined via PIN / shareable link / email invite only.

**Inviting into one.** A global competition has no PIN and no shareable link — nothing
secret to hand over — but it is still shareable, and by every member, not only the admin
who created it. What gets shared is the competition's own **public invitation page**,
`/souteze/{id}/pozvanka` (`competition_global_invitation`): a landing that names the
competition and its entry fee to somebody with no account yet, carries the intent through
sign-up and the verification mail like every other landing (see `join-intent`), and then
performs the ordinary paid join. Being invited buys no discount and no free seat — an
insufficient balance lands on the top-up page with the shortfall named. The e-mail
invitation for a global competition therefore creates **no `CompetitionInvitation` row and
no Membership**: it only mails that link (`InviteToGlobalCompetitionCommand`). The
private-competition pipeline, which pre-provisions a seat so the organizer can tip on the
invitee's behalf, refuses global competitions outright — that seat costs money.

**Where an admin creates one.** Three entry points, all hitting the same
`CreateGlobalCompetitionCommand` / `GlobalCompetitionComposer`: (1) the **create-competition
wizard** itself — an admin-only „Typ soutěže → Globální soutěž" toggle on step 1 turns the
normal „Vytvořit soutěž" flow into the global creator (adds the entry-fee field, restricts
to curated sources + all matches, skips the „Pozvánky" step, offers monetization
none/premium/boosts, keeps the rules step); (2) the dedicated admin page
`admin_global_competition_create` (`/admin/souteze/globalni/vytvorit`); (3) the „Rovnou
vytvořit globální soutěž" checkbox when an admin creates a curated source. The wizard toggle
is `ROLE_ADMIN`-gated at both the render (`isAdmin`) and action (`useGlobalKind`) level, and
`isGlobalKind` is a non-writable LiveProp, so the mode is unreachable by a non-admin.

### Monetization of a competition — premium XOR boosts
Single column `monetization: none | premium | boosts` — structurally impossible to combine.
- **premium** („Zaplatím za celou skupinu"): manager pays 10 credits per player, charged at
  each join. Insufficient wallet ⇒ join still succeeds, charge recorded as *uncovered*,
  manager notified (also at low balance). At competition start: any uncovered charge ⇒
  ALL premium charges auto-refunded, competition downgraded to `boosts`, manager notified.
  Manager may re-enable premium anytime: charges all current members atomically (must fully
  succeed) and refunds all active boost purchases. *Why charge-at-join*: gradual cost,
  early warnings; *why refund-all on failure*: fairness + a clean binary state.
  Premium managers toggle the same three boosters on for everyone: „Jak tipují ostatní?",
  „Přesné tipy soupeřů", „Počkejte si na sestavy" (+ own change-deadline offset), and the
  toggles carry exactly those names.
- **boosts** („Nechám příspěvek na jednotlivcích"): each player may buy per-competition
  boosts. Prices (constants in one config class): „Jak tipují ostatní?" 15, „Přesné tipy
  soupeřů" 35 (includes the distribution — superset, not prerequisite purchase), „Počkejte
  si na sestavy" 50. Buying is always optional; the competition itself is free.

**One name and one sentence per booster, on every surface.** `BoostType::label()` and
`::description()` are the single home of both; no surface writes its own boost prose. The
sentences are: „Odemkněte procentuální rozložení tipů 1 / X / 2 ostatních hráčů ve vaší
soutěži. Konkrétní tipy zůstávají skryté." / „Chcete vědět, jak tipuje váš soupeř? Odemkněte
si přesné tipy ostatních hráčů ve vaší soutěži." / „Chcete si počkat na soupisky? Odemkněte
si možnost upravit své tipy až 1 hodinu před začátkem zápasu." The last one states the
DEFAULT window (`tipChangeOffsetMinutes` = 60); a competition that moved the offset gets the
real value substituted in the one panel branch that knows it. The surface the first booster
unlocks carries the same name („Jak tipují ostatní?") — booster and product are one thing, so
they read as one thing. **This includes the LOCKED overlays**: a veiled card or strip renders
`label()` (unless its own heading already is that name) and `description()`, never a teaser of
its own. What is booster-INDEPENDENT — why a surface is locked — is six constants on the same
enum: `LOCKED_PREMIUM_CTA/_NOTE`, `LOCKED_OVER_CTA/_NOTE`, `LOCKED_AFTER_MATCH_CTA/_NOTE`.
Where a competition has nothing to sell (`monetization = none`) the overlay renders
`LOCKED_AFTER_MATCH_NOTE` rather than `description()`, which would promise a purchase that
does not exist.
- Switching direction refunds the other side's payments. Premium price and boost prices are
  business-tunable constants — never scatter literals.

**A boost cannot be bought once the competition is *fully over*.** „Fully over" is defined
against the competition's own match scope (`CompetitionMatchProvider` — never re-derived):

> A competition is **fully over** when it includes **at least one** match and **none** of its
> included matches can still produce a result — i.e. no included match is `Scheduled`, `Live`
> or `Postponed`. `Finished` matches carry a final result and `Cancelled` ones never will, so
> both count as settled. A competition with an empty scope is *not* over (there is nothing to
> have ended).

This is deliberately **not** „past the last kickoff": a match that kicked off but whose result
has not been entered can still move the standings, so its competition is still live. It is the
same settled-ness test the `competition_ended` notification gate uses (`hasUnsettledMatches`).
`Boost:Panel` drops the purchase CTA and explains why; `PurchaseBoostHandler` refuses with
`CompetitionAlreadyOver` so a stale page cannot burn credits either.

### Tips visibility
**Other players' tips are readable iff the viewer is ENTITLED, or the match HAS A FINAL
RESULT.** Entitlement = the premium toggle the organizer switched on for everyone, or this
viewer's own boost; it unlocks the distribution bar (anonymous percentages) and/or the
concrete member tips. The free half is the **result** („odehráno" = `SportMatchState::Finished`,
a score entered), and it opens the tips to everyone, members and — where a page is public —
non-members alike. Each viewer always sees their OWN tips. *Why*: fairness (no copying),
monetizable curiosity.

**The tip DEADLINE plays no part in this decision** (2026-07-30; until then the rule was
„entitled OR past deadline"). A deadline is an intention, a result is a fact, and two holes
followed from trusting the intention:

- a kickoff passes and the match is **not played** — an organizer late to postpone — so the
  deadline is behind us and every tip became readable for a match still to be played;
- a **late-added** match's deadline is its own kickoff (`EffectiveTipDeadlineResolver` row 2),
  so postpone-then-reschedule could **reopen tipping after such a reveal had happened** —
  copyable tips by accident of admin timing.

The result test closes both without asking anyone to be punctual. (What was never broken:
tips do not silently reopen — a non-late-added match keeps `deadline = lock moment` wherever
its kickoff moves, and the postpone/delete handlers pin `tipsLockedAt` so a lock moment is
reached only once.)

One rule, one home: `TipVisibilityGate` — concrete tips **and** the distribution, every
surface (`/zapasy`, nástěnka, competition detail, match detail's „Pořadí za zápas", the
`/zebricek/matice` matrix, on-behalf tipping). Its consumers in code are exactly four:
`TipStatsProvider` (the distribution, batched per page), `GetCompetitionGuessMatrixQuery`
(the matrix, masked per cell), `ManageMemberTipsController` (on-behalf tipping) and
`CompetitionMatchDetailController` (which gates both „Pořadí za zápas" and „Tipy členů").
**The gate belongs to the caller, not to the read model**: `GetMatchRanking` deliberately
takes no viewer and hides nothing, so the page either hands its whole board to the template
or none of it. (The 2026-07-30 log row below also lists `GetGuessesForMatchInCompetition`,
a fifth consumer that masked its own rows; that was true then — ui-nav item 22 superseded
the surface it fed and item 33 deleted the query.) No other place may compare `now` against
a deadline to decide visibility. **The decision is explicitly revisitable through one knob:**
`TipVisibilityGate::$freeRevealRequiresResult` (wired in `config/services.php`) — `false`
restores the deadline reveal everywhere at once, and a test pins that alternative so it
cannot rot. This reverses ui-nav item 10's „a LIVE match's „Pořadí za zápas" is unlocked
because its deadline passed": the section still renders, its tips sit behind the CTA.

**Who may open the matrix.** `/zebricek/matice` („Tabulka tipů") is reachable by **any
member** (`leaderboard_details` = member or admin, unchanged and NOT widened; anonymous
visitors are refused) and decides readability **per match**: finished columns are readable,
the rest carry a lock and one `Boost:Panel` unlock CTA. A managed/owned competition buys no
free pass.

**The organizer is not privileged.** A manager (and a system admin) buys the same
entitlements as everyone else — they play too, so a free look would be an in-game
advantage over the members who paid. On-behalf tipping („Tipovat za členy") therefore shows
only WHETHER a member's tip is filled (and lets the manager overwrite it), never the scores.
The knob is one constructor argument (`CompetitionEntitlements::$managersSeeTipsForFree`,
wired in `config/services.php`) should the decision ever be revisited.

**Where the bar shows.** The distribution surface — the real 1 / X / 2 bar when entitled,
a locked placeholder with a one-click buy modal when not — renders on EVERY surface that
lists matches: „Vaše zápasy", the nástěnka's upcoming list, the competition detail's „Moje
tipy" rows, the generic match detail (per competition), and the competition-scoped match
page. One component (`Match:TipStats`), one batch resolver (`TipStatsProvider`) whose cost
scales with the number of competitions on the page, never the number of matches.

### Tip window (opening & deadlines)
A match's tip window inside a competition has **two ends**, both optional overrides on the
same `CompetitionMatchSetting` row: „tipování otevřeno od" (`opensAt`, **admin-only**) and
the uzávěrka (`deadline`, the manager's). `EffectiveTipDeadlineResolver::windowFor` is the
single authority for both; `isLocked` and `assertOpenForTipping` fold them together, so a
match is untippable when it has not opened yet, when the deadline passed, or when the match
itself no longer takes tips.

**Opening („tipování otevřeno od")**: unset by default — a match is tippable from the
start, exactly as before this existed. When set, the match stays **visible in every list**
(hiding it would read as „not in this soutěž") but takes no tip until that moment: the card
dims, wears a clock and shows the admin's optional note (`openingNote`, e.g. „Otevřeme po
losu skupin"). The block is universal — **managers and admins included**, on-behalf tipping
too — and enforced in every guess-writing handler, not only in the UI. It must fall
strictly before the match's effective deadline (rejected at write time, otherwise the match
could never be tipped).

The opening is **not** per viewer: no entitlement opens a window early, and **waiting is not
for sale** — a waiting match carries no booster paywall, and `TipChangeUnlock` offers
„Počkejte si na sestavy" only where it would move the **uzávěrka**. (Selling early access
was built and withdrawn on 2026-07-30; see the decision log. Restoring it means reversing
one method in `EffectiveTipDeadlineResolver`, one comparison in `TipChangeUnlock` and the
paywall block in the waiting branch of `Guess/GuessSubmitForm`.)

Default: **all tips lock at competition start** — first match kickoff, or earlier via the
manager's manual „Uzamknout tipy". That manual lock happens **now („Ihned") or at a chosen
future moment („V určený čas")** — the same stored moment either way (`Competition.tipsLockedAt`
= „locked from"), so a scheduled lock needs no job: the deadline resolver already computes
`min(lock moment, kickoff)` and the lock fires by time passing. A scheduled moment must be in
the future and **before the competition start** (a later one would push the lock past the
automatic one and reopen closed tips); it is freely changed or cancelled until it fires, and
cancelling it is the same action as unlocking. Matches added after lock (typically playoffs,
often known only ~2 days ahead) get their own deadline: their kickoff by default, manager
per-match override allowed (never after kickoff). With the „Měnit tip" entitlement: each tip stays
changeable until 1 h before **that match's own kickoff** (offset configurable by premium manager) —
per match, not per day.
*Why lock-at-start*: classic office-tipovačka model — everyone commits before the
tournament; late matches must stay tippable or playoffs would be untippable.

### Scoring
Base rules: exact score (5), correct outcome (3), correct home goals (1), correct away
goals (1) — additive, an exact hit scores all four. Optional per-competition rules:
the four **period** rules — per-period exact (5), per-period home goals (1), per-period
away goals (1), per-period tendency (2; tendency alone excludes exact, the two goal rules
do not) — the **combined after-overtime final score** (one score pair meaning „after
prolongation *or* shootout"; input shown ONLY when the user tips a draw and the rule is
enabled — regular-time score remains the primary evaluated result), and scorer hit
(points × number of correctly guessed scorers). All optional rules default to disabled.
Presets: **Standardní** (base) / **Maxi** (base + period exact/goals + overtime) /
**Vlastní (připravujeme)** in the create wizard; the post-creation rules screen keeps
Standardní / Standard + střelec / Vlastní. Per-rule UI copy has ONE home
(`RulePresetProvider::RULE_COPY`). Changing rules after evaluations triggers full
recalculation (with confirm). Manual tie resolution by the manager after the competition
finishes (drag & drop order) persists as rank overrides.

### Sports
Football (2 poločasy) and hockey (3 třetiny) in v1. Sport lives on the MatchSource, chosen
at creation; it drives period structure, overtime semantics, and copy. Model is
sport-config-driven (period count/labels), so adding sports = data, not code.

### Teams
Teams are a first-class `Team` entity (not free-text strings). A team is one identity across
every match it plays — the home for an uploaded **logo** (absent one, a contrast-safe initials
**monogram** renders instead), a short name, a **country** (ISO 3166-1 alpha-2, drawn as a round
flag badge) and a brand color, and the anchor for the per-team `Player` roster. **Hybrid scope**: a **curated** source draws teams from ONE global,
sport-scoped **directory** (`Team.matchSource = null`, one row per real club per sport, admin-
curated at `/admin/tymy`); a **private** source (from-scratch competition internal) gets **local**
teams scoped to that source, so office-pool names never pollute the directory. Scope is *derived*
from `matchSource === null` (two partial unique indexes enforce per-scope name uniqueness). The
single home of the rule is `TeamResolver`. Organizers never see this plumbing: they just type a
team name (autocompleted; a new name creates the team in the right scope). *Why global rosters
for global teams*: a curated team's scorers/stats are meant to accumulate across every curated
competition that uses it — the exact "same team across matches" the entity exists to provide;
the cost is that a free-typed scorer typo grows the shared roster (admin roster cleanup is a
later phase). Renaming a team is free (FK stable); only **reassigning a match to a different
team** is blocked once scorers/events exist (`SportMatchTeamsLocked`). See
[`features/team-picker.md`](features/team-picker.md).

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
guess reminder (hourly sweep; ONE aggregated digest per USER across all their soutěže,
counting only tips the player can enter RIGHT NOW — window open, deadline < 24 h — with a
per-soutěž breakdown and nearest deadline; see the 2026-07-31 decision), new match added after start,
match evaluated (your points + standing), competition ended (final standing), premium
low-balance / uncovered / downgraded / re-enabled, boost refunded, nový hráč se připojil
do soutěže, kterou spravujete (member_joined — in-app default on / email off, skipped when
the joiner is the owner). Delivery is event-driven via messenger; reminders + premium
reconciliation + snapshots run as host-cron console commands (`app:guess-reminders:send` /
`app:premium:reconcile` / `app:leaderboard:capture-snapshots`), NOT symfony/scheduler.

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
| 2026-07-19 | S12 leaderboard delta = a daily `LeaderboardSnapshot` (competition × user × Prague-calendar day → rank + points), captured 03:00 Europe/Prague by the host-cron `app:leaderboard:capture-snapshots` command and idempotent per (competition, day); the Δ shown on the board is movement vs the latest snapshot day **strictly before** today (a member absent from that baseline shows „nový"); a member breakdown „Vývoj" list reads the same rows | a day is the natural „round" of a tipovačka — per-match deltas are noisy when several matches land the same day; comparing to a fixed prior day keeps the arrow stable through the day |
| 2026-07-26 | Third match-selection mode `teams` (`CompetitionTeamFilter` rows): a competition scoped to specific teams includes every source match where a filter team plays home OR away, DYNAMICALLY (later-added team matches auto-join like `all`, incl. playoff); multi-team = union; offered in private + global create flows (never `subset` for global); editable after creation on the same manage surface as `subset`; each filter team is scope-validated against the source (curated → global directory, private → local) via `TeamResolver::belongsToSourceScope` | first-class `Team` identity makes „a competition for Sparta's matches" possible; dynamic (not a snapshot) is what makes the playoff case work — you filter by a club before its knockout fixtures exist and they join as imported |
| 2026-07-20 | S13 admin consolidation: the admin area **deep-links into the voter-guarded portal** (competition detail, source detail = the matches-management page) rather than keeping duplicate admin views — the only admin-owned surfaces are the cross-cutting lists (sources, competitions, users, credits, rules) + the global-competition create/edit forms; „Kredity → Transakce" is a cross-wallet ledger filterable by transaction type and competition, and the global-competition edit page shows a read-only premium-charges / active-boosts panel; all project docs reconciled to the as-built system | one page per concern with no duplicate controllers to drift; admins see the exact same detail members do, plus the money movements (`entry_fee` / `premium_charge` / `boost_purchase` / refunds) the ledger surfaces |
| 2026-07-20 | Scheduled jobs moved from symfony/scheduler (hidden in the worker) to host crontab entries (lily.srv `apps/wtips/cron.d/wtips`) for ops visibility/monitorability — three console commands `app:premium:reconcile` / `app:guess-reminders:send` / `app:leaderboard:capture-snapshots` invoked by cron (wrapped by `lily-cron-run` + `sentry-cli monitors`); symfony/scheduler + dragonmantank/cron-expression removed | ops need each scheduled job visible/monitorable as a real crontab entry, not hidden inside the messenger worker |
| 2026-07-23 | Manager/admin auto-entitlement to VISIBILITY removed (reverses the 2026-07-19 row): an organizer buys „Lišta tipů ostatních" / „Konkrétní tipy kolegů" like any player, and all three boosts are offered to them; `PurchaseBoostHandler` now rejects only what the buyer already owns or already gets from a premium toggle. Kept revertible via `CompetitionEntitlements::$managersSeeTipsForFree` (config/services.php) | the organizer usually plays in their own competition, so a free look at everyone's tips was an in-game advantage over paying members — and it made the paywall invisible to exactly the person exploring the app |
| 2026-07-23 | On-behalf tipping shows filled/not-filled only: „Tipovat za členy" and the match page's „Tipy členů" render a „Vyplněno"/„Nevyplněno" pill with EMPTY score inputs unless the manager is separately entitled (own row, bought/premium entitlement, or past deadline); blank inputs still skip on save, so a blind manager cannot wipe a tip by accident | managing a member's tip needs to know only that it exists — showing the scores would re-open the advantage the row above closes |
| 2026-07-23 | The distribution bar/paywall is a single component (`Match:TipStats`) fed by a single batch resolver (`TipStatsProvider` + `GetPickDistributions`), rendered on every match-listing surface; locked always renders (dropping the player count when nobody has tipped yet), unlocked renders only with ≥1 tip | it previously existed on one page only, so most users never saw what they could buy; batching keeps a page O(competitions) instead of O(matches × competitions) |
| 2026-07-25 | Teams became a first-class `Team` entity (was free-text strings on `SportMatch` + `Player.teamName`). Hybrid scope: global sport-scoped **directory** for curated sources, **local** teams for private sources (derived from `matchSource === null`; two partial unique indexes). `Player` recoupled to `Team` (global team ⇒ global roster). Match commands still carry team NAMES, resolved via `TeamResolver`; the reassign guard now blocks changing a match to a DIFFERENT team once scorers/events exist (renaming a team is free). Contrast-safe monogram now, logo upload later; admin directory at `/admin/tymy`; team picker (autocomplete + create) on the match form, import badges new teams | logos/stats/"same team across matches" need a real entity; hybrid keeps office-pool names out of the shared directory; no users yet ⇒ clean cut, no backfill |
| 2026-07-25 | Admins can create a global competition **from inside the create-competition wizard** via an admin-only „Typ soutěže" toggle (global mode: entry-fee field, curated-source-only, all matches, „Pozvánky" step skipped, monetization none/premium/boosts, rules kept), branching to the existing `CreateGlobalCompetitionCommand`; the dedicated admin page + curated-source checkbox remain. The mode is `ROLE_ADMIN`-gated at render + action and `isGlobalKind` is non-writable, so non-admins can never reach it | the global feature was fully built (S09) but its create entry point lived only in the admin area and admins looked for it where they create every other competition — the wizard — and expected to set „allowed features" (rules) + entry fee there; the wizard already collects rules + monetization, so folding global in removes the discoverability gap without a second code path |
| 2026-07-29 | **A boost cannot be bought in a competition that is already over** (BUGS.md B6). „Fully over“ is defined on the competition's own match scope (`CompetitionMatchProvider::isFullyOver`): it includes ≥1 match and **none** of them is `Scheduled`/`Live`/`Postponed` — `Finished` (has a result) and `Cancelled` (never will) both count as settled; an empty scope is NOT over. Enforced in `PurchaseBoostHandler` (`CompetitionAlreadyOver`, HTTP 409), mirrored in `Boost:Panel` (CTA replaced by a one-line explanation) | buying a visibility boost for a finished competition burns credits for an entitlement with nothing left to unlock. „Past the last kickoff“ would have been wrong: a kicked-off match whose result is not yet entered can still move the standings, so the competition is still live — settled-ness, not the clock, is the test (the same one `competition_ended` uses) |
| 2026-07-29 | Wizard rules step (W1): **two new `periods` rules** — `period_home_goals` / `period_away_goals` (1 b, disabled by default) — mirror the whole-match per-team goal rules per period; `period_tendency` is **kept**, nothing retired. **PP and PEN are NOT split**: `SportMatch`/`Guess` keep the ONE combined overtime pair, relabelled „Celkové skóre po prodloužení / penaltách" (no columns, no migration, no form change). „Chcete tipovat také střelce utkání?" = `scorer_hit` enablement; „Dohrávat turnaj?" = the ONE existing `includePlayoff` toggle, only reworded. **Fantasy deferred** out of the wizard entirely. Presets: Standardní / Maxi / Vlastní (připravujeme, disabled) | periods deserve the same partial-credit richness as the full match, and adding rules costs no schema (`CompetitionRuleConfiguration` keys on the identifier); every other item was a naming problem, not a domain gap — a second control writing `includePlayoff` or a PP/PEN split would have been new state bought for wording |
| 2026-07-29 | **„Uzamknout tipy" happens now or at a chosen time** (BUGS.md B2). The scheduled variant stores the chosen moment in the SAME `Competition.tipsLockedAt` („locked from") — no flag flip, no cron, no scheduler entry: `EffectiveTipDeadlineResolver` already treats that column as the competition's lock moment, so every match deadline becomes `min(lockAt, kickoff)` the second it is stored and the lock fires by time passing. The moment must be in the future and strictly before the competition start (first kickoff); it is re-schedulable and cancellable (= unlock) until it fires, after which it behaves exactly like a lock made by hand at that instant | option (b), a job that flips a flag at the scheduled minute, buys the same user-visible behaviour with a new moving part that can fail at 03:00 — and a „locked from" timestamp is what the deadline model already speaks. Capping the schedule at the competition start keeps the „a lock moment is only ever reached once" invariant: a later moment would push the lock PAST the automatic one and reopen tips the start had closed |
| 2026-07-31 | **Team logo + country become real admin fields** (both optional, admin-only — no player-facing surface changes). `Team.logo` holds a Flysystem **storage path**, never a URL: uploads go through the `team_logos` storage (`league/flysystem-bundle`, local adapter on the persistent `public/uploads` volume) and are normalised to one shape — transparent WebP fitting inside 256 × 256; `…|team_logo_url` resolves the path at render time. `Team.country` keeps its existing alpha-2 column but stops being free text: it validates against `App\Value\Country` (248 countries + Czech names + alpha-3) and is picked from a searchable `ChoiceType`. Emoji flags retired in favour of one 64px round WebP per country in `assets/flags/`, named by alpha-3. **No migration** — both columns already existed. All existing production teams backfilled to `CZ` | the columns were reserved from day one and the monogram was always the placeholder; going through Flysystem (rather than `fopen`/`unlink`) means a later move to S3/R2 is a config change, and storing the PATH rather than the URL keeps that promise in the data too. Free-text country codes could not be trusted to have a flag — a closed registry can, and the unit test proves it |
| 2026-07-30 | **One name for the tip split: „Rozložení tipů" everywhere**; the boost that unlocks it is „Rozložení tipů ostatních". „Lišta tipů" / „Lišta tipů ostatních" and „Distribuce tipů" are retired as user-facing copy (ui-nav item 12). Czech copy only — `BoostType::TipDistribution`, the `tip_distribution` value, `PricingConfig::BOOST_TIP_DISTRIBUTION`, `TipStats`/`TipStatsProvider` and the `.dist-*` classes are unchanged, so no migration and no behaviour change; the four hard-coded copies of the boost name in `Match:TipStats` + `Boost:Panel` now read `BoostType::label()` | the shipped app had THREE names for one feature — the match page said „Rozložení tipů", the paywall sold „Lišta tipů ostatních" and the homepage advertised „Distribuce tipů" — so the thing a player was asked to buy read as a different feature from the thing it unlocks. The product owner's mocks say „DISTRIBUCE TIPŮ"; the documented vocabulary wins instead |
| 2026-07-30 | **The „Měnit tip" window is PER MATCH**: with the entitlement a tip stays changeable until `tipChangeOffsetMinutes` (default 60) before **that match's own kickoff**, replacing „before the day's first competition match" (2026-07-18 row). One line in `EffectiveTipDeadlineResolver::entitledDeadline` (the Prague-day grouping is gone); no schema change, the offset stays prémium-configurable. Copy follows: the boost panel sells „upravit své tipy až 1 hodinu před začátkem zápasu", `BoostType::description()` and the prémium-settings help texts drop „prvním zápasem dne" | the product owner chose the copy over the documented rule (*„yes the booster allows 1h before each match"*) — waiting for the lineups of the 21:00 match is exactly what the boost is bought for, and the day-first rule closed that window at 13:00 because an unrelated afternoon fixture kicked off first. It is strictly **extending**: the two rules coincide for the day's first match and per-match is later for every subsequent one, so the extend-only `max()` composition (2026-07-19 row) holds and no player loses a window they had |
| 2026-07-30 | **A player of a premium competition is never shown boost prices.** Premium XOR boosts is one column, and on `Premium` the organizer pays for everyone — a player cannot buy a boost at all. So every boost-price surface, including item 19's first-visit modal on competition detail, is suppressed on a premium competition (and on `none`), as well as for a non-member and on a fully-over competition (B6) | showing a price there would advertise something unpurchasable — the product owner's own reasoning: „not to a premium competition because user is unable to buy boosts on premium competition". This is the user-visible consequence of the single `monetization` column, not merely a schema fact |
| 2026-07-30 | **Pending join intent is durable, not session-scoped.** A visitor reaches a soutěž three ways — **e-mail invitation**, **shareable link**, **PIN**. Only the e-mail invitation proves the visitor owns the mailbox it was addressed to, so only it verifies the account on acceptance; a link and a PIN prove nothing about identity, so the intended join is *recorded* (`User.pendingJoinKind` / `pendingJoinToken`) and honoured at the first login the account is allowed to join on, i.e. after verification. All three name the soutěž **before any account exists** and all three end on the competition detail. The PIN landing (`/pripojit`) becomes **public**: reachable means *typeable*, never *joinable* — an unverified account may read all three landings and join through none | a browser-session cookie cannot survive an out-of-band mail hop that may arrive on another device, so the promise the landing page had already made was silently lost; that, plus a post-verification redirect to `/nastenka` instead of the soutěž, is why an invited sign-up ended on „Zatím nehraješ v žádné soutěži" (B15). Making the PIN public widens nothing — a PIN was always a secret the organizer hands out |
| 2026-07-30 | **Other players' tips are revealed by the RESULT, not by the deadline — on every surface** (ui-nav item 20). The rule is `entitled OR the match has a final result` (`Finished`), where „entitled" is the premium toggle or the viewer's own boost (`CompetitionEntitlements::isEntitledToOthersTips` / `…ToDistribution`); it governs the concrete tips **and** the anonymous 1 / X / 2 distribution alike, so an aggregate can never open before the tips it aggregates. Composed in ONE place (`TipVisibilityGate`, consumed by `TipStatsProvider`, the matrix query, `GetGuessesForMatchInCompetition`, match detail and on-behalf tipping); no surface compares `now` against a deadline any more. Explicitly revisitable through one knob, `TipVisibilityGate::$freeRevealRequiresResult` (wired in `config/services.php`, same pattern as `$managersSeeTipsForFree`), with a test pinning the flipped behaviour. Consequences: it REVERSES item 10's unlocked „Pořadí za zápas" on a live match (the section renders, the tips are behind the CTA), and `/zebricek/matice` becomes member-reachable with per-match gating instead of an all-or-nothing refusal (`leaderboard_details` NOT widened; anonymous still refused) | „past the deadline" trusted the SCHEDULE where the product needs a FACT. If a kickoff passes and nobody plays the match — an organizer late to postpone — the old rule published every tip for a match still to come; and because a late-added match's deadline is its own kickoff, postpone-then-reschedule could reopen tipping *after* that reveal, making tips copyable by accident of admin timing. A match without a score cannot leak, whatever its schedule says, so the correctness argument and the product owner's „consistency across the platform" point the same way. It also raises rather than lowers the boost's value: the entitlement shows the tips any time, only the FREE reveal moves later |
| 2026-07-30 | **One canonical name, sentence and price per booster — everywhere** (ui-nav item 23). `BoostType::label()` now returns „Jak tipují ostatní?" / „Přesné tipy soupeřů" / „Počkejte si na sestavy" and `::description()` the three matching sentences; the „marketing headline above the canonical name" split that ROUND2 decision 2 introduced is ABOLISHED, so the shop row, confirm dialog, paywall, intro modal, wizard, prémium toggles, /cenik, both credit ledgers and the refund notification all read the same six strings. The SURFACE the first booster unlocks is renamed with it: the „Rozložení tipů" heading (item 12, the row above) becomes „Jak tipují ostatní?" on match detail and in the compact strip inside every match card — „Rozložení tipů" survives only as the lowercase descriptive phrase inside the sentences. Prices re-set: `BOOST_TIP_DISTRIBUTION` 10→**15**, `BOOST_OTHERS_TIPS` 20→**35**, `BOOST_TIP_CHANGE` 40→**50** (`PREMIUM_PER_PLAYER` unchanged at 10). Enum values, `PricingConfig` constant names, `.dist-*`/`.tip-stats-*` classes and `TipStats`/`TipStatsProvider` are untouched, so **no migration** and no behaviour change | the product owner wrote the copy they want players to read and there is no reason for the app to own a second vocabulary: a booster sold as one name and charged as another reads as two products (exactly the failure item 12 fixed, re-introduced one layer up by the headline map). Naming the unlocked surface after the booster closes the last gap — a player who buys „Jak tipují ostatní?" now finds a section with that name |
| 2026-07-30 | **A match may declare when tipping OPENS, not only when it closes.** New optional `CompetitionMatchSetting.opensAt` + `openingNote` (deadline becomes nullable — an opening-only row is legal); `EffectiveTipDeadlineResolver` returns a whole `TipWindow` (waiting / open / closed, closed winning) and every guess-writing handler goes through ONE thrown gate, `assertOpenForTipping`, so a forged POST is refused exactly like a hidden input. Setting it is **ROLE_ADMIN only** (the deadline stays the manager's): the form does not even BUILD the fields for a non-admin and the handler re-checks the role, while a manager's save carries `changeOpening: false` and leaves the stored opening untouched. It blocks **everyone** — the organizer's on-behalf tipping included — and no entitlement opens it early. A waiting match KEEPS its row on every listing surface (nástěnka, /zapasy, competition detail, „Moje tipy", „Tipy členů") with a clock, the opening moment and the note instead of inputs; „chybí natipovat" counters, the hourly reminder and „Netipováno" all skip it | the organizer needs „tips open after the draw / after the lineups", and the only tool available was a deadline, which says the opposite. Gating in the handlers (not the voter alone) is what makes it a rule rather than a UI convention — the product owner asked for exactly that. Keeping the match VISIBLE but inert is the honest state: removing it from the list is indistinguishable from „this soutěž does not include it", and a grey „Uzamčeno" would tell a player they missed something that has not happened yet |
| 2026-07-30 | **„Počkejte si na sestavy" buys BOTH ends of the tip window** — it lifts a „tipování otevřeno od" as well as extending the uzávěrka, so its owner tips a waiting zápas immediately and keeps tipping to the deadline. This REVERSES the „the opening is not per viewer" clause written earlier the same day: `EffectiveTipDeadlineResolver::openingFor` drops the opening for a viewer with `canChangeTips`, while the USERLESS window keeps it (so `TipVisibilityGate` and every „what may I see" surface are untouched). `TipChangeUnlock` now offers the booster whenever buying would lift an opening, even when the deadline itself would not move — otherwise the paywall would be invisible on exactly the cards where the boost is worth most, since a per-match deadline pinned to the kickoff leaves nothing to extend. Copy: the waiting card asks „Už to nevydržíte a nechcete čekat?" above the existing gold „Odemknout za X kr." control | the product owner asked for the booster to sell against the wait, not only against the uzávěrka („so this booster not only allows exceeding the deadline but as well the start limit"). It also fixes an economic hole the opening had just created: with every deadline pinned to its own kickoff (the round-by-round season), the boost's „kickoff minus offset" term extends nothing, so `tip_change` had become unsellable on precisely the matches players most want early access to |
| 2026-07-30 | **The locked overlays lose their invented teasers — the booster's own name and sentence sell it there too** (extends ui-nav item 23, and REVERSES that item's assumption 8, which left the teasers alone as „varying per monetization"). The veiled „Jak tipují ostatní?" strip stops counting players („Uvidíš, jak tipují 4 hráči") and renders `BoostType::description()`; the veiled full card drops „Uvidíš, jak tipuje konkurence" and its three hand-written „Detailní rozpad tipů…" branches; the veiled „Pořadí za zápas" card on match detail drops „Uvidíš konkrétní tipy kolegů" and its three branches and now NAMES the booster it sells (`BoostType::OthersTips->label()`), which it never did. The part that genuinely varies is not per-booster but per-LOCK-REASON, so it became six constants on `BoostType` (`LOCKED_PREMIUM_*`, `LOCKED_OVER_*`, `LOCKED_AFTER_MATCH_*`) that the same three surfaces plus `Boost/Panel` now share. `/_design`'s price tiles read the enum instead of retyping it. One CSS change: `.tip-stats-note .tip-stats-teaser` was `white-space: nowrap` + ellipsis, which clipped the canonical sentence to 80 % at a 700 px card and to 23 % at 288 px — it wraps now (`border-radius` 999 → 14 px, since a stadium cannot hold four lines). No enum values, no prices, no migration | assumption 8's reasoning does not survive contact with the strings it protected: the overlay teasers varied by MONETIZATION, not by booster, so the varying half was always a candidate for its own constants and the invariant half was the booster's own sentence, already written. Keeping them meant a player met four different descriptions of one product — the veiled strip, the veiled card, the shop row and the confirm dialog — which is the exact failure item 23 was filed to end, one layer further down. The player count made it worse than generic prose: it read as a headline for data the card does not show, and the same count already renders in the UNLOCKED strip, where it is information rather than bait |
| 2026-07-30 | **Withdrawn the same day: „Počkejte si na sestavy" does NOT unlock early guesses.** The row above (the booster buying both ends of the window) shipped and was reverted within the hour — „no unlocking allowed for early guesses at this point, it might be added later". So the opening is once again identical for every member: `EffectiveTipDeadlineResolver` reads it straight off the override row, `TipChangeUnlock` offers the booster only where it moves the UZÁVĚRKA, and the waiting branch of the tip form carries no paywall. Both rules are pinned by tests (`EffectiveTipDeadlineResolverTest::testEntitlementDoesNotOpenTheWindowEarly`, `TipChangeBoostOpeningTest`) so restoring the feature is a deliberate act, not an accident | the product owner reversed the product call, not the engineering. Keeping the withdrawn behaviour pinned by tests is what makes the reversal cheap in both directions: the „add it later" path is three named edits, and until then nothing can drift back in unnoticed |
| 2026-07-30 | **The PIN and the shareable link belong to every MEMBER, not just the organizer.** `CompetitionVoter::SHARE_JOIN_LINK` (`competition_share_join_link`) grants any active member — same preconditions as `MANAGE_JOIN_MECHANICS`, i.e. never a deleted, finished or global soutěž — the right to SEE and pass on both codes, and `GetCompetitionDetailQuery` stops redacting them to owner/admin. The competition detail renders them in one „Pozvat kamaráda" card with copy buttons. CONTROL stays with the organizer: whether a PIN and a link exist at all, regenerating or revoking either, and e-mail invitations (which invite in the soutěž's name) remain `MANAGE_JOIN_MECHANICS` on the settings page | a partička grows because the players pull their friends in, and a code only the organizer can read makes them the bottleneck for a thing that is not a privilege — the PIN was always „a secret the organizer hands out" (see the 2026-07-30 pending-join row), so handing it to the people who are already inside widens nothing the link did not already widen |
| 2026-07-30 | **„Chybí kredity" is abolished — an unaffordable purchase keeps the SAME CTA at the SAME price and only changes destination.** Every boost surface (`Match:TipStats` strip + full card, `Boost:Panel` in all three shapes, the „Získej výhody" shop row) now renders one control per booster: with enough credits it opens the confirm dialog, without them it links to `/kredity#dobit`. No balance-vs-price label, no separate „Dokoupit kredity" button | the old label answered a question nobody asked („how short am I?") and made the same product look like two different offers depending on the wallet; the offer is the offer, and the wallet only decides which step comes first |
| 2026-07-31 | **A verification link never dead-ends a legitimate visitor** (Sentry TIPOVACKA-K): (a) an already-verified account short-circuits `/overit-email` to „e-mail už je ověřen → přihlaste se" BEFORE any signature check, however broken the URL, and never with a session; (b) when the full-URI signature fails but the mail's `token` param — a self-contained HMAC of userId+email, with the `+`-arrived-as-space base64 mangle repaired — matches, the click verifies anyway, and expiry is forgiven in this fallback while the account is unverified. Links stay multi-use and 7-day-signed on the normal path; the genuinely unrecoverable case (token itself damaged) keeps the recovery page with „Vyžádat nový" | the token is the same proof the signed URL encodes — only the mailbox owner ever holds it — while the URI signature protects exactly the bytes that link-rewriting intermediaries (mail-scanner „safe links", the Seznam app/crawler pipeline) legitimately alter. TIPOVACKA-K showed a real user (Seznam app, 4 clicks incl. a 15s-fresh resent link) bounced to an error page by `UriSigner` over bytes they never touched; token possession must beat byte equality, and an already-verified visitor has nothing left to prove at all |
| 2026-07-31 | **The guess reminder is ONE digest per USER, and a „missing tip" is only one the player can enter RIGHT NOW.** The hourly sweep aggregates across all the player's soutěže into a single notification/e-mail: per-soutěž line with its own count and NEAREST deadline; a single-soutěž digest keeps the old title and links the competition detail, a multi-soutěž one links `/zapasy`. Counted matches must have their tip window OPEN at sweep time (not waiting for an admin `opensAt`, deadline not passed) and closing within 24 h — a locked or not-yet-open match is never counted. Idempotency keeps the pre-digest granularity: every (soutěž, Prague deadline-day) bucket is stamped once — the digest row carries the first new bucket key, the rest become invisible marker rows (`Notifier` `additionalDedupKeys`), so a NEW bucket fires a fresh complete digest while a match added later on an already-reminded day rides the existing stamp | a player in ten soutěže sharing one zdroj zápasů got ten near-identical e-mails per deadline-day (product owner feedback, 2026-07-31) — the unit the player cares about is „what do I need to tip", not „which soutěž noticed"; and an e-mail advertising 18 chybějících tipů of which several were already locked or not yet open taught players the number lies. Bucket-level stamps (not one per-user daily gate) are what lets a later-entering soutěž/day still remind without ever re-nagging an already-covered one |
| 2026-08-05 | **The single-source scope columns are retired.** `competitions.selection_mode` / `include_playoff` dropped (the mode is per-zdroj and lives on the layer); `match_source_id` renamed to `headline_source_id` and `Competition::$matchSource` to `$headlineSource`, repointed automatically when layer 0 changes. The drop migration asserts every competition agrees with its layer 0 before touching anything, and its `down()` restores the values from the layers rather than from defaults | carrying both shapes was BC scaffolding, and the product owner confirmed BC breaks are fine when the data migrates (2026-08-05). Dual truth that can drift is worse than a rename: `detachSource` did not repoint the old `matchSource`, so dropping layer 0 left a soutěž advertising — and authorising against — a zdroj it no longer drew from. The name mattered too: `Competition::$matchSource` alongside `$sources` invites exactly the scope/zdroj confusion DOMAIN.md opens by warning about |
| 2026-08-05 | **A soutěž draws from MANY zdroje: match scope becomes a set of `CompetitionSource` layers** (zdroj + that zdroj's own `selectionMode`/`includePlayoff`), whose union is the scope. Selections and team filters hang off a layer; team-filter uniqueness moves from (competition, team) to (layer, team). `Competition.matchSource` stays as the HEADLINE zdroj (display/sport/authorization); `selectionMode`+`includePlayoff` stay on the competition only as a legacy mirror of layer 0 until every reader is moved. „Schedule complete" becomes an AND over the layers. One sport per soutěž (`CompetitionSourcesSportMismatch`); duplicate fixtures across zdroje are WARNED about on a team-name + ±24 h heuristic, never dropped. The wizard's scope step becomes a basket; the manage screen edits one layer at a time (`?vrstva=`) | users asked to combine hand-picked matches from several existing zdroje plus their own — impossible while the scope was one FK and one mode. Layers put the mode where it was always semantically owed (per zdroj: „all of Chance Ligy, only Sparta from LM, three of my own"). The sport lock is not taste: rules are configured once per soutěž in that sport's vocabulary, so a mixed scope has no coherent ruleset. Duplicates only warn because there is no cross-source fixture identity to dedupe ON — `externalId` is per-zdroj and the same club is a different `Team` row in a curated vs a private zdroj — and a cup replay is a real pair |
| 2026-07-31 | **Match data feeds: two-tier provider strategy behind one `MatchDataProvider` interface** (full research, source landscape, legal verdicts and Phase-0 prep in [`.docs/MATCH_DATA_FEEDS.md`](MATCH_DATA_FEEDS.md)). Tier CZ = FAČR (existing scraper in another project, announced API later; IS FAČR is the system of record even for Chance Liga — `2026001A1A`); Tier world (UCL/UEL) = a granted free tier of a commercial API (first candidate SoccersAPI free, fallback API-Football $19/mo — which also covers ALL tiers if a single vendor is ever preferred). **Never scrape Flashscore/Livesport, SofaScore, FotMob, ESPN or chanceliga.cz** — verified legal exposure, incl. Czech-law database-right clauses of a Prague rightsholder. Prerequisites before any download: `SportMatch.externalId` + per-source unique index, `MatchSource.feedProvider/feedRef`, `TeamAlias` with pending-team gate, player-name normalization, `MatchEventWriter` merge mode, AET/pens decision | manual result entry doesn't scale past a handful of zdrojů zápasů; the Czech leg is the part no affordable vendor does well while FAČR does it authoritatively for free, and the UEFA leg is commodity data with real free options — so the split follows the market's own fault line. One thin interface keeps „one vendor for everything" a per-source config change, not a redesign. The scraping bans are risk decisions, not taste: a monetized Czech product scraping a Czech rightsholder is the *Football Dataco* fact pattern in the defendant's home court |
| 2026-08-05 | **The match scope is not a create-time decision: the organizer of a PRIVATE soutěž edits the whole basket at any time**, at `/souteze/{id}/zapasy`, in the very form they created it with — the wizard's step 1 was extracted into the `ComposesMatchScope` trait plus two shared partials and is now rendered by both surfaces. Saving dispatches `UpdateCompetitionScopeCommand`, which reconciles the requested `CompetitionSourceSpec` list against the existing layers: a new zdroj ⇒ a new layer anchored `addedAt = now` (its matches get their own deadlines), a kept zdroj ⇒ `changeScope()` with its ORIGINAL anchor, a dropped zdroj ⇒ layer + rows removed while the **zdroj itself survives**. Per-layer rows move into `ScopeLayerWriter`, shared with the two narrow per-layer screens. A match already in scope is never re-anchored as „pozdě přidaný" when its layer changes mode (`establishedMatchIds`, snapshotted before the edit). **Global soutěže are refused** (`CompetitionIsGlobal::scopeIsAdminOnly()`) and keep the per-layer screen. In-place editing of the MATCHES themselves is offered only for a zdroj that `OwnMatchesSource` certifies as the soutěž's own — private, organizer-owned and drawn from by no other soutěž; when a private zdroj IS shared, the affordance disappears and it is edited from the zdroj's own page | a partička's rozpis changes after the soutěž starts — a playoff is added, a zdroj turns out to be the wrong one, someone wants their own zápasy alongside a curated liga — and until now the only editable thing was a single existing layer's picks. Reusing the wizard's form (rather than writing a second one) is what keeps the sport lock, the already-basketed rule and the tom-select island contract from drifting into two dialects. The exclusivity test is the safety rule the product owner asked for in one line: **editing a soutěž must never change what another soutěž tips.** It stays derived rather than enforced in the schema because reusing your own private zdroj across two partičky is legitimate — so the app narrows the affordance instead of forbidding the data shape |
| 2026-08-06 | **Inviting is a PLAYER's action, not an organizer privilege — and it works in a global soutěž too.** `competition_invite_member` is granted to every active member while the competition `isOpenToInvites` (a global one always is; a private one exactly while a PIN or a shareable link exists), so a member may send the e-mail invitation, not just pass the PIN on. `competition_share_join_link` lost its blanket `!isGlobal`. The organizer keeps the switch (`MANAGE_JOIN_MECHANICS`: whether a PIN/link exists at all) and the bulk paste-an-address-book form. A global soutěž is invited into through a new public landing `/souteze/{id}/pozvanka` (`InvitationKind::GlobalCompetition`, whose „token" is the competition's own id — no secret), which ends in the ordinary entry-fee join; its e-mail invitation writes nothing and only mails that link | reported as a bug: the organizer of a global soutěž had NO way to invite anybody, on the detail page or in Nastavení, because all three invitation permissions carried `!isGlobal`. The deeper rule was already written down for `SHARE_JOIN_LINK` — „a partička grows because the players invite their friends, not because the organizer is the only one holding the code" — and stopping one step short of the e-mail was arbitrary. Bounding a member's invitation by `isOpenToInvites` keeps the one control the organizer actually uses: revoking both mechanics still closes the doors |
| 2026-08-09 | **A score on screen always means the FINAL score — the feed never publishes a running one.** `FeedSynchronizer::applyLive` marks the zápas live and writes nothing else: the provider's in-play score is read and deliberately dropped, so `homeScore`/`awayScore` stay null until the feed reports the match finished, and every subsequent poll of a match in progress is a genuine no-op. Manual live entry (`UpdateLiveScoreCommand`, the admin action) is untouched — a human who types 1:0 at half time means it. Consequence for the poll cadence: `FeedPollPolicy`'s live trigger is now bounded by kickoff recency (`hasLiveMatchKickedOffSince`), because a fixture the provider abandons mid-game keeps the `Live` state for ever and an unbounded check would hold its zdroj at 288 fetches a day | reported by the product owner the day after the feed went live: „the matches should not update score until they are fully finished, this causes confusion for the users whether the match score is final or not". The „Live" pilulka and the number it qualifies are different elements, and a glance reads the number — so a half-time 1:0 is indistinguishable from the 1:0 a player is being scored against. The reveal rules already made results the load-bearing fact (2026-07-30: other players' tips open on the RESULT, not the deadline), so a provisional score was the one place the app still published a result-shaped thing that was not one |
