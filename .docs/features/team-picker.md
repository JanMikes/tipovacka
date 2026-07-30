# Team picker & the team directory

Teams are a first-class entity (`App\Entity\Team`). A team is one identity across every
match it plays — the home for a logo (future), a short name, a country and a brand color,
and the anchor for the per-team roster (`Player.team`).

## Hybrid scope (the one rule to remember)

- **Curated source** (admin-managed, reusable) → its matches draw teams from ONE **global
  directory** (`team.match_source_id IS NULL`), one row per real club per sport.
- **Private source** (the hidden internal of a from-scratch competition) → **local** teams
  scoped to that source (`team.match_source_id` set), so office-pool names never pollute the
  directory.

Scope is *derived* (`Team::$isGlobal = matchSource === null`), never stored. Two partial
unique indexes enforce name-uniqueness per scope. The single home of the rule is
[`App\Service\Team\TeamResolver`](../../src/Service/Team/TeamResolver.php): `resolve(source,
name, now)` find-or-creates in the right scope; `findExisting()` is the create-free variant
used by the reassign guard and the import "nový tým" preview.

## Picking UX

Commands still carry team **names** (strings) — the picker is pure affordance, and names are
unique within a source's resolution scope, so `name → Team` is deterministic. This keeps CSV
import and the form uniform and JS-optional.

- **`team-picker`** Stimulus controller (`assets/controllers/team_picker_controller.js`):
  enhances the plain home/away text input into a single-select tom-select that autocompletes
  existing teams and creates a new one on free-type. Degrades to a normal text input with JS
  off (the typed name still posts and resolves server-side).
- **Copy — every stock tom-select string must be overridden**, or it falls back to English
  (B9). A picker that can create says „Přidat tým „**X**"…" / „Přidat hráče „**X**"…"
  (`option_create`, keeping the `create` class the dark skin's `.create.active` rule needs)
  and its `no_results` invites creating („Nic nenalezeno — napište název nového týmu");
  a picker that cannot create (`team-filter`) only reports „Žádný tým nenalezen". The typed
  value is user input — always `escape(data.input)`.
- Autocomplete endpoint: `TeamAutocompleteController` (`GET /zdroje/{id}/tymy?q=…`,
  gated by `MatchSourceVoter::CREATE_MATCH`) → `TeamRepository::searchGlobalBySport` (curated)
  or `searchLocalBySource` (private).
- CSV/XLSX import (`SportMatchImporter`) resolves each `Domácí`/`Hosté` name via `TeamResolver`
  on commit; unknown names grow the directory (curated) / local pool (private).

### Team **filter** picker (competition scope, id-based, multi-select)

A separate picker for the „Podle týmu" competition scope (see
[create-wizard](create-wizard.md)) — distinct from the match `team-picker` above:

- **`team-filter`** Stimulus controller (`assets/controllers/team_filter_controller.js`):
  multi-select tom-select, **no** free-create (you filter by existing teams only), keyed on the
  team **UUID** (`valueField: 'id'`) because a filter stores team identities, not names. It syncs
  the selected ids into an optional hidden `payload` input (the wizard's LiveProp) and, on a plain
  form (the manage page), the `<select multiple name="teams[]">` posts them directly.
- Autocomplete endpoint: `SourceTeamFilterAutocompleteController`
  (`GET /zdroje/{id}/filtr-tymy?q=…`, gated by `MatchSourceVoter::CREATE_COMPETITION`) →
  `TeamRepository::searchTeamsInSource` — only teams that actually play in the source (home OR
  away of a live match), so a filter never offers a team with zero matches. Server-side
  `TeamResolver::belongsToSourceScope` re-validates on write.

## Monogram (logo comes later)

No uploads yet: every team renders as a colored initials **monogram** via the pure, unit-tested
[`App\Value\TeamMonogram`](../../src/Value/TeamMonogram.php) — background = brand color or a
stable hash of the name, foreground = whichever of black/white wins WCAG contrast (so text is
never illegible). One component renders it everywhere: `<twig:TeamFlag :team="…">` (accepts a
`Team` or a `TeamView`; a bare `name` string still works as a fallback). Query results carry a
`TeamView` (`App\Value\TeamView`) rather than the entity.

## Reassigning a match's team

`SportMatch.homeTeam/awayTeam` are `Team` FKs. Renaming a team in the directory is free (the FK
is stable — the same identity, new label). What `SportMatchTeamsLocked` blocks is **reassigning
a match to a *different* team** once `MatchEvent`/`GuessScorer` rows exist (they point at
Players of the old team). Fixing a typo is therefore a rename in the admin directory, not a
match edit.

## Admin directory

`/admin/tymy` (`admin_team_list`) lists global teams grouped by sport with their match count;
create/edit at `admin_team_create` / `admin_team_edit` (`TeamFormType`, `CreateTeamCommand` /
`UpdateTeamCommand`). Gated by the `^/admin` firewall (no dedicated voter). Local teams are not
managed here — they live and die with their private source. The logo-upload field lands on this
form in the follow-up.
