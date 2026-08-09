# Match data feeds — plan & implementation preparation

Status: **research verified 2026-07-31**, **Phase 0 implemented**, **Phase 1 designed
2026-08-08** (see [Phase 1](#phase-1--the-three-real-adapters-designed-2026-08-08) at the
bottom — it supersedes several assumptions in the TL;DR below, most importantly that one
vendor could cover everything).
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

---

# Phase 1 — the three real adapters (IMPLEMENTED 2026-08-08)

Everything in this part was **probed live on 2026-08-08**, not inferred. Where a claim
came from the 2026-07-31 sweep and was not re-checked, it says so.

> **Status: LIVE on wtips.cz since 2026-08-08.** All eight curated zdroje are bound,
> bridged and polling unattended; results now arrive without anyone typing them.
> See §Rollout log for what production changed about the plan.
>
> **Status: shipped.** `FacrMatchDataProvider`, `UefaMatchDataProvider` and
> `SportmonksMatchDataProvider` all run against their real sources; the poll policy, the
> past-kickoff guard, the deadline re-pin, the evaluation-mail digest and both cron entries
> are in. What remains is **operational**, not code: pair the team aliases per source, run
> `app:matches:adopt-external-ids` where the ids differ, then bind the sources one at a
> time (§Rollout below). Decisions taken with Honza on the day:
> **(a)** no back-fill — a feed never creates a match that has already kicked off;
> **(b)** an own-kickoff deadline pin follows the kickoff the cron corrects;
> **(c)** `match_evaluated` is in-app per match but ONE digest mail per user, like the
> guess reminder; **(d)** UEFA's free API serves UCL/UEL/UECL — no extra Sportmonks slots;
> **(e)** unpaired teams log at warning, exit 1 is for real failures only.

## What changed since the plan above

The Sportmonks key in `.env.local` was inspected against the live API. The subscription is
`Starter` (Advanced) + `World Cup 2026`, and `GET /v3/football/leagues` returns exactly
**13 leagues**:

> Premier League (8) · FA Cup (24) · Carabao Cup (27) · Bundesliga (82) · La Liga (564) ·
> World Cup (732) + its six qualification competitions (711/714/717/720/723/726/729)

**Chance Liga (262), UCL (2), UEL (5) and UECL are NOT in the plan** — `GET
/v3/football/leagues/262` answers *„you don't have access to it via your current
subscription"*. So the „one vendor for all leagues" option from the TL;DR is off the table
at this price point, and the split is not two-tier but **three**:

| Tier | Provider | Covers | Cost | Auth |
|---|---|---|---|---|
| CZ | **FAČR** IS `is.fotbal.cz` | Chance Liga, MSFL, 5. liga, 6. liga — every FAČR-filed soutěž | free | **none** |
| UEFA | **UEFA open API** `match.uefa.com/v5` | Liga mistrů, Evropská liga, Konferenční liga | free | none |
| World | **Sportmonks** | Premier League (+ Bundesliga, La Liga, FA Cup, Carabao, MS 2026) | paid, already bought | api_token |

Adding UCL/UEL/UECL to Sportmonks would be the only way to make it two-tier — decide
whether that is worth extra league slots, given the UEFA API already covers them for free
and its ids are **already the externalIds we store**.

## Provider ↔ production source map (prod DB, 2026-08-08)

| Source (prod) | Zápasů | externalId today | Feed that owns it | Bind is… |
|---|---|---|---|---|
| Premier League 2026/27 | 380 | `pl2627-arsenal-coventry-city` (synthetic) | Sportmonks league `8` | **remap needed** |
| Chance Liga 2026/27 | 224 | `8350` (synthetic) | FAČR `b905e7e9-cfe2-4606-92da-eb0e02dd8ccc` | **remap needed** |
| 3. MSFL sezóna 26/27 | 153 | *none* | FAČR `694cd96a-a84a-4801-bb6d-6dbcccfeb0e9` | **backfill needed** |
| SATUM 5. liga mužů 2026/27 | 128 | FAČR zápas GUID | FAČR `85c0fb70-b5ec-4359-9f41-2195d05e7f97` | **drop-in** ✅ |
| ČPP 6. liga mužů sk. B 2026/27 | 91 | FAČR zápas GUID | FAČR `5cf8386c-c75f-499d-a0e8-8315b29b84de` | **drop-in** ✅ |
| Konferenční liga 2026/27 | 57 | UEFA match id | UEFA `competitionId=2019` | **drop-in** ✅ |
| Evropská liga 2026/27 | 26 | UEFA match id | UEFA `competitionId=14` | **drop-in** ✅ |
| Liga mistrů 2026/27 | 20 | UEFA match id | UEFA `competitionId=1` | **drop-in** ✅ |

Five of eight sources need nothing but `app:matches:bind-feed` once their adapter exists.
All eight are currently bound to `fixture` (a repo-relative JSON path) or to nothing, which
is why no cron exists yet: replaying a static seed file is a no-op forever.

## Adapter 1 — FAČR (no login, one GET per soutěž)

The mfkfm implementation (`~/www/mfkfm/docs/facr-download-*.md`) logs in and pulls the
**club-scoped** Excel export. We do not need any of that: the **competition** pages are
fully public.

```
GET https://is.fotbal.cz/public/souteze/detail-souteze.aspx?req=<GUID>&sport=fotbal
→ 200, 320–830 KB, whole season in one HTML document, no cookies, no Cloudflare
```

Verified: MSFL → 153 zápas GUIDs (prod has exactly 153), SATUM → 128 (prod: 128),
ČPP → 91 (prod: 91), Chance Liga → **240** (prod: 224 — see the back-fill hazard below).

Each row is a `<tr class="type_false">` with seven `<td>`s:

| # | Cell | Example |
|---|---|---|
| 0 | kickoff, **Prague local**, `DD.MM.YYYY HH:MM` | `08.08.2026 10:30` |
| 1 | home + table rank | `Český Těšín (16)` |
| 2 | away + table rank | `FC Vřesina (9)` |
| 3 | score + shootout | `4 : 1 (PK:0:0)` / `-- : -- (PK:0:0)` |
| 4 | venue | `Český Těšín - tráva` |
| 5 | pzn. | `změna termínu - hlášenka`, `Původní termín: 09.08.2026 10:15` |
| 6 | akce — carries `zapas=<GUID>` + the zápis state | `nezahájen` / `zápis neuzavřen` / `zápis uzavřen` |

`zapas=<GUID>` in cell 6 **is** our `externalId`. Status mapping (all four strings observed
on live pages):

| Score cell | Zápis link | → `FeedMatchStatus` |
|---|---|---|
| `-- : --` | `nezahájen` | `Scheduled` |
| `N : M` | `zápis neuzavřen` | `Finished` (provisional — referee filed, report still open) |
| `N : M` | `zápis uzavřen` | `Finished` (official) |
| anything else | anything else | `Unknown` → reported loudly, never guessed |

Treating „neuzavřen" as finished is safe: tips lock at kickoff, and a later correction is
already a first-class path (`FeedSyncResult::$corrected` re-evaluates). **Not yet observed
and therefore unmapped: odložený zápas, kontumace (3:0 k.), and a real `(PK:x:y)` shootout.**
They will appear during the season; the `Unknown` bucket is the safety net, and each one is
a one-line addition to the mapping table.

**Scorers are NOT available anonymously** — `zapis-o-utkani-report.aspx?zapas=…` 302s to the
login. So the FAČR adapter is a **score-only provider**: it emits `events: null`, which
`FeedSynchronizer` already honors by leaving manually entered scorers untouched (P0.5). If
`scorer_hit` ever needs to be automated for CZ, the mfkfm login flow is the upgrade path —
but it is out of Phase 1.

Competition discovery is also public: an anonymous ASP.NET postback on
`prehled-soutezi.aspx` (`__EVENTTARGET=ctl00$MainContent$btnSearch`,
`ctl00$MainContent$txtSearchCislo=2026001A1A`, `listSearchRocnik=19` = ročník 2026) returns
the soutěž row with its `detail-souteze.aspx?req=<GUID>` link. That is how
`b905e7e9-…` (Chance Liga) was found. Worth wrapping as
`app:facr:find-competition <číslo>` so binding a new soutěž never means hand-copying a URL —
but a human pasting the GUID once per season is an acceptable v1.

## Adapter 2 — UEFA (one GET per soutěž)

```
GET https://match.uefa.com/v5/matches?competitionId=<1|14|2019>&seasonYear=2027&limit=100&offset=0
```

`offset` is **mandatory** (omitting it returns `404 null is not valid for offset`).
Verified on UECL: 40 matches, `status ∈ {UPCOMING, FINISHED}`, finished rows carry
`score.regular` + `score.total` and a `playerEvents.scorers[]` array with full player
objects (name, id, `goalType: SCORED`, `phase: SECOND_HALF`). So UEFA is a **full**
provider — score *and* scorers — and its `id` (e.g. `2049167`) is byte-identical to the
`externalId` already stored in prod.

Unresolved: the exact strings for postponed/cancelled/live, and whether a shootout appears
as `score.penalties` — no UEFA tie has gone to extra time yet this season. Which means:

> **P0.6 (AET / penalty shootout) is due sooner than the plan above says.** It is not
> „spring 2027" — the UECL/UEL **play-off round is late August 2026** and its second legs
> can go to extra time and penalties. Decide the representation before binding the UEFA
> adapter, or those matches will fail `setFinalScore()`'s overtime invariant.

## Adapter 3 — Sportmonks (one GET per *poll*, not per source)

The single most useful shape, verified live:

```
GET /v3/football/fixtures/between/{from}/{to}
      ?filters=fixtureLeagues:8,82,564
      &include=participants;scores;state;round;venue;events
      &per_page=50
```

One request returned 11 finished fixtures across three leagues with, per fixture:
`state` (`{id:5, state:"FT", name:"Full Time"}`), `participants` with
`meta.location = home|away`, `scores[]` rows described `CURRENT` / `1ST_HALF` / `2ND_HALF`
per participant (→ our `periodScores` fall out directly), and `events[]` with
`{minute, type_id:14, player_name, participant_id, result:"0-1"}` (→ `scorer_hit`).

**The whole Sportmonks tier therefore costs 1–2 HTTP requests per poll regardless of how
many sources are bound** — the league filter is comma-separated. Do not fetch per source.

Rate limit, observed in every response body: `rate_limit.remaining` counts down from
**2500 per hour, per requested entity** (`Fixture`, `League`, `Stage` are separate buckets),
`resets_in_seconds` ≈ 3600. A 5-minute poll is 12 requests/hour = **0.5 % of budget**.
There is no plausible way to be aggressive here.

The complete state table is `GET /v3/football/states` (25 rows) — build the mapping from it
rather than guessing:

| Sportmonks | → |
|---|---|
| `NS`, `TBA`, `PENDING`, `DELAYED` | `Scheduled` |
| `INPLAY_1ST_HALF`, `HT`, `INPLAY_2ND_HALF`, `BREAK`, `INPLAY_ET`, `EXTRA_TIME_BREAK`, `INPLAY_ET_2ND_HALF`, `PEN_BREAK`, `INPLAY_PENALTIES` | `Live` |
| `FT`, `AET`, `FT_PEN`, `AWARDED`, `WO` | `Finished` |
| `POSTPONED` | `Postponed` |
| `CANCELLED`, `DELETED` | `Cancelled` (report-only) |
| `SUSPENDED`, `ABANDONED`, `INTERRUPTED`, `AWAITING_UPDATES` | `Unknown` — deliberately: these need a human |

## Polling: make cost proportional to football, not to wall-clock

One cron entry, `*/5`, calling the existing `app:matches:sync`. Each source then decides
whether it is **due**, from the kickoffs we already store:

| Bucket | Condition (any match of this source) | Poll every |
|---|---|---|
| **hot** | state `live`, or kickoff between −15 min and +4 h ago | 5 min |
| **warm** | kickoff within the next 6 h, or ended < 24 h ago | 30 min |
| **cold** | neither | once daily, ~03:30 Prague |

On a quiet Tuesday that is **8 requests for the whole day**. On a Saturday with four CZ
soutěže playing it is a few hundred, spread out — nothing a public ASP.NET page will notice,
and 2 % of the Sportmonks hourly bucket. This needs exactly **one new nullable column**,
`match_sources.feed_polled_at`; the rest is a `FeedPollPolicy` query over `sport_matches`.

Two cheap multipliers on top:

- **Payload hash short-circuit.** Store `feed_payload_hash`; an unchanged FAČR page (830 KB
  for Chance Liga) skips parsing and ~240 `findBySourceAndExternalId` lookups entirely.
  Correctness does not depend on it — `FeedSynchronizer` is already idempotent — it just
  makes the common case free.
- **Windowed fetch for Sportmonks**: `between(today−2d, today+10d)` on hot/warm polls, full
  season once nightly. Cuts the payload ~10×. FAČR and UEFA have no date-window endpoint,
  so they always fetch whole-season; that is what the hash guard is for.

## Hazards this turns on — read before scheduling the cron

1. **Back-fill of already-played matches.** FAČR lists Chance Liga's full 240 zápasů; prod
   deliberately holds 224 (kola 3–30, because kola 1–2 were already played at seed time).
   A first sync would happily **create 16 finished matches inside a running soutěž**, where
   nobody could ever have tipped — everyone scores zero on them. Fix before binding:
   `FeedSynchronizer` must **never create a match whose kickoff is already in the past**;
   report it instead and let an admin add it deliberately. (Rescheduling/finishing an
   *existing* match is unaffected.)
2. **The tip-deadline pin.** Chance Liga rounds 6+ carry placeholder 00:00-Prague kickoffs
   with deadlines pinned by `app:tip-opening:bulk-set --deadline-own-kickoff`. The moment the
   feed corrects those kickoffs, the **override stays at the old placeholder** and tipping
   closes early — silently, for a whole round. Either re-run `bulk-set` after every sync that
   moved kickoffs, or teach the synchronizer to carry a „pinned to own kickoff" override along
   with the kickoff. This is a live foot-gun the day the Chance Liga feed goes on.
3. **Notification bursts.** `NotifyMatchAddedHandler` is already safe — it only notifies
   competitions that have *already started*, so a bulk fixture import into a fresh soutěž is
   silent. `NotifyMatchEvaluatedHandler` is not: it emits one notification per
   (user, competition) per finished match, and a Saturday sync finishing eight MSFL zápasů at
   once becomes **eight notifications in one burst** — something manual entry never produced,
   because a human enters results one at a time. Options: ship as-is and measure; or add a
   digest window („3 zápasy vyhodnoceny"); or split by channel (in-app per match, e-mail
   digested). Recommend shipping as-is *only* after the first automated round is watched.
4. **Nobody is told when a kickoff moves.** `postponeTo()`/`reschedule()` change a user's tip
   deadline and there is no notification type for it. Rare enough to ignore while a human
   moves matches; routine once a feed does. A `match_rescheduled` NotificationType is the
   natural companion to this work.
5. **Team spellings.** FAČR uses legal entity names — `AC Sparta Praha fotbal, a.s.`,
   `SK Slavia Praha - fotbal a.s.`, `ZBROJOVKA BRNO B` (upper-case) — which match nothing in
   the directory. The pending-team gate blocks creation and reports, so
   **`app:matches:sync --dry-run` is itself the alias-discovery tool**: run it once per source,
   feed the `unresolved team` lines into `app:team-alias:add`, repeat until clean, only then
   bind for real.
6. **Cron ordering.** `app:guess-reminders:send` runs at `:00`; a `*/5` sync also fires at
   `:00`. Reminders would go out against kickoffs up to 5 minutes stale. Harmless, but moving
   the reminder to `2 * * * *` removes the race for free.

## What shipped

| Piece | Where |
|---|---|
| `FeedProvider`: `facr` / `uefa` / `sportmonks` / `fixture` (+ `reportsScorers()`) | `src/Enum/FeedProvider.php` |
| Three adapters, each owning its own status mapping table | `src/Service/Feed/{Facr,Uefa,Sportmonks}MatchDataProvider.php` |
| Never create a past-kickoff match; long-past rows silently ignored | `FeedSynchronizer::createFromSnapshot` |
| Poll cadence hot/warm/cold + `match_sources.feed_polled_at` | `FeedPollPolicy`, `SportMatchRepository::hasLiveMatch` / `hasMatchKickingOffBetween` |
| The sync pass itself (fetch → dispatch → log), so the console stays a wrapper | `FeedSyncRunner`, `FeedSyncReport` |
| externalId bridge for the three mismatched sources | `ExternalIdAdopter` + `app:matches:adopt-external-ids` |
| Own-kickoff deadline pin follows a corrected kickoff | `RepinOwnKickoffDeadlinesHandler`, `SportMatchUpdated::$previousKickoffAt` |
| Per-call channel restriction; evaluation mail as ONE digest | `NotificationDelivery`, `SendMatchEvaluationDigestsHandler`, `app:match-digests:send` |
| Cron: `*/5` sync, `:10` digest | `~/www/lily.srv/apps/wtips/cron.d/wtips` |

Adapter payload shapes are pinned by unit tests built from **real trimmed responses**
(`tests/Unit/Service/Feed/`) — that is where a provider changing its HTML or JSON shows up.

## Incident 2026-08-08 — binding before bridging (fixed in code)

Chance Liga was bound to FAČR and the five-minute cron fired **before**
`app:matches:adopt-external-ids` ran. Its 224 stored matches carried synthetic ids
(`8350`…), so not one FAČR fixture was recognised, every future one looked new, and the sync
created **220 duplicate matches + 1760 `match_added` notifications** in a competition people
are tipping. Nobody had tipped the duplicates and no e-mail went out (the one user with
`match_added` e-mail on is not in that soutěž); everything was deleted the same evening.

`FeedSynchronizer` now refuses to CREATE on a source whose matches all carry ids from another
feed (`FeedSyncResult::$needsAdoption`, which fails the cron). Matches with a NULL externalId
do not trigger it — a hand-maintained source gaining feed fixtures is legitimate, and the
signal is *foreign* ids, not the mere presence of matches. So the window between binding and
bridging is now closed by the code rather than by the operator's memory.

## Rollout — one source at a time, ascending blast radius

For each source, in this order: SATUM 5. liga → ČPP 6. liga → MSFL → UEFA trio →
Chance Liga → Premier League.

1. `app:matches:bind-feed <source> <provider> <ref>` — safe even with the cron live: an
   unbridged source now imports nothing and says so.
2. `app:matches:sync --source=<source> --dry-run` — **this is the alias-discovery tool.**
   Every „unresolved team" line is an `app:team-alias:add` waiting to happen. Repeat until
   clean. Expect a lot for Chance Liga: FAČR files legal entity names („AC Sparta Praha
   fotbal, a.s.", „SK Slavia Praha - fotbal a.s.", „ZBROJOVKA BRNO B").
3. Only where the ids differ (Premier League, Chance Liga, MSFL):
   `app:matches:adopt-external-ids <source> --dry-run`, read it, then run it for real.
   Widen `--kickoff-tolerance-hours` when the stored kickoffs are placeholders — Chance Liga
   needed ~480 h (four rounds seeded at 00:00 Prague, one fixture moved by 17 days). Widening
   is safe: two candidates in the window are reported as ambiguous, never guessed.
4. `app:matches:sync --source=<source>` and read the report.
5. Leave it to the cron.

`--source=` implies `--force`, so a targeted run never waits for the cadence.

## Rollout log — 2026-08-08

All eight curated sources bound, bridged and verified against live data:

| Zdroj | Provider | Zápasů | externalId bridged | First automated results |
|---|---|---|---|---|
| Premier League 2026/27 | sportmonks `8` | 380 | 380 (adopted) | — season not started |
| Chance Liga 2026/27 | facr `b905e7e9…` | 224 | 224 (adopted, 480 h tolerance) | 4, and **201 placeholder kickoffs corrected** |
| 3. MSFL sezóna 26/27 | facr `694cd96a…` | 153 | 153 (adopted, had none) | 2 |
| SATUM 5. liga 2026/27 | facr `85c0fb70…` | 128 | 128 (drop-in) | 6 |
| ČPP 6. liga sk. B | facr `5cf8386c…` | 91 | 91 (drop-in) | — |
| Konferenční liga | uefa `2019` | 57 | 57 (drop-in) | 25 event sheets |
| Evropská liga | uefa `14` | 26 | 26 (drop-in) | 11 event sheets |
| Liga mistrů | uefa `1` | 20 | 20 (drop-in) | 2 event sheets |

Team pairing was far cheaper than feared: SATUM, ČPP and MSFL matched the directory
**100 %** (their seeds came from FAČR in the first place), Sportmonks' 20 Premier League
names matched **100 %**, and only Chance Liga needed aliases — 16, mapping FAČR's legal
entity names (`AC Sparta Praha fotbal, a.s.`) onto the directory's friendly ones. The UEFA
sources needed none at all: their matches pair by externalId, and team resolution only runs
when a match is CREATED.

The poll policy behaves as designed — a full unattended pass with nothing due costs eight
cheap DB checks and **zero HTTP requests**:

```
3. MSFL sezóna 26/27: not due (warm)      Chance Liga 2026/27: not due (hot)
Liga mistrů 2026/27: not due (cold)       Premier League 2026/27: not due (cold)
```

### What production taught us that the plan did not

1. **`symfony/css-selector` is a dev dependency.** `Crawler::filter()` works in tests, dev and
   all four CI jobs, and throws in the image (`composer install --no-dev`). CI structurally
   cannot catch this. Fixed with `filterXPath()`; recorded as a convention in CLAUDE.md.
   *(2026-08-09: the package was moved to `require`, so the trap is now the general one about
   `require-dev` rather than this package — see Day 2 below.)*
2. **Binding before bridging duplicates a season** — the incident above, now guarded in code.
3. **A placeholder kickoff can be 17 days out.** The adopter's fixed 36 h missed five Chance
   Liga fixtures; `--kickoff-tolerance-hours` now takes an operator's judgement, and the
   exactly-one-candidate rule keeps a wide window honest (480 h paired all 224, zero ambiguous).
4. **A window is the wrong question on a first fetch.** Sportmonks' ±window let adoption see
   30 of 380 Premier League fixtures; an unpolled source now fetches the season in chunks.
5. **`feedPolledAt` had to become feed-scoped.** Premier League had been polled while bound to
   a seed JSON, so switching it to Sportmonks still looked like a routine poll. `bindFeed()`
   now clears the stamp whenever the provider or ref changes — which also gives operators a
   supported way to force a season-wide re-read: unbind, rebind.

## Day 2 — 2026-08-09: what a day of real traffic surfaced

The pipeline ran unattended overnight: **166/166 `matches-sync` runs exit 0**, no non-zero exit
in either cron log, an empty messenger queue, no stuck fixtures (every past kickoff on a
feed-bound zdroj had its result), and the three Sentry issues from the rollout were all
one-offs from that evening's manual commands, none recurring. Four things still needed fixing.

1. **The feed published provisional scores.** The headline finding, from the product owner: a
   zápas in progress showed a running score, and nothing on the row distinguishes it from the
   final one people are scored against. `applyLive` now marks the match live and writes
   **nothing else** — the provider's in-play score is read and dropped. A number on a wtips
   screen means „this is how it ended", always. Locked by four tests in `FeedSynchronizerTest`;
   full reasoning in DOMAIN.md's decision log (2026-08-09).

   The live path had **no test at all** before today, which is why the behaviour shipped
   unquestioned: the plan treated „live" as a state to track and never asked what a half-played
   score means to a reader.

2. **`symfony/css-selector` moved from `require-dev` to `require`.** Item 1 of the list below
   was fixed by rewriting the selector, which cured the symptom; the disease is that a
   dev-only package used from `src/` is invisible to all four CI jobs and fails only on the
   box. The adapters keep their XPath, but `Crawler::filter()` is now safe anywhere.

3. **`app:team-alias:add` is idempotent.** Aliases arrive as a batch an operator re-runs after
   fixing one line; re-adding an alias that already points at the requested team is now a
   no-op instead of an exception (that exception is Sentry `TIPOVACKA-R`). A *different* team
   is still a hard conflict.

4. **A stuck-live match can no longer pin a zdroj to 5-minute polling.** `Live` is entered and
   left on the feed's word, so a fixture the provider abandons mid-game (Sportmonks
   `ABANDONED` maps to `Unknown`, which the synchronizer refuses to act on) keeps the state
   for good. `FeedPollPolicy` now bounds its live trigger by kickoff recency
   (`hasLiveMatchKickedOffSince`), so such a row decays to cold instead of costing 288 fetches
   a day for the rest of the season. First tests for the policy landed with it.

Two things looked wrong and are **not**: `Chance Liga 2026/27` warns „3 already-played matches
not imported" on every poll (kolo 1–2 predate the seeded 224 — correct, and self-limiting once
they age past the 7-day cutoff), and `Premier League pro Fantasy Magory` is a 380-match zdroj
with no feed (a **private** zdroj, which by design is never feed-bound; its owner maintains it
by hand).

## Still open

- **AET / penalty shootout.** DOMAIN.md (2026-07-29) locks ONE combined overtime pair — no
  split, no columns. The convention to record when the first UEFA tie goes to penalties:
  a shootout maps to the regular score **with the winner +1 goal** (1:1, won 4:3 on pens →
  overtime pair `2:1`), which keeps the same scale as a real extra-time goal, is what players
  actually tip, and satisfies the non-draw ≥ regular invariant. The UEFA adapter currently
  reports the REGULAR score only and invents nothing, so a knockout tie is scored correctly by
  every rule except `overtime_exact` until this lands. Due with the **August play-off round**.
- **FAČR statuses not yet observed**: odložený zápas, kontumace, a real `(PK:x:y)`. Each will
  arrive as an `Unknown` in the sync report, and each is one line in the adapter's table.
- **FAČR scorers** stay manual (the zápis needs a login). Promoting to the authenticated flow
  is a later, separate decision.
- **Payload hash short-circuit** (skip parsing an unchanged 830 kB FAČR page) — a pure
  optimization, deliberately not built yet.
