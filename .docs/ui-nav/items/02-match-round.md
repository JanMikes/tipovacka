# Item 02 — „Kolo" (round) on a match: optional input, import support, grouping

**Status:** DONE
**Depends on:** nothing
**Blocks:** item 04 (Žebříček — the „Poslední kolo" tab and the „KOLO: Osmifinále" hero stat)

---

## Why

The Žebříček design (see `.docs/ui-nav/screenshots/img05-zebricek-a.png`) needs a round concept in
two places: the hero stat **„KOLO · Osmifinále"**, and the period tab **„Poslední kolo"** next to
Celkem / Týden / Měsíc. The product owner decided round 1 ships **all four tabs**, which makes this
domain change a prerequisite.

The product owner's words: *„add round optional input and for the import too."*

## Step 0 — INVESTIGATE FIRST, then implement

**Do not assume the field is missing.** There is strong evidence a stage/round concept already exists
in some form:

- The create-wizard match picker groups matches under headings **„1. KOLO"** and **„ZÁKLADNÍ
  SKUPINA"** (`.docs/ui-nav/screenshots/…` — the wizard step-1 screenshot in the session; see
  `Competition:CreateWizard` / `wizard_matches_controller.js`).
- `assets/styles/app.css` has `.tip-row-when .round` and `.tip-stage` classes.
- `templates/portal/competition/detail.html.twig` renders a stage/round-ish label.
- The `SportMatch` entity may already carry a stage/group/round property, and the importer may
  already parse one.

So: inventory `src/Entity/SportMatch.php`, the import command/parser
(`portal_sport_match_import` → `templates/portal/sport_match/import.html.twig` and its handler), the
match create/edit form (`src/Form/`, `portal_sport_match_create|edit`), and whatever feeds those
wizard group headings. **Write down what exists before changing anything**, and put that finding in
the commit message. If a suitable field already exists, this item becomes „expose + import + group by
it", not „add a column".

## Scope

### A. The field

An **optional** free-text round/stage label on `SportMatch` (examples from real data: „1. kolo",
„Základní skupina", „Osmifinále", „Kolo 34"). Nullable — matches without a round are normal and must
keep working everywhere.

Follow the project's entity conventions exactly: `private(set)` / `public private(set)`, no setters —
a behaviour method, no trivial accessors. If a new column is genuinely needed, **generate** the
migration with `doctrine:migrations:diff` and commit what it produces — never hand-write it.

### B. Match forms

Expose it as an optional input on match create and edit
(`portal_sport_match_create`, `portal_sport_match_edit`, `templates/portal/sport_match/form.html.twig`).
Czech label „Kolo", with a hint naming a couple of the example values above. Free text, not an enum —
the vocabulary differs per sport and per source (kolo / skupina / fáze play-off).

### C. Import

The importer must accept the round per match too. Match the importer's existing input format rather
than inventing a new one, and keep it optional so every existing import string still parses. Update
whatever in-app help/placeholder documents the import format, and cover it with a test — including a
row **without** a round.

### D. Grouping / display

Where matches are listed grouped (the wizard match picker at minimum), group by this field. Where a
single match is shown, render the round in the existing `.tip-row-when .round` / `.tip-stage` slot.
Matches with no round must not produce an „(empty)" group heading — fold them into a sensible default
bucket or leave them ungrouped.

### E. What item 04 will need from you

Leave the read side usable without further domain work:
- a way to ask **„what is the current/latest round of this competition"** (for the hero „KOLO" stat),
- a way to scope leaderboard scoring to **the most recent round** (for the „Poslední kolo" tab).

Add these as query/service capability in the natural place. Do **not** build the Žebříček page or its
tabs — that is item 04. Just make sure the data is reachable and say in the item's „What landed"
section exactly how item 04 should call it.

The Týden / Měsíc tabs are time-window based and do **not** depend on this item.

## Explicitly out of scope

- The Žebříček page itself, its tab strip, or any template in `templates/portal/leaderboard/`.
- Any change to how points are calculated. Round only *slices* existing scoring; it must not alter
  totals on the „Celkem" view.
- Backfilling rounds onto existing production data.

## Acceptance criteria

1. A match can be created and edited with, and without, a round; both round-trip correctly.
2. An import string with rounds imports them; an import string without rounds still imports cleanly.
3. Grouped match lists group by round; matches with no round do not create an empty heading.
4. „Celkem" leaderboard totals are byte-for-byte unchanged by this item (guard with a test).
5. `schema:validate` is clean and `migrations-up-to-date` passes.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → `tests/Integration/{Command,Query,Console,Portal}`
chunks (never `phpunit tests/` whole — it OOMs). Plus render checks on the match form, the import
page and the wizard match picker. Update `UI-MAP.md` if any route/template changes. Update the status
board row to DONE + sha. Commit `UI: optional round (kolo) on matches + import`, push to `main`.

---

## Step 0 finding — the round already existed

**`SportMatch::$round` was already there** (`?string`, `length: 120, nullable: true`), added by commit
`5595def Feature: SportMatch.round / stage label`, migration `Version20260718222245`. Consequently
**no entity change and no migration were needed** — `doctrine:migrations:diff` reports
*„No changes detected"* and `doctrine:schema:validate` is clean on both mapping and database.

Inventory of what was already implemented:

| Scope | State before this item |
|---|---|
| **A. The field** | ✅ `SportMatch::$round`, `public private(set) ?string`; set via the constructor and `updateDetails()` (replaced wholesale, like `$venue`). Unit-covered in `tests/Unit/Entity/SportMatchEntityTest.php`. |
| **B. Match forms** | ✅ `SportMatchFormData::$round` + `SportMatchFormType` (`TextType`, `required: false`, label „Kolo / fáze", help „Nepovinné. Např. „Skupina A", „Čtvrtfinále"."), rendered by `templates/portal/sport_match/form.html.twig`; both `CreateSportMatchController` and `UpdateSportMatchController` pass `$formData->round ?: null`. |
| **C. Import** | ✅ `SportMatchImporter::COLUMN_ROUND = 'Kolo (nepovinné)'` — an **optional** header (`mapHeader()` only maps it if present, so pre-round CSVs still parse), 120-char validation, carried on `SportMatchImportRow`, written in `commit()`, present in `generateTemplateCsv()`, shown in the preview table and documented in `templates/portal/sport_match/import.html.twig`. |
| **D. Grouping** | ✅ `CreateWizard::$groupedMatches` and `CompetitionMatchSelectionController` both group by `$match->round ?? <Prague kickoff date>` — an unlabelled match falls into a date bucket, so there is **never** an „(empty)" heading. `Match/MatchRow` renders `<span class="round">`; `portal/guess/detail.html.twig` renders `.tip-stage .round`. |
| **E. Read side for item 04** | ❌ **Nothing existed.** No way to ask for a competition's current round; `LeaderboardTimeFilter` had only `celkem` / `7dni`. |

So this item was *„expose + read side"*, not *„add a column"* — exactly the possibility Step 0 flagged.

## What landed

### New — the read side item 04 needs (scope E)

**1. „What round is this competition in?"** — `App\Service\Competition\CompetitionRoundResolver`
(`src/Service/Competition/CompetitionRoundResolver.php`), wrapped in a query for controller use:

```php
use App\Query\GetCompetitionCurrentRound\GetCompetitionCurrentRound;

$currentRound = $this->queryBus->handle(new GetCompetitionCurrentRound(
    competitionId: $competition->id,
));

$currentRound->round;              // ?string — „Osmifinále", or null
$currentRound->matchCount;         // int — matches of THIS competition in that round
$currentRound->finishedMatchCount; // int — how many of them are already played
```

Resolution order (deadline- and result-independent):
1. the round of the **latest already-kicked-off** match that carries a label;
2. otherwise the round of the **earliest upcoming** labelled match (nothing has started yet);
3. otherwise `null` — the competition has no round-labelled match at all.

Unlabelled matches are **skipped** (a round is a name; a nameless match belongs to none) and cancelled
matches never count. Membership always goes through `CompetitionMatchProvider`, so the answer respects
`all` / `subset` / `teams` and the playoff setting.

> **Item 04: `round === null` means „hide the KOLO hero stat and the Poslední kolo tab".** Do not
> render either against a null round — see the fallback note below.

**2. Leaderboard scoped to the latest round** — new enum case
`LeaderboardTimeFilter::LastRound` (value `kolo`, label „Poslední kolo"), placed **second** in the
enum so `cases()` already yields the design's tab order (Celkem · Poslední kolo · Posledních 7 dní).
The existing tab strip in `templates/portal/leaderboard/index.html.twig` renders straight from
`cases()`, so the tab and its `?obdobi=kolo` link appeared with no template change.

```php
$board = $this->queryBus->handle(new GetCompetitionLeaderboard(
    competitionId: $competition->id,
    filter: LeaderboardTimeFilter::LastRound,
));

$board->roundLabel; // ?string — the round the board is scoped to (null on other filters)
```

`GetCompetitionLeaderboardQuery` resolves the round **once** per request and applies
`m.round = :lbRound` to all three aggregates (points/accuracy, exact hits, streak) — the private
`applyTimeWindow()` became `applyPeriodFilter()`, which now branches on either a time window or a
round. `showDelta` stays false for any non-all-time filter, unchanged.

**Fallback when there is no round:** `LastRound` on a competition with no round-labelled match applies
**no** scope (the board equals „Celkem") and reports `roundLabel === null`. This keeps the page
renderable; the UI is expected to hide the tab instead of showing a board that silently lies.

### Changed — display gaps found while investigating (scope D)

- `templates/portal/sport_match/detail.html.twig` — the `.tip-stage .round` slot showed the **match
  source name** and never the round. Now mirrors `portal/guess/detail.html.twig`: round first, source
  name demoted into the `.when` line, source name still the fallback when there is no round.
- `templates/portal/match_source/detail.html.twig` — the organizer's own schedule list had no way to
  see the rounds they had just imported. Added a small uppercase round label under the kickoff time.

### Tests

- `tests/Integration/Query/GetCompetitionLeaderboardRoundTest.php` (**new**, 7 cases) — current-round
  resolution (latest started · skips unlabelled · falls back to the earliest upcoming · null when the
  competition has none), round-scoped totals ignoring both other rounds and unlabelled matches, the
  no-round fallback, and **acceptance criterion 4**: `testAllTimeTotalsAreUnchangedByTheRoundFilter`
  pins the whole all-time row (points, rank, evaluated/scored/exact/partial, accuracy, streak,
  `showDelta`, `roundLabel`) so any drift in „Celkem" fails.
- `tests/Integration/Portal/SportMatch/BulkImportFlowTest.php` — the round import test now imports a
  **two-row** sheet where the second row leaves „Kolo" empty (optional per **row**, not just per file).
- `tests/Integration/Portal/SportMatch/CreateSportMatchFlowTest.php` — asserts an omitted „Kolo" stays
  null, plus a new case creating a match **with** a round.
- `tests/Integration/Portal/SportMatch/UpdateSportMatchFlowTest.php` — an untouched edit round-trips
  the round; a new case changes it and then clears it by blanking the field.

### Verification

`cs:fix` clean · `quality` (phpstan lvl 8 + 463 unit tests) green ·
`tests/Integration/{Command,Query,Console,Portal}` green (234 / 90 / 3 / 189) ·
`doctrine:schema:validate` clean (mapping + database) · `doctrine:migrations:diff` → no changes.
Rendered as admin: `/nastenka`, `/zapasy`, `/portal/turnaje/{id}`, `/portal/zapasy/{id}`,
`/portal/zapasy/{id}/upravit`, `…/zapasy/novy`, `…/zapasy/import`, `/portal/souteze/nova`,
`…/zebricek`, `…/zebricek?obdobi=kolo` — all HTTP 200, with the round rendered on the match detail and
the source schedule, all three leaderboard tabs in the right order, and the match-picker groups showing
`Základní skupina` / `15. 6. 2025` / `Čtvrtfinále` / `Playoff` (the date bucket is the unlabelled live
match — no empty heading).

## Assumptions made

1. **Form label kept as „Kolo / fáze".** The item asked for the label „Kolo"; the pre-existing label is
   a strict superset that also names the play-off case, and its help text already names two example
   values as required. Renaming would have been pure churn, so it stands.
2. **„Poslední kolo" == the current round, not „the last *completed* round".** The hero stat („KOLO ·
   Osmifinále") and the tab use the *same* resolution, so the page cannot contradict itself. A round
   that is under way but not yet evaluated therefore shows a board of zeros — honest, and it fills in
   as results land.
3. **Unlabelled matches belong to no round** (rather than to an implicit „bez kola" bucket). They are
   invisible to both the resolver and the round-scoped board, and keep their date bucket in grouped
   lists — consistent with the pre-existing grouping behaviour.
4. **The „Poslední kolo" tab became visible on the existing Žebříček page** the moment the enum case
   was added, because the tab strip renders from `LeaderboardTimeFilter::cases()`. No template in
   `templates/portal/leaderboard/` was touched; the page itself, its hero stats and its tab styling
   remain item 04's job.
5. **`LastRound` with no resolvable round falls back to unscoped** rather than to an empty board, so
   the page always renders. `roundLabel === null` is the signal for item 04 to hide the tab.
