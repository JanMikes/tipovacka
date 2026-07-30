# Item 33 — `GetGuessesForMatchInCompetition` has no consumer left

**Status:** TODO
**Filed:** 2026-07-30, by the item 22 implementer, as a consequence of its own change (`876e674`).

## What happened

Item 22 found that the match page's two „other members' tips" blocks — „Pořadí za zápas"
(`GetMatchRanking`) and „Jak tipovali ostatní" (`Guess:MatchGuessesList` →
`GetGuessesForMatchInCompetition`) — returned **the same rows**: every active guess for the same
(soutěž, match) pair, behind the same `TipVisibilityGate` decision. Item 10's own assumption 3 had
already half-admitted it: while a match was unscored, „Pořadí za zápas" *retitled itself* „Jak
tipovali ostatní". They were one block wearing two names.

So item 22 folded them into one. The only content unique to the deleted block — the optional tip
detail (period scores, prodloužení, střelci) — moved onto `MatchRankingRow` (fetch-joined, no N+1),
and `Guess:MatchGuessesList` (component + template) is gone.

**`GetGuessesForMatchInCompetition` is now orphaned**: message, handler and result DTO with no
production call site. Item 22 deliberately did not delete it, because `.docs/DOMAIN.md` names it as a
`TipVisibilityGate` consumer and DOMAIN.md was another agent's file that round — deleting the code
would have left that line wrong with no way to fix it.

## What to do

**Delete it, and fix the documentation in the same commit.** `PLAN.md`'s standing convention is to
prefer the clean end-state — *„no backwards compatibility is owed, there are no users yet"* — and
this query was not deferred or reserved for later: it was **superseded**, its job absorbed by
`GetMatchRanking`. That is the difference from `GetCompetitionsPageStats` (item 24) and
`Competition:FilterBar` (item 15), which were kept **because the product owner intends to reuse
them**. Nothing intends to reuse this one.

1. **Delete `src/Query/GetGuessesForMatchInCompetition/`** — message, `…Query` handler and result DTO.
2. **`grep` the route name and the class names first** and fix every remaining reference in the same
   commit — `PLAN.md`'s replacing constraint is that nothing inside the app may 404 or 500.
3. **`.docs/DOMAIN.md`** names it as a `TipVisibilityGate` consumer. Correct that sentence so it lists
   the consumers that actually exist. Do not rewrite history — if there is a decision-log row that
   mentions it, leave the row and let the prose above it be current.
4. **`tests/Integration/…/BoostTipVisibilityTest`** still passes but now guards nothing user-facing.
   Read it: if every case it covers is also covered against `GetMatchRanking`, delete it with the
   query; if it covers a gate behaviour nothing else pins, **re-point those cases at
   `GetMatchRanking`** rather than dropping the coverage. Say which you did and why — the
   entitlement gate is a paid feature and losing its coverage silently is the bad outcome here.
5. Check `UI-MAP.md` and `.docs/features/*.md` for mentions.

**If you find a live consumer** the item 22 implementer missed, stop: do not delete, report it, and
say where it is.

## What must NOT change

- **`TipVisibilityGate` itself**, and the rule it enforces: a viewer sees others' tips iff entitled
  **or** the match has a result. **Managers and admins get no free pass** (`CompetitionEntitlements`).
- `GetMatchRanking` and the merged block item 22 just shipped.
- Czech in the UI, English in code, identifiers and comments. No „sázka" in any form.

## Acceptance criteria

1. `src/Query/GetGuessesForMatchInCompetition/` is gone and nothing references it.
2. `.docs/DOMAIN.md` no longer names a query that does not exist.
3. The entitlement-gate coverage that test provided still exists somewhere — or you have stated,
   case by case, why it was redundant.
4. `composer quality` clean; the match page still renders for entitled and unentitled viewers.

## Verification

`PLAN.md`'s Definition of Done, plus:

- Load `/souteze/{id}/zapasy/{matchId}` as an entitled viewer and an unentitled one, before and after.
  `composer quality` does not catch a Twig error.
- `docker compose exec web vendor/bin/phpunit tests/Integration/Query tests/Integration/Portal`.
  **Never run `phpunit tests/` whole — it OOMs (exit 137).** Strip ANSI before grepping.
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`, and never a tree-wide `git restore` / `checkout .` / `stash`. Push to `main`.
Do not update the status board; report your sha.
