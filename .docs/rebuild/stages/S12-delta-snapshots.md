# S12 — Leaderboard delta & snapshots

**Goal**: rank-movement (Δ) on leaderboards, powered by daily snapshots.

## Domain

- Entity `LeaderboardSnapshot` — `id`, `competition` FK, `user` FK, `day` DATE (Prague
  day), `points` int, `rank` int, `createdAt`; unique `(competition_id, user_id, day)`.
- `CaptureLeaderboardSnapshotsCommand(competitionId, day)` — upserts a full snapshot of
  the current leaderboard (reuses `GetCompetitionLeaderboard` results incl. tie overrides).
  Idempotent per (competition, day) — re-running replaces the day's rows.
- Triggers:
  - Scheduler daily 03:00 Europe/Prague: snapshot every competition that had ≥1 evaluation
    since its last snapshot (cheap existence query).
  - On `competition_ended` detection (S11): immediate final snapshot.
  - After a `RecalculateCompetitionPoints` run: re-capture TODAY's snapshot (history stays).
- Delta semantics: leaderboard query returns `delta = previousRank − currentRank`
  (positive = climbed) + `deltaIsNew` for members without a previous snapshot, where
  `previousRank` comes from the **latest snapshot day strictly before today** (Prague).
  No snapshot history yet ⇒ delta null (render dot, styleguide §D).

## UX

- Leaderboard table: Δ column (chevron-up green / chevron-down red / minus dot; classes
  `.lb-delta-up/.lb-delta-down` already exist) — full table + mini dashboard leaderboard
  (`+9`-style chips per screenshot 2) + „Tvoje pozice" you-strip gets „{±n} od včera".
- Member breakdown: small „Vývoj" line — list of snapshot days with rank/points (simple
  table; charts out of scope).
- Time filters (Celkem / Poslední kolo / Týden / Měsíc from screenshot 13): implement
  **Celkem + Posledních 7 dní** only (points earned in window computed from evaluations
  by match kickoff date); other windows follow the same mechanism later — keep the filter
  UI extensible but only render implemented options.

## Tests

- Snapshot command: idempotent upsert, tie-override ranks captured, day boundary (Prague
  vs UTC — evaluation at 23:30 Prague lands on the right day).
- Delta query: climb/fall/new-member/no-history matrix; recalculation rewrites today only.
- Flow: leaderboard renders Δ; dashboard chips.
- Scheduler task registered (assert schedule contains the task).

## Acceptance

- [ ] Δ visible on all leaderboard surfaces with correct daily semantics.
- [ ] Snapshots idempotent, timezone-correct, capture-on-end wired.
- [ ] Quality gate green.
