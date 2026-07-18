# S06 — Guess extensions & new scoring rules

**Goal**: the guess side of S05 — period tips, overtime tip, scorer tips, and the four new
rules evaluated per competition config.

## Domain changes

- `Guess`:
  - `?array $periodScores` (JSON via `Value\PeriodScores`, validated against the sport).
  - `?int $overtimeHomeScore` / `?int $overtimeAwayScore` — allowed only when the main tip
    is a draw (invariant in entity, `InvalidGuessScore` variants).
  - New entity `GuessScorer` — `id`, `guess` FK, `player` FK (`Player`), `createdAt`;
    unique `(guess_id, player_id)`; guessed scorers picked from the source's player pool
    (players may also free-type — creates a `Player` in the pool, same as organizer side).
    Cap: max 5 scorer tips per guess (constant next to the entity).
  - `updateScores`/constructor extended; submitting clears fields not enabled by the
    competition's rules (defense in the handler: reject payloads for disabled features
    with 422).
- Rule engine generalization:
  - `Rule::evaluate(Guess, SportMatch): int` return contract changes from binary to
    **multiplier ≥ 0**; `GuessEvaluator` awards `evaluate() × configuredPoints` and stores
    that product in `GuessEvaluationRulePoints` (keep only-hits-stored behavior:
    multiplier 0 ⇒ no row).
  - New rules (all `enabledByDefault = false`, provisioned disabled):
    - `scorer_hit` (defaultPoints 2) — count of guessed players with ≥1 goal `MatchEvent`
      in this match.
    - `period_exact` (defaultPoints 5) — count of periods where the tip matches exactly
      (only when both sides provided periods).
    - `period_tendency` (defaultPoints 2) — count of periods with correct 1/X/2 tendency
      but NOT exact (exclusive with period_exact per period).
    - `overtime_exact` (defaultPoints 3) — 1 when regular result was a draw, the user
      tipped that draw's OT continuation, and the OT final score matches exactly; else 0.
  - Registry order: define explicit `$priority`? No — keep discovery order; UI groups by
    a new `Rule::$category { get; }` (`'base' | 'periods' | 'scorers' | 'overtime'`) for
    sectioned rendering.
- Feature toggles = rule enablement (DOMAIN.md): the guess form shows period inputs iff
  `period_exact || period_tendency` enabled for the competition; scorer picker iff
  `scorer_hit`; OT input iff `overtime_exact` and the current tip is a draw (live-reactive).

## UX

- `Guess:GuessSubmitForm` live component: period inputs (labeled per sport), draw ⇒ OT
  inputs appear reactively, scorer tom-select (multi, max 5, autocomplete + create).
  Batch pages (`moje-tipy`, manage-member-tips) get period inputs only (scorers/OT only on
  detail — keep batch simple, note „Střelce a prodloužení tipnete v detailu zápasu").
- Rule presets: „Standard + střelec" tile becomes real (base rules + scorer_hit enabled);
  preset tiles read values from data attributes (done in S04).
- Member breakdown + match detail show per-rule points with **Czech labels** (fix the raw
  identifier leak in `member.html.twig`), including multiplied rules („2× trefený střelec").
- Pick distribution/others' tips views show scorer tips + period tips where visible.

## Tests

- Unit per new rule (edge matrix: no data, partial data, draw/no-draw, multiplier counts),
  `GuessScorer`/entity invariants, evaluator multiplier math.
- Integration: finish match with scorers+periods+OT ⇒ evaluation totals across two
  competitions with different configs; disabled-feature payload rejected; guess form flow
  (draw reveals OT input — assert via component re-render), scorer autocomplete endpoint.
- Regression: base 1+1+3+5=10 exact-hit total unchanged for default competitions.

## Acceptance

- [ ] All four new rules evaluate correctly and only when enabled per competition.
- [ ] Guess UX adapts to competition config + sport; no dead inputs anywhere.
- [ ] Quality gate green.
