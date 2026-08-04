# Match data feeds — plan & implementation preparation

Status: **research verified 2026-07-31** (live probes of every claim — no vendor marketing
taken at face value), direction decided, implementation prep (Phase 0) specified below.
Owner rationale: demand for Chance Liga, Evropská liga and Liga mistrů soutěže; manual
result entry does not scale with the number of zdrojů zápasů.

## TL;DR decision

**Two-tier provider strategy behind ONE thin `MatchDataProvider` interface:**

- **Tier CZ (Chance Liga, MSFL, anything FAČR-filed):** FAČR — the existing scraper in
  Honza's other project now, the announced FAČR API when it ships. IS FAČR is the legal
  system of record even for the top flight (Chance Liga = soutěž `2026001A1A`, MSFL =
  `2026003A1A`; the zápis o utkání carries score, goals, cards, lineups and **stable
  player IDs**). Post-match only — no live in-play — which fully covers scoring needs.
- **Tier world (UCL, UEL):** a granted free tier of a commercial API — first candidate
  **SoccersAPI free plan** (3 league slots; UCL id 539 + UEL id 541; attribution
  required, publication of data with attribution is explicitly contemplated by their
  ToS). Fallback if the free plan doesn't serve the current season: **API-Football Pro,
  $19/mo** (no league gating; Czech Liga + MSFL id 349 included too, which also makes it
  the single-vendor fallback if the FAČR tier is delayed).
- Optional free cross-check for UEFA fixtures: UEFA's own open API
  (`match.uefa.com/v5`, competitions 1=UCL / 14=UEL) — freshest fixtures anywhere,
  scorers with minute+second, but **unlicensed** (use, if at all, for fixtures only:
  per CJEU *Fixtures Marketing*, fixture lists carry no sui generis protection).

**Never scrape:** Flashscore/Livesport (Czech rightsholder, explicit database-right
clauses čl. 2.9/2.10, Czech courts; Soccerway/BeSoccer = same entity), SofaScore (no
license path exists at all), FotMob (active takedowns Jan 2026), ESPN (Disney ToU bars
automated + commercial use). chanceliga.cz carries an all-rights-reserved clause and
declares its stats „pouze pro interní účely LFA" — do not build on it either.

Competitive note: LFA launched its own official **Fantasy Chance Ligy** for 2026/27
(2026-07-08, with eSports.cz) — the league monetizes game rights in-house.

## What a feed must supply (and what it may skip)

Scoring consumes only (see `src/Rule/`, `Service/Scoring/MatchContext`):

| Data | Needed for | Notes |
|---|---|---|
| final home/away score | 4 base rules | mandatory |
| per-period scores | 4 period rules (off by default) | all-or-nothing: exactly `sport.periodCount` periods summing to the final score |
| overtime score | `overtime_exact` | see open decision on AET/pens below |
| goal scorers (player names) | `scorer_hit` | resolved by name → `Player` per team |
| kickoff (UTC), status | deadlines, postpone/reschedule flow | `SportMatchState`: scheduled/live/finished/postponed/cancelled |

Cards, assists, minutes, venue, round are display-only (`MatchEvent` timeline) — nice to
have, never load-bearing. **A results-only feed already automates 100 % of scoring.**

The write path is the existing command surface — a feed is just another caller:
fixtures via `BulkImportSportMatchesCommand` rows, date moves via
`postponeTo()`/`reschedule()`, results via `SetSportMatchFinalScoreCommand` (which
triggers evaluation, notifications and deadline pinning automatically). Feeds target
**curated** sources only; `editorId` can be any admin/system user (no handler reads it).

## Verified source landscape (2026-07-31)

| Source | Chance Liga | MSFL | UCL/UEL | Live events + scorers | Postponed status | Cost | Verdict |
|---|---|---|---|---|---|---|---|
| FAČR IS (is.fotbal.cz) | ✅ `2026001A1A` | ✅ `2026003A1A` | ❌ | post-match zápis (goals, cards, player IDs) | status column (`nezahájen`, …) | free | **Tier CZ** |
| SoccersAPI | ✅ 1580 | ✅ 1585 | ✅ 539/541 | advertised, depth unverified | best enum (4/5/6/10 = Postponed/Cancelled/Abandoned/Delayed) | free (3 leagues) / €20–30 | **Tier world candidate** |
| API-Football | ✅ "Czech Liga" | ✅ 349 | ✅ | ✅ 15 s, all plans | `PST`→`NS`, stable fixture IDs | free (season limit?) / $19 | **fallback, single-vendor option** |
| Sportmonks | ✅ #262 | ✅ #1157 | ✅ #2/#5 | ✅ 10 s, scorer denormalized | 26 states, `POSTPONED`→`NS` | €29 (5 slots) | strong paid alternative (ToS explicitly allows storage) |
| TheSportsDB | ✅ 4631 | ✅ 5880 | ✅ | inconsistent for CZ | undocumented strings | $9 | weak status model |
| UEFA open API | — | — | ✅ 1/14 | scorers, no cards | first-class status | free | fixtures cross-check only (unlicensed) |
| football-data.org | ❌ absent at every tier | ❌ | UCL free / UEL €49 | paid | best enum | €0–49 | UCL-only supplement |
| BetsAPI | ✅ | ? | ✅ | **CZ events lack player names** | rich codes | $30 | fails scorer need |
| Goalserve | ✅ | "3. Ligy" | ✅ | ✅ + websockets | ✅ | ~$300 | overkill |

## Phase 0 — implementation preparation (no downloading yet)

> **Status: IMPLEMENTED 2026-07-31** (everything except real provider adapters — no
> network code exists). Deliberate divergences from the spec below:
> feed **cancellations are report-only in v1** (never auto-applied — our cancel voids
> guesses and has no un-cancel, so the "seen twice" rule was dropped in favor of a
> plain human confirmation; the sync prints + logs a warning), and **P0.9 became a
> pinning test only** (`SportMatchLockPinningTest::testReschedulingPostponedOpenerKeepsThePin`
> — reschedule keeps the pin, extend-only semantics made explicit rather than rewired).
> The P0.2 admin-form fields were deferred; binding runs through
> `app:matches:bind-feed <source-uuid> <provider> <ref>` (provider `none` unbinds).
>
> Ops surface: `app:matches:sync [--source=] [--dry-run]` (host-cron entry point;
> exit 1 when any source reports unresolved teams / unknown statuses / errors, so the
> cron monitor fires), `app:team-alias:add <team> <alias> [--sport=]`,
> `app:matches:bind-feed`. A source whose provider has no adapter yet is skipped with
> a warning — configuring `facr`/`soccersapi`/`api_football` before their adapter
> ships is safe. The `fixture` provider (feedRef = JSON path) exercises the whole
> pipeline end-to-end; integration coverage lives in
> `tests/Integration/Service/Feed/FeedSynchronizerTest`.

Everything below is valuable and testable **before any network call exists**, using a
fixture-file provider. Ordered by dependency.

### P0.1 — `externalId` on `SportMatch` (the idempotency anchor)

Nullable `externalId` (string, provider-scoped opaque ref: FAČR zápas GUID, vendor
fixture id) + partial unique index per source:
`#[ORM\UniqueConstraint(columns: ['match_source_id','external_id'], options: ['where' => '(external_id IS NOT NULL AND deleted_at IS NULL)'])]`.
Today the only key is the PK and the natural key `(source, teams, kickoffAt)` is
unstable — kickoff is exactly what postponement moves — so re-polling would silently
duplicate fixtures. Manual matches keep `null`. Migration via `doctrine:migrations:diff`.

### P0.2 — Feed binding on `MatchSource`

Two nullable columns: `feedProvider` (enum `FeedProvider: facr | soccersapi |
api_football | …`) + `feedRef` (provider's competition ref: `2026001A1A`, `539`, …).
`null` = manual source (default, all existing rows). This is the whole "which tier"
switch — the two-tier vs single-vendor question becomes per-source config, not
architecture. Admin UI: two fields on the existing curated-source form, ROLE_ADMIN only.

### P0.3 — `TeamAlias` (mandatory before any import)

`TeamResolver::resolve()` matches case-insensitive exact name only; a feed's spelling
(„Zbrojovka Brno B" vs „FC Zbrojovka Brno B", „Sparta Praha" vs „AC Sparta Praha")
silently **creates a duplicate global team** and splits matches across identities —
`CompetitionTeamFilter` soutěže then quietly drop rows. New entity `TeamAlias`
(`team` FK, `alias` string, unique per sport scope), consulted by `resolve()`/
`findExisting()` after the exact-name miss and **before** the create fallback. Feed
sync must run in a "new team requires confirmation" mode: an unknown name creates a
**pending alias task**, not a directory row (reuses the „nový tým" badge idea from
`SportMatchImporter::isNewTeamName()`). Seeding: console command mapping feed names →
directory teams once per source. B-teams make this non-optional (MSFL is full of them).

### P0.4 — Player-name normalization for `scorer_hit`

Same failure mode one level down: `PlayerRepository::findOrCreate` is exact-name per
team, and a mismatch between the feed's scorer string and what tippers picked silently
scores zero. Minimum: normalized comparison (trim, case, diacritics, „J. Novák" ↔
„Jan Novák" prefix rule) before create; FAČR player IDs become a `Player.externalId`
later (Tier CZ bonus). Keep creates non-destructive — a new spelling creates a player
only when no normalized match exists.

### P0.5 — `MatchEventWriter` merge mode

`replace()` deletes all events and re-inserts on every score save — a scorer-less feed
update (or a feed that only knows the score) would **wipe manually entered scorers**
and silently zero `scorer_hit`. Add an explicit mode: full replace (current admin-form
semantics, events are authoritative) vs score-only update (events untouched). The feed
adapter declares which one it is per call.

### P0.6 — OPEN DECISION: AET / penalty-shootout mapping

`setFinalScore()` requires overtime to be a non-draw ≥ the regular score, so a feed's
„1:1 AET, 4:3 on pens" has no faithful representation (`overtimeHome/Away` means
„score AFTER prolongation"). Options: (a) map AET+pens result into the existing
overtime fields by convention (e.g. pens winner = +1 goal), (b) add explicit
`penaltyHome/Away` columns, (c) Tier-world knockout matches only — decide before the
UCL/UEL knockout phase (spring 2027), NOT needed for the league phases or Chance Liga.
Record the choice in DOMAIN.md when made.

### P0.7 — `MatchDataProvider` interface + snapshot DTOs

```php
interface MatchDataProvider   // src/Service/Feed/
{
    /** @return list<MatchSnapshot> all matches of the bound competition */
    public function fetchMatches(MatchSource $source): array;
}

final readonly class MatchSnapshot
{
    public function __construct(
        public string $externalId,
        public string $homeTeamName,      // raw feed name — TeamAlias resolves it
        public string $awayTeamName,
        public \DateTimeImmutable $kickoffUtc,
        public FeedMatchStatus $status,   // OUR enum, provider maps into it
        public ?int $homeScore,
        public ?int $awayScore,
        /** @var list<array{int,int}>|null */
        public ?array $periodScores,
        /** @var list<FeedMatchEvent>|null null = provider knows nothing about events */
        public ?array $events,
        public ?string $round = null,
        public ?string $venue = null,
    ) {}
}
```

`FeedMatchStatus` (scheduled | live | finished | postponed | cancelled | unknown) is
the neutral vocabulary; each adapter owns its mapping table (API-Football `PST`→
postponed, SoccersAPI status 4→postponed, FAČR text →…) with `unknown` logged loudly,
never guessed. `events: null` vs `events: []` is the P0.5 distinction (don't-know vs
knows-there-are-none).

### P0.8 — Sync pipeline + `app:matches:sync` skeleton, tested with fixtures

A `FeedSynchronizer` service that diffs snapshots against DB state per source and
dispatches the existing commands:

- unseen `externalId` + scheduled → create (through TeamAlias, pending-team gate)
- kickoff moved → `postponeTo()` / `reschedule()` (never bare `updateDetails` — a
  kickoff edit alone does not flip state or close tipping)
- status finished + score → `SetSportMatchFinalScoreCommand` (idempotent: a repeated
  identical result is a no-op; a changed one re-evaluates via `SportMatchScoreUpdated`)
- status cancelled → `CancelSportMatchCommand` — **guarded**: cancellation is terminal
  in our model (no un-cancel exists), so require the status twice in a row (two sync
  runs) before acting
- console command `app:matches:sync [--source=] [--dry-run]` in `src/Console/`,
  host-cron like the other three jobs (name stays stable for cron.d), Sentry-monitored

**The whole pipeline ships and is integration-tested with a `FixtureFileProvider`**
(JSON files in `tests/`) — create, postpone, reschedule, result, correction, cancel,
alias-miss — zero network, zero vendor account. This is the „tech improvements without
actual downloading" deliverable: when a real adapter lands, it is ~one class + one
mapping table.

### P0.9 — Close the `reschedule()` gap (pre-existing)

`postponeTo()` pins `Competition.tipsLockedAt` via `SportMatchPostponedHandler` when
the lock-defining match leaves, but `reschedule()` records only `SportMatchUpdated`,
which has **no handler** — the pin is never revisited. Harmless while reschedules are
rare manual acts; a feed makes them routine. Decide + implement the unpin/keep rule
(extend-only semantics say: keep the pin — tips never reopen — but make it explicit
with a test).

## Vendor verification checklist (first real match rounds)

Free, ~30 min each, settles every remaining unknown — ideally during a round where all
tiers play (UCL/UEL qualifiers run Aug 5–11, Sparta–Lyon 2026-08-11; Chance Liga +
MSFL rounds every weekend):

1. SoccersAPI free plan: register, bind UCL 539 + UEL 541 (+ MSFL 1585 as the third
   free slot) → does the free plan serve the **current season**? Does
   `?t=match_events` carry player names for a live match? How does a postponed match
   present (status 4 + does `starting_at` update)?
2. API-Football free: register → does `GET /fixtures?league=…&season=2026` return the
   current season on the free plan? (The one fact deciding whether $0 single-vendor is
   viable.)
3. FAČR: measure zápis latency after a Chance Liga + an MSFL final whistle (minutes or
   hours?) and how an odložený zápas appears in the fixture list.
4. When the FAČR API materializes: license terms re LFA competitions (the Opta media
   deal may carve them out), auth, rate limits.

## Open questions

- FAČR API: timeline, terms, whether 1./2. liga are included (P0 items don't block on this).
- AET/pens representation (P0.6) — needed before UCL/UEL knockouts, spring 2027.
- Attribution placement for SoccersAPI (footer of match surfaces?) if it becomes Tier world.
- Live in-play for CZ (FAČR tier is post-match only): acceptable for v1; if live UX is
  ever wanted for Chance Liga, that's a separate vendor decision — do not scrape for it.
