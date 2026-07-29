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

> **Browsing the app, not writing a test?** Jump to
> [Development worlds (`DevFixtures`)](#development-worlds-devfixtures) — that section is the
> map of what `composer db:reset` puts in front of you, which user to log in as, and what each
> page looks like with that data. Everything above it describes the shared *test* baseline.

## Identity provider (tests)

`App\Tests\Support\PredictableIdentityProvider` (replaces `RandomIdentityProvider` in the
test env) returns UUIDs from a fixed pool `01933333-0000-7000-8000-0000000000XX`
(XX = 01…80, digits-only suffixes — grown in S06 for the heavier recalculation flows:
8 provisioned rules plus scorer/period rule-point rows), resetting between tests via
`kernel.reset`.

- `FIXTURE_RESERVED_COUNT = 5`: indices 0–4 (UUIDs `…0001`–`…0005`) are reserved for the
  five fixture users below, which are persisted with those exact IDs. The provider starts
  at index 5, so the **first `next()` call in a test returns `…0006`** — avoiding unique
  constraint collisions with fixture rows.
- The pool has 80 entries; exhausting it throws (`Exhausted all predefined UUIDs`).

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
  predictable provider's pool (`…0001`–`…0080`), so it can never collide with IDs handed
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
| `GLOBAL_COMPETITION_*`      | `019bbbbb-0000-7000-8000-000000000044` | `Globální tipovačka LM`     | PUBLIC_SOURCE | ADMIN | none (`null`) | none (`null`) |
| `FREE_GLOBAL_COMPETITION_*` | `019bbbbb-0000-7000-8000-000000000045` | `Globální tipovačka zdarma` | PUBLIC_SOURCE | ADMIN | none (`null`) | none (`null`) |
| `PREMIUM_COMPETITION_*`     | `019bbbbb-0000-7000-8000-000000000055` | `Prémiová firemní liga`     | PUBLIC_SOURCE | ADMIN | none (`null`) | `PREMIUM_COMPETITION_LINK_TOKEN` = `019bbbbb00007000800000000000000519bbbbb0000700b5` |
| `BOOSTS_COMPETITION_*`      | `019bbbbb-0000-7000-8000-000000000066` | `Příspěvková firemní liga`  | PUBLIC_SOURCE | ADMIN | none (`null`) | `BOOSTS_COMPETITION_LINK_TOKEN` = `019bbbbb00007000800000000000000619bbbbb0000700b6` |

All competitions: `tipsLockedAt = null` (never manually locked),
`tipChangeOffsetMinutes = 60` (default) and `monetization = None` (S08 entity
default — the create-competition wizard sets `premium|boosts`, fixtures keep None)
— **except `PREMIUM_COMPETITION`** (Premium) and `BOOSTS_COMPETITION` (Boosts) (see S10 below).

**S10 premium competition** (`PREMIUM_COMPETITION`, `monetization = Premium`,
`isGlobal = false`, mode `all` over the PUBLIC curated source, owned by ADMIN — the
paying manager): its earliest included kickoff is MATCH_FINISHED (2025-06-10, in the
past vs the fixed clock), so the reconcile sweep treats it as **started**. It has the
four default rule configs (`PREMIUM_COMPETITION_RULE_*` = `019fffff-…-16…19`) and a
shareable link (tests add joiners with it). SECOND_VERIFIED_USER is a non-owner member
with an already-**Charged** `CompetitionPremiumCharge` (`PREMIUM_CHARGE_ID` =
`019bbbbb-0000-7000-8000-0000000000d1`, amount 10). **No wallet/ledger is seeded** for
the charge (that would break the whole-table credit asserts, see below) — the Charged
row just represents the already-paid state; tests grant the owner credits in-test.

**S10 boosts competition** (`BOOSTS_COMPETITION`, `monetization = Boosts`,
`isGlobal = false`, mode `all` over the PUBLIC curated source, owned by ADMIN): four
default rule configs (`BOOSTS_COMPETITION_RULE_*` = `019fffff-…-1a…1d`) and a shareable
link. SECOND_VERIFIED_USER is the single non-owner member and holds one **active**
`BoostPurchase` of type `OthersTips` (`BOOST_PURCHASE_OTHERS_TIPS_ID` =
`019bbbbb-0000-7000-8000-0000000000e1`, `pricePaid = 20`) — the entitled viewer.
VERIFIED_USER is deliberately NOT a member (it stays the „single competition" user
other count tests rely on); visibility tests join a second, non-entitled member on the
fly (via the shareable link). **No wallet/ledger is seeded** for the purchase (would
break the whole-table credit asserts) — the row alone drives the entitlement, exactly
like the premium charge.

**S09 global competitions** (`isGlobal = true`, mode `all`, owned by ADMIN, both
over the PUBLIC curated source; the ADMIN owner is the sole member of each ⇒ fee
still unlocked): `GLOBAL_COMPETITION` charges `GLOBAL_COMPETITION_ENTRY_FEE = 50`
credits; `FREE_GLOBAL_COMPETITION` is fee `0`. Every other fixture competition is
`isGlobal = false`, `entryFeeCredits = 0`. VERIFIED_USER and SECOND_VERIFIED_USER
are NOT members of either ⇒ both can be used to test joining.

Selection mode: VERIFIED_COMPETITION and PUBLIC_COMPETITION are mode `all` with
`includePlayoff = true` (defaults). **`SUBSET_COMPETITION` is mode `subset`** with
exactly two `CompetitionMatchSelection` rows:

| Constant                        | ID                                     | Selected match     |
|---------------------------------|----------------------------------------|--------------------|
| `SUBSET_SELECTION_SCHEDULED_ID` | `019bbbbb-0000-7000-8000-00000000bb01` | `MATCH_SCHEDULED`  |
| `SUBSET_SELECTION_FINISHED_ID`  | `019bbbbb-0000-7000-8000-00000000bb02` | `MATCH_FINISHED`   |

NOT selected (⇒ `MatchNotInCompetition` when tipped there): `MATCH_LIVE`, `MATCH_PLAYOFF`.

### Tip locking in fixtures (S07)

Since S07 tips lock at **competition start** (earliest included kickoff, or a manual
`tipsLockedAt`), with one escape hatch: matches that ENTERED the competition after its
lock moment (mode All: `max(SportMatch.createdAt, Competition.createdAt)` > lock; mode
Subset: selection `addedAt` > lock) keep their own kickoff as the deadline
(`EffectiveTipDeadlineResolver`).

Because every fixture row is created at `$now = 2025-06-15 12:00 UTC`, the fixture
competitions on the PUBLIC source **naturally exercise the late-added branch**:

| Competition | Lock moment (earliest included kickoff) | Why scheduled matches stay tippable |
|---|---|---|
| PUBLIC_COMPETITION | `2025-06-10 18:00` (MATCH_FINISHED) — in the past | ALL matches have `createdAt = 2025-06-15 12:00` > lock ⇒ **late-added** ⇒ deadline = own kickoff |
| SUBSET_COMPETITION | `2025-06-10 18:00` (MATCH_FINISHED is selected) | both selections have `addedAt = 2025-06-15 12:00` > lock ⇒ **late-added** ⇒ deadline = own kickoff |
| VERIFIED_COMPETITION | `2025-06-20 19:00` (MATCH_PRIVATE_SCHEDULED) — in the future | not started yet ⇒ default branch, deadline = first kickoff (= the match's own kickoff) |

Practical consequences for tests:

- Submitting on `MATCH_SCHEDULED` / `MATCH_PLAYOFF` / `MATCH_PRIVATE_SCHEDULED` works
  exactly as before S07 (deadline = kickoff, all in the future).
- To test **locked** tipping, either dispatch `LockCompetitionTipsCommand` (locks at the
  MockClock now, 12:00 — a fixture match created at 12:00 is NOT late-added because the
  comparison is strictly `>`), or advance the `MockClock` before creating a match to make
  it late-added (see `LockCompetitionTipsHandlerTest`).
- VERIFIED_COMPETITION is the natural place to test manual lock/unlock (its first kickoff
  is still ahead ⇒ unlock allowed); SUBSET_COMPETITION is the natural "already started"
  competition (unlock rejected with `CompetitionTipsCannotBeUnlocked`).

## Memberships

| Constant                                   | ID                                     | Competition          | User           |
|--------------------------------------------|----------------------------------------|----------------------|----------------|
| `VERIFIED_COMPETITION_OWNER_MEMBERSHIP_ID` | `019bbbbb-0000-7000-8000-00000000aa01` | VERIFIED_COMPETITION | VERIFIED_USER  |
| `ANONYMOUS_MEMBERSHIP_ID`                  | `019bbbbb-0000-7000-8000-00000000aa03` | VERIFIED_COMPETITION | ANONYMOUS_USER |
| `PUBLIC_COMPETITION_OWNER_MEMBERSHIP_ID`   | `019bbbbb-0000-7000-8000-00000000aa02` | PUBLIC_COMPETITION   | ADMIN          |
| `SUBSET_COMPETITION_OWNER_MEMBERSHIP_ID`   | `019bbbbb-0000-7000-8000-00000000aa04` | SUBSET_COMPETITION   | SECOND_VERIFIED_USER |
| `GLOBAL_COMPETITION_OWNER_MEMBERSHIP_ID`      | `019bbbbb-0000-7000-8000-00000000aa05` | GLOBAL_COMPETITION      | ADMIN |
| `FREE_GLOBAL_COMPETITION_OWNER_MEMBERSHIP_ID` | `019bbbbb-0000-7000-8000-00000000aa06` | FREE_GLOBAL_COMPETITION | ADMIN |
| `PREMIUM_COMPETITION_OWNER_MEMBERSHIP_ID`     | `019bbbbb-0000-7000-8000-00000000aa07` | PREMIUM_COMPETITION     | ADMIN |
| `PREMIUM_COMPETITION_MEMBER_MEMBERSHIP_ID`    | `019bbbbb-0000-7000-8000-00000000aa08` | PREMIUM_COMPETITION     | SECOND_VERIFIED_USER |
| `BOOSTS_COMPETITION_OWNER_MEMBERSHIP_ID`        | `019bbbbb-0000-7000-8000-00000000aa09` | BOOSTS_COMPETITION | ADMIN |
| `BOOSTS_COMPETITION_MEMBER_MEMBERSHIP_ID`       | `019bbbbb-0000-7000-8000-00000000aa0a` | BOOSTS_COMPETITION | SECOND_VERIFIED_USER |

Membership gaps useful in tests: VERIFIED_USER is NOT a member of PUBLIC_COMPETITION,
ADMIN is NOT a member of VERIFIED_COMPETITION. Neither VERIFIED_USER nor
SECOND_VERIFIED_USER is a member of the two global competitions (ADMIN owns both).
SECOND_VERIFIED_USER owns SUBSET_COMPETITION and (since S10) is a non-owner member of
PREMIUM_COMPETITION and of BOOSTS_COMPETITION (where it holds the OthersTips boost).
VERIFIED_USER is deliberately kept out of the S10 monetized competitions (it stays the
„single competition" user); it is a natural joiner for PREMIUM_COMPETITION / BOOSTS_COMPETITION
(via their shareable links) in charge / visibility tests.

## Credit wallets — none seeded

No `CreditWallet`/`CreditTransaction` rows are seeded: several credit tests
(`AdjustUserCreditsHandlerTest`, `FulfillCreditPurchaseHandlerTest`, …) assert over
the WHOLE `credit_transactions` table with `getOneOrNullResult()`, so any seeded
ledger row would make them throw `NonUniqueResult`. Paid-global-join tests therefore
grant a balance in-test (dispatch `AdjustUserCreditsCommand`), and use
SECOND_VERIFIED_USER (no wallet, balance 0) as the "insufficient credits" subject.

The single seeded `CompetitionPremiumCharge` (`PREMIUM_CHARGE_ID`, status Charged) is
deliberately **not** backed by a wallet/ledger row for the same reason — it has no
FK to `credit_wallets`/`credit_transactions`, so it never trips the whole-table credit
asserts. It stands for an already-charged member; premium tests that need real
balances grant the owner (ADMIN) credits in-test.

## Premium charges (`CompetitionPremiumCharge`, table `competition_premium_charges`)

| Constant           | ID                                     | Competition         | Member               | Status  | Amount |
|--------------------|----------------------------------------|---------------------|----------------------|---------|--------|
| `PREMIUM_CHARGE_ID`| `019bbbbb-0000-7000-8000-0000000000d1` | PREMIUM_COMPETITION | SECOND_VERIFIED_USER | Charged | 10     |

## Boost purchases (`BoostPurchase`, table `boost_purchases`)

| Constant                        | ID                                     | Competition        | User                 | Type       | Price | Active |
|---------------------------------|----------------------------------------|--------------------|----------------------|------------|-------|--------|
| `BOOST_PURCHASE_OTHERS_TIPS_ID` | `019bbbbb-0000-7000-8000-0000000000e1` | BOOSTS_COMPETITION | SECOND_VERIFIED_USER | OthersTips | 20    | yes    |

Like the premium charge, this row has **no** backing wallet/ledger (keeps the whole-table
credit asserts intact) — it just represents an already-bought boost, and drives the
`CompetitionEntitlements` / `TipVisibilityGate` entitlement for SECOND_VERIFIED_USER.

## Recorded domain events (test spy)

`App\Tests\Support\RecordedDomainEvents` is a test-only event.bus handler (registered
in `config/services_test.php`) that captures the recording-only S10 premium/boost events
(`PremiumConfirmed`, `PremiumDowngraded`, `PremiumChargeUncovered`, `PremiumBalanceLow`,
`BoostRefunded`). Integration tests get it via `IntegrationTestCase::recordedDomainEvents()`
and assert with `->ofType(EventClass::class)`; call `->reset()` between phases of a test.

## Competition invitation (`CompetitionInvitation`)

| Constant                   | Value                                  |
|----------------------------|----------------------------------------|
| `PENDING_INVITATION_ID`    | `019ccccc-0000-7000-8000-000000000001` |
| `PENDING_INVITATION_TOKEN` | `abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789` |
| `PENDING_INVITATION_EMAIL` | `outsider@tipovacka.test` (not a registered user) |

Invitation to PUBLIC_COMPETITION, invited by ADMIN, created at `$now`, expires
`$now + 7 days`, not accepted, not revoked.

> The join-request flow was retired in S09 (global competitions replace public
> discovery join-requests). There is no `CompetitionJoinRequest` fixture anymore.

## Teams (`Team`)

Global directory teams (`match_source_id` NULL, sport = football) plus two local teams of the
PRIVATE source. Some globals carry the optional short name / country / brand color.

| Constant               | ID                                     | Scope  | Name           | Short / Country / Color |
|------------------------|----------------------------------------|--------|----------------|-------------------------|
| `TEAM_REAL_MADRID_ID`  | `019ddddd-0000-7000-8000-0000000000d1` | global | Real Madrid    | RMA / ES / `#FEBE10` |
| `TEAM_BARCELONA_ID`    | `019ddddd-0000-7000-8000-0000000000d2` | global | Barcelona      | BAR / ES / `#A50044` |
| `TEAM_SPARTA_ID`       | `019ddddd-0000-7000-8000-0000000000d3` | global | Sparta Praha   | SPA / CZ / `#EE1C25` |
| `TEAM_SLAVIA_ID`       | `019ddddd-0000-7000-8000-0000000000d4` | global | Slavia Praha   | SLA / CZ / `#D7141A` |
| `TEAM_PLZEN_ID`        | `019ddddd-0000-7000-8000-0000000000d5` | global | Viktoria Plzeň | PLZ / CZ / `#005CA9` |
| `TEAM_BANIK_ID`        | `019ddddd-0000-7000-8000-0000000000d6` | global | Baník Ostrava  | BAN / CZ / — |
| `TEAM_BOHEMIANS_ID`    | `019ddddd-0000-7000-8000-0000000000d7` | global | Bohemians 1905 | BOH / CZ / `#00843D` |
| `TEAM_JABLONEC_ID`     | `019ddddd-0000-7000-8000-0000000000d8` | global | Jablonec       | JBL / CZ / — |
| `TEAM_TYGRI_ID`        | `019ddddd-0000-7000-8000-0000000000d9` | local (PRIVATE_SOURCE) | Tygři | — |
| `TEAM_LVI_ID`          | `019ddddd-0000-7000-8000-0000000000da` | local (PRIVATE_SOURCE) | Lvi   | — |

## Sport matches (`SportMatch`)

Team columns are `home_team_id` / `away_team_id` FKs to the teams above (the „Teams" column
below shows their names).

| Constant                     | ID                                     | Source          | Teams                          | Kickoff (UTC)       | State / score                | Round / venue |
|------------------------------|----------------------------------------|-----------------|--------------------------------|---------------------|------------------------------|---------------|
| `MATCH_SCHEDULED_ID`         | `019ddddd-0000-7000-8000-000000000001` | PUBLIC_SOURCE   | Sparta Praha vs Slavia Praha   | 2025-06-20 18:00    | scheduled                    | `Čtvrtfinále`, Generali Arena |
| `MATCH_LIVE_ID`              | `019ddddd-0000-7000-8000-000000000002` | PUBLIC_SOURCE   | Viktoria Plzeň vs Baník Ostrava | 2025-06-15 11:00   | live (began at `$now`)       | — |
| `MATCH_FINISHED_ID`          | `019ddddd-0000-7000-8000-000000000003` | PUBLIC_SOURCE   | Bohemians 1905 vs Jablonec     | 2025-06-10 18:00    | finished, **2:1**, periods **(1:0, 1:1)**, no OT | `Základní skupina`, Ďolíček |
| `MATCH_PRIVATE_SCHEDULED_ID` | `019ddddd-0000-7000-8000-000000000004` | PRIVATE_SOURCE  | Tygři vs Lvi                   | 2025-06-20 19:00    | scheduled                    | — |
| `MATCH_PLAYOFF_ID`           | `019ddddd-0000-7000-8000-000000000005` | PUBLIC_SOURCE   | Real Madrid vs Barcelona       | 2025-06-22 18:00    | scheduled, **isPlayoff=true** | `Playoff` |

`MATCH_PLAYOFF` is the only fixture match with `isPlayoff = true` — every other match
defaults to `false`.

## Players (`Player`, table `players`) — per-team roster

Each player belongs to a `Team` (`team_id` FK). The scorers below hang off the global teams of
MATCH_FINISHED (Bohemians home, Jablonec away).

| Constant                    | ID                                     | Team (`team_id`)                 | Name (`PLAYER_*_NAME`) |
|-----------------------------|----------------------------------------|----------------------------------|------------------------|
| `PLAYER_HOME_SCORER_ONE_ID` | `019ddddd-0000-7000-8000-0000000000b1` | Bohemians 1905 (`TEAM_BOHEMIANS_ID`) | `Jan Novák`        |
| `PLAYER_HOME_SCORER_TWO_ID` | `019ddddd-0000-7000-8000-0000000000b2` | Bohemians 1905 (`TEAM_BOHEMIANS_ID`) | `Petr Svoboda`     |
| `PLAYER_AWAY_BOOKED_ID`     | `019ddddd-0000-7000-8000-0000000000b3` | Jablonec (`TEAM_JABLONEC_ID`)        | `Marek Doležal`    |

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
| `SUBSET_GUESS_ID`                   | `019eeeee-0000-7000-8000-000000000005` | S06: SECOND_VERIFIED_USER's guess **2:1** on MATCH_FINISHED in SUBSET_COMPETITION with period tips `[[1,0],[1,1]]`. **No evaluation seeded** — evaluation tests trigger it themselves |
| `SUBSET_GUESS_SCORER_ID`            | `019eeeee-0000-7000-8000-000000000006` | S06: scorer tip on that guess → PLAYER_HOME_SCORER_ONE (`Jan Novák`, a correct scorer of the 2:1) |

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
| `SUBSET_COMPETITION_RULE_SCORER_HIT_ID`           | SUBSET_COMPETITION   | `scorer_hit`         | 2 |
| `SUBSET_COMPETITION_RULE_PERIOD_EXACT_ID`         | SUBSET_COMPETITION   | `period_exact`       | 5 |
| `SUBSET_COMPETITION_RULE_PERIOD_TENDENCY_ID`      | SUBSET_COMPETITION   | `period_tendency`    | 2 |

UUIDs are `019fffff-0000-7000-8000-0000000000XX` with XX = 01–15 in the table's order.

**S06 feature-on example**: only SUBSET_COMPETITION has the optional rules
`scorer_hit` + `period_exact` + `period_tendency` **enabled** (fixture rows above);
`overtime_exact` has NO stored row anywhere (⇒ disabled via `enabledByDefault=false`) —
tests enable it per competition via `UpdateCompetitionRuleConfigurationCommand`.
VERIFIED and PUBLIC competitions keep every optional rule off, so they double as the
"payload part rejected" fixtures (`GuessFeatureNotEnabled`).

## Tie resolution

`FIXTURE_TIE_RESOLUTION_ID` = `019eeeee-0000-7000-8000-000000000004` is a **reserved
constant only** — `AppFixtures::load()` does not persist any `LeaderboardTieResolution`
row. Use it as a stable ID when a test needs to create one.

## Notifications (`Notification`, table `notifications`) — S11

Two rows for **VERIFIED_USER**, both tied to VERIFIED_COMPETITION (own `019a0000-…`
block, clear of the identity pool). Content is pre-rendered Czech (title/body/url).

| Constant                 | ID                                     | Type            | State  | createdAt   |
|--------------------------|----------------------------------------|-----------------|--------|-------------|
| `NOTIFICATION_UNREAD_ID` | `019a0000-0000-7000-8000-0000000000f1` | MatchAdded      | unread | now − 2 h   |
| `NOTIFICATION_READ_ID`   | `019a0000-0000-7000-8000-0000000000f2` | MatchEvaluated  | read   | now − 1 day |

So VERIFIED_USER always has **exactly one unread** notification — bell badge / center
mark-read flows assert on that. Both carry a `url` pointing at the competition leaderboard.

## Leaderboard snapshots (`LeaderboardSnapshot`, table `leaderboard_snapshots`) — S12

Seeded for **VERIFIED_COMPETITION** (own `019a1111-…` block, clear of the identity pool).
`$now = 2025-06-15 12:00 UTC` ⇒ Prague today = 2025-06-15, „yesterday" = 2025-06-14.
VERIFIED_COMPETITION has **no finished matches** (its only match is Scheduled), so its
live board is all-zeros (both members tied rank 1). The snapshots mirror that reality —
**0 points, rank 1** — so no screen ever shows points the board cannot justify.

| Constant                         | ID                                     | Day        | User           | Rank | Points |
|----------------------------------|----------------------------------------|------------|----------------|------|--------|
| `SNAPSHOT_YESTERDAY_VERIFIED_ID` | `019a1111-0000-7000-8000-000000000001` | 2025-06-14 | VERIFIED_USER  | 1    | 0      |
| `SNAPSHOT_TODAY_VERIFIED_ID`     | `019a1111-0000-7000-8000-000000000003` | 2025-06-15 | VERIFIED_USER  | 1    | 0      |
| `SNAPSHOT_TODAY_ANONYMOUS_ID`    | `019a1111-0000-7000-8000-000000000004` | 2025-06-15 | ANONYMOUS_USER | 1    | 0      |

`day` is a Prague-midnight DATE; the yesterday row carries `createdAt = now − 1 day`,
today's `createdAt = now`. VERIFIED_USER (owner) is present on both days; ANONYMOUS_USER
joined at `$now` (2025-06-15) so it appears only on today's snapshot. Δ compares today's
rank to the **latest day strictly before today** (2025-06-14): VERIFIED_USER is **beze
změny** (rank 1 → 1), ANONYMOUS_USER is **„nový"** (absent from the 2025-06-14 baseline).
The 2025-06-15 rows feed the member breakdown „Vývoj" list — they are NOT used for
today's Δ.

Because VERIFIED_COMPETITION has no evaluations, the daily sweep skips it (nothing new
since its last snapshot), so its **three** seeded rows stay intact across a sweep. No
other AppFixtures competition has snapshots, so their leaderboards render a neutral Δ dot.

**DevFixtures** (dev browser only) adds the rich, moving Δ demo: a genuine EARLIER
standing of the **VŠCHT tipovačka** competition, dated 2025-06-09 — the board as it stood
after only the first finished Fortuna match (Sparta 3:1, 2025-06-08), before the second
(Plzeň 2:2, 2025-06-10) reshuffled it. Being a real partial-sum state, every seeded total
is ≤ that member's current total, so the leaderboard Δ shows honest movement and the
member „Vývoj" never exceeds the live „Celkem bodů".

## Notification preferences (`NotificationPreference`, table `notification_preferences`)

| Constant                     | ID                                     | User          | Type           | inApp | email |
|------------------------------|----------------------------------------|---------------|----------------|-------|-------|
| `NOTIFICATION_PREFERENCE_ID` | `019a0000-0000-7000-8000-0000000000f3` | VERIFIED_USER | MatchEvaluated | true  | false |

Only ONE explicit override is seeded; every other type falls back to
`NotificationType::defaultInApp()` / `defaultEmail()`. In-app defaults ON for all types;
email defaults ON only for guess reminder, competition ended, the three premium problems,
and boost refunded.

---

# Development worlds (`DevFixtures`)

`App\DataFixtures\DevFixtures` (group `dev` only, **never loaded in tests**) is what
`docker compose exec web composer db:reset` puts in the browser. On top of the older
demo data (25 users, Euro 2024, Fortuna Liga, Firemní liga) it seeds **four
self-contained worlds** designed for the UI/nav restructure — one per situation the
rebuilt pages have to render.

**These worlds are anchored to the REAL calendar** (`today ± n days`), not to the
`2025-06-15` MockClock instant the test baseline uses. A `db:reset` therefore always
produces a tournament that is genuinely half-played: past matches carry results,
upcoming ones are still tippable, and „Posledních 7 dní" / „Poslední kolo" are never
empty. Everything is created at real *now*, which is after each world's earliest
kickoff, so every match counts as **late-added** and keeps its own kickoff as the tip
deadline (`EffectiveTipDeadlineResolver`) — i.e. the upcoming fixtures really are
tippable in the browser.

Reference constants live on `DevFixtures` itself (`DevFixtures::WORLD_CUP_COMPETITION_ID`, …).

## Logins

Password for **every** dev/test user: `AppFixtures::DEFAULT_PASSWORD` = `password`.

| Login | Who | Verified | Use it for |
|---|---|---|---|
| `user@tipovacka.test` (`tipovac`) | **the primary dev user** | yes | everything — it is a member of six competitions, mid-table in the big one, owns two of them, has a wallet |
| `admin@tipovacka.test` (`admin`) | system admin, owns the World-Cup source + global competition | yes | admin area, global-competition management |
| `other@tipovacka.test` (`druhy_tipovac`) | outsider | yes | joining flows (member of almost nothing) |
| `honza@tipovacka.dev`, `petros@…`, … 25 in total | the demo crowd, `{nickname}@tipovacka.dev` | yes | second/third player perspectives |
| **`neovereny@tipovacka.dev`** (`neovereny`) | **the ONE unverified dev login** | **no** | the e-mail-verification airlock — it is a member of nothing on purpose, so it never muddies a leaderboard |
| `unverified@tipovacka.test` (`novy_uzivatel`) | the AppFixtures unverified user | no | same airlock, from the test baseline |

`mischa@tipovacka.dev` (`users[8]`) is **deactivated** — the blocked-user state in the admin UI.

### What the primary dev user (`user@tipovacka.test`) is in

| Competition | Role | Standing | Monetization |
|---|---|---|---|
| **Tipovačka MS 2026** (World A) | member | **7th of 24**, tied on 32 b | boosts — owns „Konkrétní tipy kolegů" ⇒ **unlocked** |
| **Fandíme Česku** (World D) | member | 2nd of 6 (13 b) | boosts — owns nothing ⇒ **locked** |
| **Sousedský pohár** (World B) | **organizer** | 1st of 6 (13 b) | premium, all toggles ON for everyone |
| **Zimní pohár – parta** (World C) | member | tied 1st, tie resolved to 2nd | boosts, competition is over |
| `Kámoši u piva` (AppFixtures) | organizer | — | none |
| `VŠCHT tipovačka` (older dev data) | organizer | — | none |

Credit balance: **35 kr.** (`DevFixtures::DEV_USER_CREDIT_BALANCE`) — enough for either
cheaper boost, five short of „Měnit tip" (40 kr.), so the *insufficient credits* branch is
one click away. The balance is derived from `Credits\PricingConfig`, never a literal. The
seeded ledger is honest: `+135` admin grant → `−30` entry fee (World A) → `5 × −10` premium
per player (World B) → `−20` boost (World A) = **35**. `martas` has a second, smaller wallet
(`10 kr.` left after buying the 10 kr. distribution boost).

## Map of the worlds

### World A — „Tipovačka MS 2026" · the leaderboard playground
`DevFixtures::WORLD_CUP_COMPETITION_ID` = `019bbbbb-0000-7000-8000-0000000000f1`

A **paid, global (publicly discoverable)** competition run by `admin` over the curated
source **Mistrovství světa 2026** (`WORLD_CUP_SOURCE_ID` = `019aaaaa-…-0000000000f1`),
entry fee **30 kr.** (`WORLD_CUP_COMPETITION_ENTRY_FEE`), monetization **boosts**.
**24 members**, every one of them with a *designed* point total from 47 down to 4, so the
board has a real podium, a real mid-table, a genuine **tie on 32 b** (`tipovac` and
`honza`, both rank 7) and a long tail worth collapsing behind a „…" gap. Accuracy,
exact-hit counts and scoring streaks all differ because each member's hits are spread over
different matches. **Use this one to work on the žebříček.**

### World B — „Sousedský pohár" · the small private premium competition
`NEIGHBOURS_COMPETITION_ID` = `019bbbbb-0000-7000-8000-0000000000f3`

A **private, from-scratch** source (`NEIGHBOURS_SOURCE_ID`, local teams only) organized by
the **primary dev user**. Six members including one **anonymous member with no e-mail**
(`Josef Dvořák`, `NEIGHBOURS_ANONYMOUS_USER_ID`) and one **pending e-mail invitation**
(`soused@tipovacka.dev`, `NEIGHBOURS_INVITATION_TOKEN`). Monetization **premium** with all
three toggles ON, and a `Charged` `CompetitionPremiumCharge` per non-owner member — so this
is the „premium is on for everyone" side of the Premium XOR boosts rule. Two matches played,
four still ahead (rounds „1. kolo"…„3. kolo"), only half the group has tipped the next one.
**Use this one for organizer/member-management states and on-behalf tipping.**

### World C — „Zimní pohár – parta" · the finished competition
`WINTER_COMPETITION_ID` = `019bbbbb-0000-7000-8000-0000000000f4`

Curated source **Zimní pohár 2026** (`WINTER_SOURCE_ID`), **completed** ~24 days ago; all
four matches (Semifinále / O 3. místo / Finále) have final results. Eight members, and the
organizer already broke the 24 b tie at the top by hand — the only seeded
`LeaderboardTieResolution` in the whole app (`kaja` → rank 1, `tipovac` → rank 2).
Monetization **boosts** on purpose, so „a boost can no longer be bought once the
competition is over" has a subject. **Use this one for the „Ukončeno" states.**

### World D — „Fandíme Česku" · the team filter
`TEAM_FILTER_COMPETITION_ID` = `019bbbbb-0000-7000-8000-0000000000f2`

The **same** World-Cup source seen through selection mode **`teams`**, pinned to
**Česko** (`TEAM_CESKO_ID`) + **Slovensko** (`TEAM_SLOVENSKO_ID`). Only the four
Czech/Slovak fixtures are in — three played plus the Osmifinále one that auto-joined by the
playoff-always-in rule — so „why is this match not in that competition" can be reproduced
deliberately. Six members, monetization **boosts**, and **nobody owns a boost**: this is the
canonical **locked** „Rozložení tipů" of the dev world (the dev user can afford it, so the
strip renders the buy trigger, not the „chybí kredity" variant).

## World A in detail

### Teams (global directory, football)

Non-European nations on purpose — the Euro 2024 dev source already owns the European names
in the shared directory, and a directory team name is unique per sport.

Česko · Slovensko · Brazílie · Argentina · Mexiko · Kanada · Uruguay · Japonsko · Maroko ·
USA · Senegal · Korea — all with a short name, country and brand color, UUIDs
`019ddddd-0000-7000-8000-0000000ff001`…`…ff00c` in that order.

### Matches (`019ddddd-0000-7000-8000-0000000fa001`…`…fa012`)

| # | Round | Match | Kickoff | State |
|---|---|---|---|---|
| 0 | `ROUND_GROUP_1` — Základní skupina – 1. kolo | Česko – Slovensko | today −14 | **2:1** |
| 1 | `ROUND_GROUP_1` | Brazílie – Mexiko | today −13 | **1:1** |
| 2 | `ROUND_GROUP_1` | Argentina – Kanada | today −13 | **3:0** |
| 3 | `ROUND_GROUP_2` — Základní skupina – 2. kolo | Česko – Brazílie | today −9 | **0:2** |
| 4 | `ROUND_GROUP_2` | Mexiko – Argentina | today −8 | **2:2** |
| 5 | `ROUND_GROUP_2` | Uruguay – Japonsko | today −8 | **1:0** |
| 6 | `ROUND_GROUP_3` — Základní skupina – 3. kolo | Česko – Maroko | today −3 | **1:1** |
| 7 | `ROUND_GROUP_3` | USA – Senegal | today −2 | **2:0** |
| 8 | `ROUND_GROUP_3` | Korea – Uruguay | now −1 h | **live** |
| 9 | `ROUND_LAST_16` — Osmifinále | Česko – Brazílie | today +2 | scheduled, **playoff** |
| 10 | `ROUND_LAST_16` | Argentina – Mexiko | today +3 | scheduled, **playoff** |
| 11 | `ROUND_QUARTER_FINAL` — Čtvrtfinále | Uruguay – Maroko | today +7 | scheduled, **playoff** |

Because the live match (#8) is the latest kicked-off labelled match,
`CompetitionRoundResolver::currentRound()` → **„Základní skupina – 3. kolo"**, which has two
evaluated matches — so `LeaderboardTimeFilter::LastRound` („Poslední kolo") and
`GetCompetitionCurrentRound` both return real data. „Posledních 7 dní" covers matches #6–#8.

Tips on the open fixtures thin out on purpose (24 / 18 / 12 / 6 tippers for #8 / #9 / #10 /
#11), so every „Rozložení tipů" bar has a different shape.

### Standings and how they are built

Each member gets a **plan string** — one character per finished match — which is rotated by
the member's position so *which* matches they hit differs too:

| code | meaning | points (default rules) |
|---|---|---|
| `e` | exact hit | 10 (5 + 3 + 1 + 1) |
| `o` | right outcome only | 3 |
| `h` | right home goals only | 1 |
| `m` | complete miss | 0 |

That makes a plan string readable as a point total, and lets the leaderboard be *designed*:

| Rank | Member | Points | | Rank | Member | Points |
|---|---|---|---|---|---|---|
| 1 | `martas` | 47 | | 13 | `mara` | 22 |
| 2 | `katka` | 44 | | 14 | `filda` | 21 |
| 3 | `admin` | 41 | | 15 | `ondra` | 19 |
| 4 | `petros` | 38 | | 16 | `dejv` | 18 |
| 5 | `kuba` | 36 | | 17 | `kaja` | 17 |
| 6 | `lucka` | 34 | | 18 | `zdenekb` | 15 |
| **7** | **`tipovac`** | **32** | | 19 | `romca` | 14 |
| **7** | **`honza`** | **32** | | 20 | `vojta` | 12 |
| 9 | `janicka` | 29 | | 21 | `adamr` | 11 |
| 10 | `tomas_p` | 27 | | 22 | `evinka` | 9 |
| 11 | `lukas_h` | 25 | | 23 | `terka` | 7 |
| 12 | `pavlik` | 24 | | 24 | `bara` | 4 |

### Leaderboard snapshots

Three honest partial-sum standings of World A: **today −10** (after round 1), **today −4**
(after round 2) and **today** (current). Δ compares today's board with the latest day
*strictly before* today, i.e. the today −4 one — so the leaderboard shows the real round-3
reshuffle (currently 8 climbers, 14 fallers) and the member „Vývoj" list never exceeds the
live „Celkem bodů". The older `VŠCHT tipovačka` snapshot (2025-06-09, see above) still exists.

## What each page looks like with this data

**Item 04 — the `SoutezSwitcher` grouped picker.** `tipovac` is in **six** competitions
across **five** sources, two of them as organizer and one already finished — enough to
exercise grouping („moje soutěže" vs „organizuji"), a „Ukončeno" badge inside the dropdown,
and a list long enough that a search box earns its place.

**Item 05 — Žebříček.** Open World A's board: a 24-row table with a podium, a highlighted
own row at rank 7 that is part of a **tie**, a long tail to collapse, non-trivial accuracy
percentages and streaks, live Δ arrows, and all three period tabs populated (Celkem /
Poslední kolo / Posledních 7 dní). World C is the same page in its final, frozen,
tie-resolved state; World B is the three-row-ish small board.

**Item 06 — Nástěnka hráče.** The primary dev user has a competition worth putting in focus
(World A: mid-table, a live match right now, three fixtures ahead), plus played *and*
upcoming matches in three other competitions, one unread notification, a 35 kr. balance,
and both a locked (World D) and an unlocked (World A / B) „Rozložení tipů" on the
cross-competition match feed.

**Item 07 — Soutěže.** `/souteze` lists three global competitions (World A plus the two
AppFixtures ones) with an entry fee to render; the logged-in variant additionally has
**organized** (World B, `Kámoši u piva`, `VŠCHT tipovačka`), **joined** (World A, World D,
World C), **private** vs **global**, **live** vs **finished**, premium vs boosts vs none —
every filter the page will offer has at least two rows on both sides.

## Gotchas worth knowing

- **Shareable link tokens must be 48 chars of `[a-f0-9]`** — that is what the
  `competition_join_by_link` route requires, and a token outside it makes the competition
  detail page 500 when it renders the invite link. Dev tokens are therefore `str_repeat()`
  of a hex character (`e`, `1`, `2`, `d`, `3`, `4`, `c`, `b`, `f`).
- **Global directory team names are unique per sport.** Adding a curated-source match with a
  team name another dev source already uses is a unique-constraint violation, not a reuse.
- Dev PINs: `20240701`, `10000001`, `10000002`, `20000001`, `20000002`, `30000001`,
  `40000001` (World D), `40000002` (World B), `40000003` (World C).
- **After `composer db:reset`, run `docker compose restart web`.** `db:reset` DROPs the
  database, and the FrankenPHP workers keep their old connections — every page then 500s with
  *„no connection to the server"* until the container is restarted. Nothing is wrong with the
  data. (If the drop itself fails with *„database is being accessed by other users"*, restart
  `web` first and re-run.)
