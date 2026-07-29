# Item 03 — Development fixtures: a realistic, complete world

**Status:** DONE
**Depends on:** item 02 (so fixtures can set a round on matches)
**Blocks:** nothing, but items 04–07 are far easier to build and verify with this data in place

---

## Why

The product owner: *„We might need fixtures for better development — global competition, private
competition, few competitors (users), e.g. closed competition, playoff — you come up with some and
describe them in a markdown file and reference it in CLAUDE.md so any Claude can access it."*

Today `db:reset` produces data that is too thin to exercise the pages being rebuilt in items 04–07: a
leaderboard needs enough players to have a podium, a mid-table and a „…" gap; the Nástěnka needs a
user who is in several competitions at once with played *and* upcoming matches; the Soutěže page
needs both organized and joined competitions, public and private, live and finished.

## Important

**`.docs/FIXTURES.md` already exists and is already referenced from `CLAUDE.md`.** Extend that file —
do not create a second fixtures doc and do not add a second `CLAUDE.md` reference. Keep its existing
structure and reference-constant style so tests that rely on it keep working.

**Do not break existing tests.** A large number of integration tests assert against current fixture
data. Adding new worlds is safe; renaming or renumbering existing ones is not. If you must change an
existing fixture, update every test that depends on it and say so.

`DataFixtures` is the one place the project allows `flush()`.

## Scope — the worlds to build

Design the specifics yourself; these are the requirements each world must satisfy. Give every entity
stable, human-readable Czech names, and use `PredictableIdentityProvider`-style deterministic UUIDs so
the doc can quote them as constants.

1. **Global (public) competition, live** — curated `MatchSource`, `isGlobal`, an entry fee in credits,
   enough members to fill a leaderboard properly (see below), some matches played with results, some
   upcoming, and a **playoff phase** so the playoff-always-included rule is exercised. Give matches a
   round/kolo (item 02) across at least three distinct rounds so the „Poslední kolo" tab has something
   to show.

2. **Private competition, live** — a `private` source created from scratch, small (5–8 members),
   organized by the primary dev user. Include at least one **anonymous member** (no e-mail) and one
   pending invitation, since those states have their own UI.

3. **Closed / finished competition** — every match has a final result, source completed. This is what
   B6 (no boost purchase once it is over) and the „Ukončeno" states need.

4. **Competition with a team filter** — match scope mode `teams`, so `CompetitionMatchProvider`'s
   dynamic mode is represented and B4-style „why is this match not in that competition" situations can
   be reproduced deliberately.

5. **Monetization coverage** — across the worlds, cover both sides of the Premium XOR boosts rule: at
   least one competition with premium on for everyone, and one on boosts where the dev user has
   **bought** a boost and another member has not. This is what drives the „Rozložení tipů" locked vs
   unlocked surfaces on every match listing.

### Members and scoring

The leaderboard pages need a populated table, not three rows:
- **~20+ members** in the global competition, with genuinely varied point totals so rank order,
  accuracy %, exact-hit counts and streaks all differ. The design shows a podium, a highlighted own
  row around 7th, and a „… pozice 13–24 …" gap — the data must make all three real.
- The **primary dev user sits mid-table** (not 1st) in at least one competition, so „Tvoje pozice",
  „do TOP 5 +18 b" and the highlighted own row are visible during development.
- Enough evaluated guesses that accuracy percentages are not all 0 % or 100 %.
- At least one **tie** on points, so `LeaderboardTieResolution` has a reason to exist.

### Credits

The dev user needs a credit balance large enough to buy a boost and small enough that the
„insufficient credits" branch can be reached by buying one or two things. Prices come from
`Credits/PricingConfig` — never hard-code an amount.

## Documentation

Extend `.docs/FIXTURES.md` with:
- a short **map of the worlds** — one paragraph each, naming what it is for („use this one to work on
  the leaderboard", „use this one for the finished-competition states"),
- the **login credentials** of the dev users and which competitions each belongs to,
- **reference constants** (UUIDs / names) in the same style the file already uses,
- a **„what each page will look like with this data"** note for the four pages in items 04–07.

## Acceptance criteria

1. `docker compose exec web composer db:reset` completes cleanly from an empty database.
2. Logging in as the primary dev user shows: several competitions, a mid-table leaderboard position,
   played and upcoming matches, and at least one locked and one unlocked „Rozložení tipů".
3. The global competition's leaderboard has enough rows to show a podium **and** a gap.
4. Every existing integration test still passes.
5. `.docs/FIXTURES.md` documents everything above; `CLAUDE.md`'s existing reference still resolves.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → run the integration suite in subdirectory chunks
(never `phpunit tests/` whole — it OOMs at exit 137) and confirm nothing regressed. Then `db:reset`
and click through `/nastenka`, `/souteze`, a competition detail and a leaderboard to confirm the data
looks right. Update the status board row to DONE + sha. Commit `UI: development fixtures for the
rebuilt pages`, push to `main`.

---

## Assumptions made

1. **Everything landed in `DevFixtures`, nothing in `AppFixtures`.** The item asks for ~20+
   members, several new competitions and a populated ledger; adding any of that to the shared
   *test* baseline would break the many integration tests that assert exact counts over whole
   tables (memberships, competitions, `credit_transactions`). `DevFixtures` is group `dev` only
   and never loaded by `tests/bootstrap.php`, so the new worlds are invisible to the suite —
   which is also why no existing fixture entity had to be renamed or renumbered.
2. **The new worlds are anchored to the REAL calendar (`today ± n days`), not to the
   `2025-06-15` MockClock instant.** Those two anchors cannot both hold, and since `DevFixtures`
   never runs under MockClock, the real calendar is what makes a `db:reset` useful: „Posledních
   7 dní" and „Poslední kolo" always have data and the upcoming fixtures are genuinely
   upcoming. The pre-existing `DevFixtures` data was already doing this for its scheduled
   matches; item 03 just makes it consistent. Documented in `.docs/FIXTURES.md`.
3. **The global competition's entry fee (30 kr.) is a `DevFixtures` constant, not a
   `PricingConfig` one.** `PricingConfig` is the home of *prices the product charges* (premium
   per player, the three boosts); an entry fee is per-competition data an admin types in — the
   same call `AppFixtures::GLOBAL_COMPETITION_ENTRY_FEE` already makes. Every boost/premium
   amount in the new fixtures does come from `PricingConfig`, including the dev user's target
   balance (`DEV_USER_CREDIT_BALANCE`).
4. **The one deliberately unverified dev user is a member of nothing.** The airlock does not
   need data behind it, and a member with no tips would have added a meaningless 0-point row to
   a leaderboard the item wants realistic.
5. **A global competition monetized as `boosts` is a legitimate state** (`Competition::
   updateGlobalSettings()` and `CreateGlobalCompetitionCommand` both take a monetization), so
   World A is global *and* boosts — that is what gives the primary dev user an unlocked
   „Rozložení tipů" without inventing a premium competition nobody manages.

## Bugs the new data exposed (fixed in the same commit)

- **`CompetitionTeamFilterRepository::teamViewsFor()` threw on every Teams-mode competition.**
  Its DQL was `SELECT t FROM CompetitionTeamFilter f JOIN f.team t` — Doctrine cannot hydrate a
  joined entity when the root entity is not selected, so the competition detail page and the
  match-selection page 500'd with *„Cannot select entity through identification variables
  without choosing at least one root entity alias"*. No fixture had ever had a `teams`-mode
  competition, so nothing caught it. Rewritten with `Team` as the root alias.
- **Several dev shareable-link tokens were not hex**, so `competition_join_by_link`
  (`[a-f0-9]{48}`) refused to generate the invite URL and the detail page of e.g. „VŠCHT
  tipovačka" 500'd. All dev tokens are hex now, with a note on the fixture helper.
