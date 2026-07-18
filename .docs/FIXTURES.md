# Test Fixtures Reference

All test fixture data is defined as class constants on `App\DataFixtures\AppFixtures`.
Fixtures live in `fixtures/` (namespace `App\DataFixtures`, autoloaded from `fixtures/`),
NOT in `src/`. Import in tests: `use App\DataFixtures\AppFixtures;`

- `AppFixtures` runs for both fixture groups `test` and `dev` (`tests/bootstrap.php` loads
  group `test`). `DevFixtures` (group `dev` only, depends on `AppFixtures`) adds extra
  dev-browsing data and is never loaded in tests.
- Every entity is created with hardcoded UUIDs via `Uuid::fromString()` — fixtures never
  consume `ProvideIdentity::next()`.
- All timestamps use `$now = 2025-06-15 12:00:00 UTC`, matching the MockClock fixed time
  used in all tests (never call `new \DateTimeImmutable()` without argument in tests).
- `tests/bootstrap.php` builds the schema with `doctrine:schema:create` (not migrations)
  and caches the database (`tests/.database.cache`) keyed by a hash of `migrations/` +
  `fixtures/`; changing either rebuilds automatically. DAMA DoctrineTestBundle wraps each
  test in a transaction, so the fixture baseline is always intact.

## Identity provider (tests)

`App\Tests\Support\PredictableIdentityProvider` (replaces `RandomIdentityProvider` in the
test env) returns UUIDs from a fixed pool `01933333-0000-7000-8000-0000000000XX`
(XX = 01…30), resetting between tests via `kernel.reset`.

- `FIXTURE_RESERVED_COUNT = 5`: indices 0–4 (UUIDs `…0001`–`…0005`) are reserved for the
  five fixture users below, which are persisted with those exact IDs. The provider starts
  at index 5, so the **first `next()` call in a test returns `…0006`** — avoiding unique
  constraint collisions with fixture rows.
- The pool has 30 entries; exhausting it throws (`Exhausted all predefined UUIDs`).

## Users

Password for all users: `AppFixtures::DEFAULT_PASSWORD` = `password`.

| Constant prefix          | ID (`…_ID`)                            | Email                        | Nickname        | Role  | Verified | Deleted |
|--------------------------|----------------------------------------|------------------------------|-----------------|-------|----------|---------|
| `ADMIN_*`                | `01933333-0000-7000-8000-000000000001` | admin@tipovacka.test         | `admin`         | admin | yes      | no      |
| `VERIFIED_USER_*`        | `01933333-0000-7000-8000-000000000002` | user@tipovacka.test          | `tipovac`       | user  | yes      | no      |
| `SECOND_VERIFIED_USER_*` | `01933333-0000-7000-8000-000000000099` | other@tipovacka.test         | `druhy_tipovac` | user  | yes      | no      |
| `UNVERIFIED_USER_*`      | `01933333-0000-7000-8000-000000000003` | unverified@tipovacka.test    | `novy_uzivatel` | user  | no       | no      |
| `DELETED_USER_*`         | `01933333-0000-7000-8000-000000000004` | deleted@tipovacka.test       | `smazany`       | user  | yes      | yes     |
| `ANONYMOUS_USER_*`       | `01933333-0000-7000-8000-000000000005` | — (no email, no password)    | — (no nickname) | user  | no       | no      |

Notes:

- **`SECOND_VERIFIED_USER_ID` quirk**: its ID ends in `…0099`, deliberately OUTSIDE the
  predictable provider's pool (`…0001`–`…0030`), so it can never collide with IDs handed
  out by `next()`. Since S02 it owns (and is the sole member of) `SUBSET_COMPETITION`;
  it remains an outsider for every other competition.
- `DELETED_USER` was soft-deleted at `2025-06-16 09:00:00 UTC` (one day after `$now`).
- `ANONYMOUS_USER` has no email/password/nickname; profile name is
  `ANONYMOUS_USER_FIRST_NAME` = `František`, `ANONYMOUS_USER_LAST_NAME` = `Novák`.
  It is a member of VERIFIED_COMPETITION (see memberships) so managers can practise
  tipping on behalf of someone else.

## Sport

Seeded by both the foundation migration (prod) and `AppFixtures` (dev/test — the test DB
is built by `doctrine:schema:create`, which skips the migration's seed row).

| Code       | Name   | UUID                                                          | Periods |
|------------|--------|---------------------------------------------------------------|---------|
| `football` | Fotbal | `Sport::FOOTBALL_ID` = `01960000-0000-7000-8000-000000000001` | 2 — poločas/poločasy |
| `hockey`   | Hokej  | `Sport::HOCKEY_ID` = `01960000-0000-7000-8000-000000000002`   | 3 — třetina/třetiny |

## Match sources (`MatchSource`, table `match_sources`)

| Constant prefix    | ID                                     | Name                | Kind      | Owner         |
|--------------------|----------------------------------------|---------------------|-----------|---------------|
| `PUBLIC_SOURCE_*`  | `019aaaaa-0000-7000-8000-000000000001` | `Liga mistrů 2026/27` | curated | ADMIN         |
| `PRIVATE_SOURCE_*` | `019aaaaa-0000-7000-8000-000000000002` | `Chlapi u piva`     | private   | VERIFIED_USER |

Both use sport football, `description/startAt/endAt = null`, not completed, not deleted.
(The constants keep the historical `PUBLIC_/PRIVATE_` prefixes; `PUBLIC_SOURCE` is the
curated one.)

## Competitions (`Competition`, table `competitions`)

| Constant prefix          | ID                                     | Name           | Match source   | Owner         | PIN        | Shareable link token |
|--------------------------|----------------------------------------|----------------|----------------|---------------|------------|----------------------|
| `VERIFIED_COMPETITION_*` | `019bbbbb-0000-7000-8000-000000000001` | `Kámoši u piva` | PRIVATE_SOURCE | VERIFIED_USER | `12345678` (`VERIFIED_COMPETITION_PIN`) | `VERIFIED_COMPETITION_LINK_TOKEN` = `019bbbbb00007000800000000000000119bbbbb0000700b1` |
| `PUBLIC_COMPETITION_*`   | `019bbbbb-0000-7000-8000-000000000002` | `Admin liga`   | PUBLIC_SOURCE  | ADMIN         | none (`null`) | `PUBLIC_COMPETITION_LINK_TOKEN` = `019bbbbb00007000800000000000000219bbbbb0000700b2` |
| `SUBSET_COMPETITION_*`   | `019bbbbb-0000-7000-8000-000000000033` | `Vybrané zápasy party` | PUBLIC_SOURCE | SECOND_VERIFIED_USER | none (`null`) | `SUBSET_COMPETITION_LINK_TOKEN` = `019bbbbb00007000800000000000000319bbbbb0000700b3` |

Selection mode: VERIFIED_COMPETITION and PUBLIC_COMPETITION are mode `all` with
`includePlayoff = true` (defaults). **`SUBSET_COMPETITION` is mode `subset`** with
exactly two `CompetitionMatchSelection` rows:

| Constant                        | ID                                     | Selected match     |
|---------------------------------|----------------------------------------|--------------------|
| `SUBSET_SELECTION_SCHEDULED_ID` | `019bbbbb-0000-7000-8000-00000000bb01` | `MATCH_SCHEDULED`  |
| `SUBSET_SELECTION_FINISHED_ID`  | `019bbbbb-0000-7000-8000-00000000bb02` | `MATCH_FINISHED`   |

NOT selected (⇒ `MatchNotInCompetition` when tipped there): `MATCH_LIVE`, `MATCH_PLAYOFF`.

## Memberships

| Constant                                   | ID                                     | Competition          | User           |
|--------------------------------------------|----------------------------------------|----------------------|----------------|
| `VERIFIED_COMPETITION_OWNER_MEMBERSHIP_ID` | `019bbbbb-0000-7000-8000-00000000aa01` | VERIFIED_COMPETITION | VERIFIED_USER  |
| `ANONYMOUS_MEMBERSHIP_ID`                  | `019bbbbb-0000-7000-8000-00000000aa03` | VERIFIED_COMPETITION | ANONYMOUS_USER |
| `PUBLIC_COMPETITION_OWNER_MEMBERSHIP_ID`   | `019bbbbb-0000-7000-8000-00000000aa02` | PUBLIC_COMPETITION   | ADMIN          |
| `SUBSET_COMPETITION_OWNER_MEMBERSHIP_ID`   | `019bbbbb-0000-7000-8000-00000000aa04` | SUBSET_COMPETITION   | SECOND_VERIFIED_USER |

Membership gaps useful in tests: VERIFIED_USER is NOT a member of PUBLIC_COMPETITION
(a pending join request exists instead), ADMIN is NOT a member of VERIFIED_COMPETITION,
SECOND_VERIFIED_USER's only membership is SUBSET_COMPETITION (which they own).

## Competition invitation (`CompetitionInvitation`)

| Constant                   | Value                                  |
|----------------------------|----------------------------------------|
| `PENDING_INVITATION_ID`    | `019ccccc-0000-7000-8000-000000000001` |
| `PENDING_INVITATION_TOKEN` | `abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789` |
| `PENDING_INVITATION_EMAIL` | `outsider@tipovacka.test` (not a registered user) |

Invitation to PUBLIC_COMPETITION, invited by ADMIN, created at `$now`, expires
`$now + 7 days`, not accepted, not revoked.

## Competition join request (`CompetitionJoinRequest`)

| Constant                 | Value                                  |
|--------------------------|----------------------------------------|
| `PENDING_JOIN_REQUEST_ID` | `019ccccc-0000-7000-8000-000000000002` |

VERIFIED_USER requesting to join PUBLIC_COMPETITION (valid because they are not a
member), requested at `$now`, undecided.

## Sport matches (`SportMatch`)

| Constant                     | ID                                     | Source          | Teams                          | Kickoff (UTC)       | State / score                | Round / venue |
|------------------------------|----------------------------------------|-----------------|--------------------------------|---------------------|------------------------------|---------------|
| `MATCH_SCHEDULED_ID`         | `019ddddd-0000-7000-8000-000000000001` | PUBLIC_SOURCE   | Sparta Praha vs Slavia Praha   | 2025-06-20 18:00    | scheduled                    | `Čtvrtfinále`, Generali Arena |
| `MATCH_LIVE_ID`              | `019ddddd-0000-7000-8000-000000000002` | PUBLIC_SOURCE   | Viktoria Plzeň vs Baník Ostrava | 2025-06-15 11:00   | live (began at `$now`)       | — |
| `MATCH_FINISHED_ID`          | `019ddddd-0000-7000-8000-000000000003` | PUBLIC_SOURCE   | Bohemians 1905 vs Jablonec     | 2025-06-10 18:00    | finished, **2:1**, periods **(1:0, 1:1)**, no OT | `Základní skupina`, Ďolíček |
| `MATCH_PRIVATE_SCHEDULED_ID` | `019ddddd-0000-7000-8000-000000000004` | PRIVATE_SOURCE  | Tygři vs Lvi                   | 2025-06-20 19:00    | scheduled                    | — |
| `MATCH_PLAYOFF_ID`           | `019ddddd-0000-7000-8000-000000000005` | PUBLIC_SOURCE   | Real Madrid vs Barcelona       | 2025-06-22 18:00    | scheduled, **isPlayoff=true** | `Playoff` |

`MATCH_PLAYOFF` is the only fixture match with `isPlayoff = true` — every other match
defaults to `false`.

## Players (`Player`, table `players`) — roster pool of PUBLIC_SOURCE

| Constant                    | ID                                     | Team           | Name (`PLAYER_*_NAME`) |
|-----------------------------|----------------------------------------|----------------|------------------------|
| `PLAYER_HOME_SCORER_ONE_ID` | `019ddddd-0000-7000-8000-0000000000b1` | Bohemians 1905 | `Jan Novák`            |
| `PLAYER_HOME_SCORER_TWO_ID` | `019ddddd-0000-7000-8000-0000000000b2` | Bohemians 1905 | `Petr Svoboda`         |
| `PLAYER_AWAY_BOOKED_ID`     | `019ddddd-0000-7000-8000-0000000000b3` | Jablonec       | `Marek Doležal`        |

## Match events (`MatchEvent`, table `match_events`) — timeline of MATCH_FINISHED

| Constant                     | ID                                     | Type        | Side | Minute | Player                |
|------------------------------|----------------------------------------|-------------|------|--------|-----------------------|
| `MATCH_EVENT_GOAL_ONE_ID`    | `019ddddd-0000-7000-8000-0000000000c1` | goal        | home | 27     | PLAYER_HOME_SCORER_ONE |
| `MATCH_EVENT_GOAL_TWO_ID`    | `019ddddd-0000-7000-8000-0000000000c2` | goal        | home | 63     | PLAYER_HOME_SCORER_TWO |
| `MATCH_EVENT_YELLOW_CARD_ID` | `019ddddd-0000-7000-8000-0000000000c3` | yellow_card | away | 51     | PLAYER_AWAY_BOOKED     |

Note the deliberate mismatch: the away goal of the 2:1 result has **no** scorer event —
goal-count vs score consistency is a UI warning only, never enforced.

## Guess + evaluation

| Constant                            | ID                                     | What |
|-------------------------------------|----------------------------------------|------|
| `FIXTURE_GUESS_ID`                  | `019eeeee-0000-7000-8000-000000000001` | ADMIN's guess **3:0** on MATCH_FINISHED (actual 2:1) in PUBLIC_COMPETITION, submitted at `$now` |
| `FIXTURE_GUESS_EVALUATION_ID`       | `019eeeee-0000-7000-8000-000000000002` | Evaluation of that guess, evaluated at `$now` |
| `FIXTURE_GUESS_EVAL_RULE_POINTS_ID` | `019eeeee-0000-7000-8000-000000000003` | Single rule-points row: `correct_outcome` → **3 points** (both picked home win; exact score missed) |

## Rule configurations (`CompetitionRuleConfiguration`)

All three competitions get the four default rules, all enabled (rules are
per-competition since S04 — sources own no rules):

| Constant                                          | Competition          | Rule identifier      | Points |
|---------------------------------------------------|----------------------|----------------------|--------|
| `VERIFIED_COMPETITION_RULE_EXACT_SCORE_ID`        | VERIFIED_COMPETITION | `exact_score`        | 5 |
| `VERIFIED_COMPETITION_RULE_CORRECT_OUTCOME_ID`    | VERIFIED_COMPETITION | `correct_outcome`    | 3 |
| `VERIFIED_COMPETITION_RULE_CORRECT_HOME_GOALS_ID` | VERIFIED_COMPETITION | `correct_home_goals` | 1 |
| `VERIFIED_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID` | VERIFIED_COMPETITION | `correct_away_goals` | 1 |
| `PUBLIC_COMPETITION_RULE_EXACT_SCORE_ID`          | PUBLIC_COMPETITION   | `exact_score`        | 5 |
| `PUBLIC_COMPETITION_RULE_CORRECT_OUTCOME_ID`      | PUBLIC_COMPETITION   | `correct_outcome`    | 3 |
| `PUBLIC_COMPETITION_RULE_CORRECT_HOME_GOALS_ID`   | PUBLIC_COMPETITION   | `correct_home_goals` | 1 |
| `PUBLIC_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID`   | PUBLIC_COMPETITION   | `correct_away_goals` | 1 |
| `SUBSET_COMPETITION_RULE_EXACT_SCORE_ID`          | SUBSET_COMPETITION   | `exact_score`        | 5 |
| `SUBSET_COMPETITION_RULE_CORRECT_OUTCOME_ID`      | SUBSET_COMPETITION   | `correct_outcome`    | 3 |
| `SUBSET_COMPETITION_RULE_CORRECT_HOME_GOALS_ID`   | SUBSET_COMPETITION   | `correct_home_goals` | 1 |
| `SUBSET_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID`   | SUBSET_COMPETITION   | `correct_away_goals` | 1 |

UUIDs are `019fffff-0000-7000-8000-0000000000XX` with XX = 01–12 in the table's order.

## Tie resolution

`FIXTURE_TIE_RESOLUTION_ID` = `019eeeee-0000-7000-8000-000000000004` is a **reserved
constant only** — `AppFixtures::load()` does not persist any `LeaderboardTieResolution`
row. Use it as a stable ID when a test needs to create one.
