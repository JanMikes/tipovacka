# S01 — Domain rename: Tournament → MatchSource, Group → Competition

**Goal**: purely mechanical rename of code symbols, DB schema, route names, template paths,
fixtures and tests. **Zero behavior change** — every feature works exactly as before.
This creates the stable base for all semantic stages.

## Rename map

| From | To |
|---|---|
| `Entity\Tournament` (`tournaments`) | `Entity\MatchSource` (`match_sources`) |
| `Enum\TournamentVisibility` | `Enum\MatchSourceVisibility` (values unchanged `public/private`; replaced by `kind` in S02) |
| `Entity\Group` (`user_groups`) | `Entity\Competition` (`competitions` — no longer a reserved word; drop the explicit `#[ORM\Table]` override) |
| `Entity\GroupInvitation` (`group_invitations`) | `Entity\CompetitionInvitation` (`competition_invitations`) |
| `Entity\GroupJoinRequest` (`group_join_requests`) | `Entity\CompetitionJoinRequest` (`competition_join_requests`) |
| `Entity\GroupMatchSetting` (`group_match_settings`) | `Entity\CompetitionMatchSetting` (`competition_match_settings`) |
| `Entity\TournamentRuleConfiguration` (`tournament_rule_configurations`) | `Entity\MatchSourceRuleConfiguration` (`match_source_rule_configurations`) — replaced wholesale in S04, rename anyway so no `Tournament` symbol survives |
| `Guess::$group` (`group_id`) | `Guess::$competition` (`competition_id`) |
| `Membership`, `LeaderboardTieResolution`, `SportMatch` | keep names; rename their `group`/`tournament` associations + FK columns accordingly |

Cascade the rename through every layer (nothing may keep the old words except historic
migrations): Commands (`CreateGroup`→`CreateCompetition`, `CreatePrivateTournament`→
`CreatePrivateMatchSource`, `CreatePublicTournament`→`CreatePublicMatchSource`,
`UpdateTournamentRuleConfiguration`→`UpdateMatchSourceRuleConfiguration`,
`RecalculateTournamentPoints`→`RecalculateMatchSourcePoints`, `MarkTournamentFinished`→
`MarkMatchSourceFinished`, all `*Group*` commands, etc.), Queries, Events
(`TournamentCreated`→`MatchSourceCreated`, `GroupCreated`→`CompetitionCreated`, …),
Exceptions (`TournamentNotFound`→`MatchSourceNotFound`, `GroupNotFound`→
`CompetitionNotFound`, `CannotJoinFinishedTournament`→`CannotJoinFinishedCompetition`? —
NO: it references the source being finished; rename to `CannotJoinFinishedMatchSource`),
Voters (`TournamentVoter`→`MatchSourceVoter`, `GroupVoter`→`CompetitionVoter`),
Repositories, Forms, Twig components, Stimulus values, controller directories
(`Controller/Portal/Group`→`Controller/Portal/Competition`, `Controller/Admin/Tournament`→
`Controller/Admin/MatchSource`), template directories, config references
(`config/packages/messenger.php` routing entry!), voter attribute strings
(`group_view` → `competition_view`, `tournament_edit` → `match_source_edit`).

## Route names & URLs

- Route **names**: `portal_group_*` → `portal_competition_*`, `*tournament*` →
  `*match_source*`.
- URL slugs: `/portal/skupiny/...` → `/portal/souteze/...`. Tournament-page slugs
  (`/turnaje`, `/portal/turnaje`) **stay as-is** in S01 (public URLs re-scoped in S02/S09).
- Query param `?soutez=` stays.

## UI copy

- Fix the documented drift: all remaining „skupina" strings become „soutěž" (form labels
  "Název skupiny", flashes "Skupina byla vytvořena/uložena", "Skupiny" headings,
  invitation flashes). „Turnaj" copy stays for now (S02 relabels to „zdroj zápasů").

## Migration

Hand-written rename migration (documented exception to the generated-only rule):
`ALTER TABLE ... RENAME TO ...`, `ALTER TABLE ... RENAME COLUMN ...`,
`ALTER INDEX/CONSTRAINT ... RENAME TO ...` for every table/column/index/constraint touched
(match new Doctrine-generated names — run `doctrine:schema:validate` and CI's
`schema:update --dump-sql` emptiness to verify). Provide a symmetric `down()`.

## Tests & fixtures

- Rename fixture constants (`VERIFIED_GROUP_*`→`VERIFIED_COMPETITION_*`,
  `PUBLIC_TOURNAMENT_*`→`PUBLIC_SOURCE_*`, `PRIVATE_TOURNAMENT_*`→`PRIVATE_SOURCE_*`,
  rule-config constants, membership constants) and all ~120 referencing test files.
- Czech `submitForm` labels in flow tests must match the updated copy.
- Update `.docs/FIXTURES.md` to the true current fixture inventory (it is stale — treat
  this as the regeneration opportunity: document ALL constants).

## Acceptance

- [ ] `grep -ri "tournament\|\bGroup\b\|user_groups\|skupin" src/ templates/ config/ tests/ fixtures/ assets/` → only legitimate leftovers (historic migrations, `user_groups` in old migrations, generic words). No PHP symbol, route name, table, or template path contains Tournament/Group semantics.
- [ ] Full quality gate green; `doctrine:schema:validate` clean; fresh `db:reset` works.
- [ ] App manually equivalent: dashboard, competition detail, tipping, leaderboard, admin lists all render (flow tests are the proxy).

## As-built notes (2026-07-18)

- Kept explicit `#[ORM\Table(name: 'competitions')]` — the naming strategy would default
  to singular `competition`; explicit plural matches house convention (spec premise wrong).
- **Waived** (per Honza's "no BC worries, no real users" instruction): no legacy redirect
  for the public shareable-link slug change `/skupiny/pozvanka/{token}` →
  `/souteze/pozvanka/{token}` (previously distributed links 404), and session
  join/invitation intent keys renamed without fallback (in-flight intents across the
  deploy are dropped).
- Review fixes applied on top of the mechanical rename: reverted two football
  group-stage copy overreaches (home.html.twig „Skupina C", for_business „od skupin až
  po finále"), reworded three degenerate sentences (dashboard, match_sources_list,
  for_business doubled „soutěž"), completed the Badge `variant="group"`→`"competition"`
  + `.badge-group`→`.badge-competition` rename, synced `.docs/features/confirm-modal.md`.
