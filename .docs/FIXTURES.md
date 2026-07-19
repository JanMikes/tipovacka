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
