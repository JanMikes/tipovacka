# S05 — Sports & match result model (periods, overtime, events, roster, live)

**Goal**: the result side — richer sports, richer match results, organizer score-entry UX
per the reference screenshots. Guess side follows in S06.

## Domain changes

- `Sport` — add config columns: `periodCount` int (football 2, hockey 3),
  `periodLabelSingular` / `periodLabelPlural` (poločas/poločasy, třetina/třetiny). Seed
  hockey (`Sport::HOCKEY_ID` const, migration INSERT). Sport select appears in:
  admin curated-source creation, from-scratch source creation (interim page; wizard S08).
  Remove the `getByCode('football')` hardcodes — sport comes from the form.
- `SportMatch`:
  - `?array $periodScores` (JSON, nullable) — list of `[home, away]` pairs, length must
    equal the sport's periodCount when set; wrapped by value object
    `Value\PeriodScores` (finally populate `src/Value/`) with validation + named
    constructors. Partial results allowed only complete-per-period.
  - `?int $overtimeHomeScore` / `?int $overtimeAwayScore` — final score **after**
    prolongation/shootout; settable only when the regular score is a draw; regular score
    remains the primary result (base rules evaluate regular time only).
  - `updateLiveScore(home, away, ?periodScores, now)` — allowed in Live state (and
    transitions Scheduled→Live implicitly); records `SportMatchLiveScoreChanged`
    (no evaluation trigger). Revives the orphaned Live state properly.
  - `setFinalScore(...)` extended: accepts periodScores + overtime scores + validates
    consistency (period sum == final score for football; for hockey OT: regular = sum of
    thirds, draw ⇒ OT score required if provided). Keep first-finish vs score-correction
    event split.
- New entity `MatchEvent` — `id`, `sportMatch` FK, `type: MatchEventType
  { Goal='goal', YellowCard='yellow_card', RedCard='red_card' }`, `side: MatchSide
  { Home='home', Away='away' }`, `?int minute`, `player` FK (see below), `createdAt`.
  Goals are the scorer source of truth for the `scorer_hit` rule (S06); cards feed the
  timeline + future fantasy. Consistency: goal-event count per side SHOULD equal the score
  but is NOT enforced (organizers may skip scorers) — warn in UI only.
- New entity `Player` — `id`, `matchSource` FK, `teamName` string(120), `name` string(120),
  `createdAt`; unique `(match_source_id, team_name, name)`. The per-source roster pool.
  Created implicitly when an organizer types a new scorer name (autocomplete offers
  existing pool of the match's team). No standalone roster CRUD in v1.
- Import: no changes (scores never imported).

## Score entry UX (organizer modal — screenshot "Zapsat výsledek")

Rework `/portal/zapasy/{id}/skore` into the reference layout (page, not modal, is fine):
- Score steppers; state toggle **Probíhá / Ukončený** (Probíhá saves via
  `UpdateLiveScoreCommand`; Ukončený via `SetSportMatchFinalScoreCommand`).
- Scorers section: rows (team select home/away, minute stepper, name input w/ tom-select
  autocomplete against `Player` pool + free-create), „+ Gól {team}" buttons; card rows
  addable via a secondary control. Persisted as MatchEvents (full replace per save).
- Period scores inputs (labeled per sport: Poločasy/Třetiny) — shown when source sport has
  them; optional.
- Overtime inputs appear only when the entered regular score is a draw (Stimulus).
- **„Toto byl poslední zápas soutěže/zdroje" checkbox** when finishing — calls
  `MatchSource::markCompleted(now)` (records `MatchSourceCompleted`). Uncheckable later
  via source management (reopen). This powers competition-end detection (S11).
- Match detail gets the „Průběh zápasu" timeline block (events desc by minute, dot color
  per type) — visible to everyone once events exist.

## Tests

- Unit: `PeriodScores` VO validation, `SportMatch` new invariants (OT only on draw,
  period/sum consistency, live-score state machine, markCompleted), `MatchEvent`/`Player`
  entities.
- Integration: set-final-score with periods+OT+scorers persists all (incl. player pool
  auto-creation + reuse on name match), live update does NOT evaluate, finishing does,
  score-entry flow test (WebTestCase, Czech labels), hockey source end-to-end (3 thirds).
- Fixtures: add hockey sport; extend finished fixture match with events + periods.

## Acceptance

- [ ] Organizer can record: live score, periods, OT (draw only), scorers/cards, last-match
      flag; timeline renders; hockey works with 3 thirds.
- [ ] Base scoring (S04) unaffected: regular-time score still drives the 4 base rules.
- [ ] Quality gate green.
