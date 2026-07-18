# S02 — Source kinds & competition–match linkage

**Goal**: replace public/private visibility with `MatchSourceKind`, introduce match
selection (`all | subset`), playoff flagging, and re-scope creation/discovery flows to the
new semantics. After this stage the *semantic* model matches DOMAIN.md; wizard UX comes in S08.

## Domain changes

- `Enum\MatchSourceKind { Curated = 'curated', Private = 'private' }` replaces
  `MatchSourceVisibility`. Data migration: `public`→`curated`, `private`→`private`.
- `MatchSource`: drop `creationPin` entirely (column, forms, gate logic in
  CreateCompetition controller, voter checks). Joining/creating rules now:
  - Curated sources: any verified user may create a competition from them (S08 wizard;
    interim: existing create-competition page gains a source selector limited to curated
    sources + own private sources).
  - Private sources: invisible; 1:1 with their competition (legacy N:1 data tolerated).
- `SportMatch`: add `bool $isPlayoff` (default false). Editable in match create/edit forms
  and CSV/XLSX import (new optional column `Playoff (ano/ne, nepovinné)`); admin match list
  and match rows show a „Playoff" pill.
- `Competition`: add `selectionMode: CompetitionMatchSelectionMode { All='all', Subset='subset' }`
  (default All) and `bool $includePlayoff` (default true).
- New entity `CompetitionMatchSelection` — `id`, `competition` FK, `sportMatch` FK,
  `addedAt`; unique `(competition_id, sport_match_id)`. Only used when mode = Subset.
- New domain service **`CompetitionMatchProvider`** — THE single authority answering
  "which matches belong to competition C" (and "does match M belong to C"):
  mode All ⇒ source matches, minus playoff when `includePlayoff = false`, minus deleted;
  mode Subset ⇒ selection rows (selection wins over includePlayoff — an explicitly selected
  playoff match counts). Refactor every query/handler that joins matches via the source
  (leaderboards, matrix, match lists, dashboards, guess authorization in Submit/Update
  handlers, pick distribution, member breakdown) to route through it (DQL fragments may be
  provided by the service for query builders).
- Events: `SportMatchCreated` in a source now interests competitions in mode All — add
  event payload `matchSourceId` + `isPlayoff` (consumed by notifications in S11; no handler
  yet beyond keeping evaluation semantics correct).
- Guard: a `Guess` may only exist for a match that `CompetitionMatchProvider` includes —
  enforce in Submit/Update/OnBehalf handlers (replaces the current "match belongs to the
  group's tournament" check; use a proper new exception `MatchNotInCompetition` (409),
  not `NotAMember`).

## Flow/UX changes (interim, pre-wizard)

- Portal "create competition" page: add source select (curated + user's own private
  sources) — replaces the tournament-scoped route `/portal/turnaje/{id}/skupiny/novy` with
  `/portal/souteze/nova?zdroj={id}` (source preselectable). Also fix the known bug: the
  create form must honor `hideOthersTipsBeforeDeadline`/`tipsDeadline` (extend
  `CreateCompetitionCommand`).
- "Create private tournament" pages become „Vlastní zdroj zápasů" management under the
  competition (interim: keep the standalone page, relabeled, reachable from competition
  management only). `CreatePublicMatchSource` (admin) relabels to „Nový zdroj zápasů"
  and gains kind=Curated + sport select stays hardcoded football until S05.
- Public pages: `/turnaje` listing + detail relabel to „Zdroje zápasů" showing curated
  sources & their public join-request behavior **unchanged for now** (retired in S09).
- UI copy: „turnaj" → „zdroj zápasů" in portal/admin chrome (nav, breadcrumbs, headings,
  emails). Keep `SoutezSwitcher` etc. working.

## Tests

- Unit: `CompetitionMatchProvider` (all × subset × includePlayoff × deleted/playoff
  matches), `CompetitionMatchSelection` entity, `SportMatch::isPlayoff`, kind migration
  behavior via fixtures.
- Integration: submit-guess rejected for non-included match (`MatchNotInCompetition`),
  subset leaderboard only counts selected matches, importer parses the playoff column,
  create-competition honors tip settings (regression for the fixed bug).
- Update fixtures: `VERIFIED_COMPETITION` gets mode Subset example? — no: keep baseline
  simple (mode All); add one dedicated subset competition constant + selection rows.

## Acceptance

- [ ] `MatchSourceVisibility` gone; kind everywhere; creationPin fully removed.
- [ ] All match-listing surfaces (dashboard, /zapasy, leaderboard, matrix, detail pages,
      pick distribution) respect selection mode + includePlayoff.
- [ ] Quality gate green.
