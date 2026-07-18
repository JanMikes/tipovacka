# S04 — Rules per competition

**Goal**: move scoring configuration from the source to the competition. Each competition
owns its rule set; evaluation and recalculation become competition-scoped.

## Domain changes

- New entity `CompetitionRuleConfiguration` — `id`, `competition` FK, `ruleIdentifier`
  string(64), `enabled` bool, `points` int, `updatedAt`; unique
  `(competition_id, rule_identifier)`. Behavior methods as the old entity
  (enable/disable/updatePoints). Replaces `MatchSourceRuleConfiguration` (entity, table,
  provisioner, commands, queries, controllers, forms — delete the source-scoped ones).
- Provisioning: on `CompetitionCreated` → provision one row per registered rule with
  `defaultPoints`, enabled per-rule default (`Rule` gains
  `public bool $enabledByDefault { get; }` — the 4 base rules true; future optional rules
  false).
- Data migration: for every competition, copy its source's rule rows into
  `competition_rule_configurations`; then drop `match_source_rule_configurations`.
- `GuessEvaluator`: resolve config via `$guess->competition` (NOT via match→source).
  A match finishing now evaluates guesses **per competition config** — same match can
  yield different points in different competitions. `SportMatchFinishedHandler` /
  `ScoreUpdatedHandler` unchanged in trigger, but evaluation loops guesses and uses each
  guess's competition config (per-competition config cache within the handler run).
- Rule changes: `UpdateCompetitionRuleConfiguration` command (managerId, competitionId,
  changes) → records `CompetitionRulesChanged` on Competition → handler dispatches
  `RecalculateCompetitionPointsCommand` (async) which deletes + re-evaluates ONLY that
  competition's evaluations (new repository methods `deleteAllForCompetition`,
  `findActiveForFinishedMatchesInCompetition` via `CompetitionMatchProvider`).
  Delete the source-scoped recalculation command.
- Authorization: competition manager or admin edits rules (`CompetitionVoter::EDIT`);
  rules editable anytime (recalc confirm when evaluations exist — keep the
  `evaluationCount` UX, now per competition).

## UX changes

- Rules page moves under the competition: `/portal/souteze/{id}/pravidla` (reuse
  `Scoring:RuleFields`, presets, confirm-recalculation Stimulus — evaluationCount from the
  new per-competition query `GetCompetitionRuleConfiguration`).
- Remove the two source-scoped rule pages (portal + admin duplicates). Admin edits a
  global competition's rules through the same competition page (admin passes voter).
- Read-only rules partial (`_partials/…rules…`) shown on competition detail reads
  competition config.
- Kill the JS/PHP duplication drift while here: `scoring_preset_controller.js` reads
  preset values from `data-*` attributes rendered from PHP `defaultPoints` (single source
  of truth); `RuleFields` copy map keys stay but fall back to PHP labels.

## Tests

- Migrate/rename existing rule-config tests to competition scope (provisioner unit,
  update-handler integration incl. unknown-identifier defense + recalc trigger,
  query merge logic incl. the display-vs-evaluator mismatch: **fix it** — evaluator and
  query must agree: missing row = rule's `enabledByDefault` with `defaultPoints`, and the
  update handler persists missing rows on save; evaluator provisions nothing silently).
- New: same match finished, two competitions with different points ⇒ different totals
  (integration, the key semantic proof).
- Leaderboard/breakdown queries unaffected structurally (they aggregate evaluations) —
  keep green.

## Acceptance

- [ ] No `MatchSourceRuleConfiguration` anywhere; per-competition config drives evaluation.
- [ ] Different competitions on one source score independently (test proves it).
- [ ] Quality gate green.
