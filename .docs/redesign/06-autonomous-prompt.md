# 06 — Autonomous next-session prompt

Copy the block below into a fresh Claude Code session (in this repo) to continue toward
full Wtips / design-system parity. It is built for a multi-hour autonomous run that commits
directly to `main`. Pair it with the plan in this folder and the design system at
`~/www/wtips-design-system`.

> ⚠️ `main` deploys to **wtips.cz** (prod). The prompt enforces "only push when
> `composer quality` is green" — keep that guardrail.

---

```
Work autonomously for the next few hours toward our goal: a fully Wtips-migrated app with the
NEW features from the design system implemented. Follow the design system at
~/www/wtips-design-system (prototypes in project/pages/ and project/ui_kits/organizer-webapp/,
tokens/components catalogs in .docs/redesign/analysis/). Run everything, verify, and COMMIT
DIRECTLY TO main, pushing after each green feature. Do NOT ask me for permission. Use
ultracode/workflows where it helps. No co-author footer on commits.

START:
- Ensure main contains the completed dark migration (it does once this is merged). Continue ON main.
- Read .docs/redesign/ in full: 00-overview (glossary soutěž=Group, turnaj=Tournament; scope),
  02-components (component library), 04-features, and 05-backlog (the work list). The dark
  visual migration + pick-distribution + leaderboard-stats are already DONE — reuse the
  existing dark components/classes and match the already-migrated screens; don't re-skin.

BUILD (in this order — implement end-to-end: backend query/entity + UI + test each):
P1: (1) soutěž switcher on dashboard/leaderboard (server-side nav menu of the user's groups);
    (2) "Zápasy" cross-soutěž page + a nav item in Layout/Nav (generalize the dashboard's
        upcoming-matches query; filter chips Vše/Dnes/Tipovatelné/Ukončené — no Live);
    (3) dashboard personal stat cards (rank/accuracy/streak per selected soutěž — small stats
        query feeding the hero; reuse the leaderboard stat logic);
    (4) SportMatch.round label (nullable) — the ONLY schema change: edit the entity, then
        `doctrine:migrations:diff` and commit the generated migration; surface in the create
        form + CSV import + the tip-card header (fallback = tournament name). Wire my hockey
        QF/SF/final data shape if useful;
    (5) per-match ranking ("Pořadí za zápas") on the single-match page (query over
        GuessEvaluation for the match+group, sorted by points; show when can_see_all_tips);
    (6) Podium top-3 on the leaderboard page (the leaderboard query already returns rich rows;
        .podium/.pod CSS exists — render it above the table).
Then buildable P2 that needs no external service: premium teaser as VISUAL-ONLY behind a
    feature flag (no payments), favicons + og-default.png regenerated from
    assets/images/logo/logo-mark.svg into public/.
STUB + DOCUMENT (do NOT build — they need a payment provider, OAuth, or a live data feed):
    real premium payments/pricing, payouts/Výplaty, goalscorers+timeline+"Trefený střelec"
    rule, full bracket/group-stage viz, notifications feed, social login, real live-match.
    Leave them clearly noted in 05-backlog with what's blocking. Mark each finished item DONE
    in 05-backlog.md.

FIXTURES: you MAY extend AppFixtures/DevFixtures and add reference constants
(tests/Support + AppFixtures::*) for whatever data the new features/tests need (e.g. more
guesses for distribution/streak, members for the switcher). Keep the `test` fixture group
deterministic (PredictableIdentityProvider) so query tests can assert exact values.

HARD RULES:
- CQRS (CLAUDE.md): reads = QueryMessage + …Query handler + Result DTO via QueryBus; writes via
  command.bus; repos never flush(); UUID v7. NEVER hand-write migrations — entity change ->
  `doctrine:migrations:diff` -> commit generated file. Express partial unique indexes in mapping.
- A `final readonly` Result DTO must NOT use a virtual get-hook property (phpstan worker fatals);
  use plain props + compute in the template.
- Aggregations: never join GuessEvaluationRulePoints into a query that SUMs e.totalPoints
  (row multiplication) — use a separate aggregate (see GetGroupLeaderboardQuery).
- Dark only (no solid bg-white / text-navy-900 / gray / cyan); no emoji (Lucide only; import new
  icons via `ux:icons:import` before use). Czech vykání, sentence-case headings, decimal comma,
  „…" quotes, correct numerals. Preserve all Live-Component/Stimulus/CSRF/voter wiring on edits.

VERIFICATION GATE — main deploys to wtips.cz, so ONLY push when green. NOTE: in this
repo `composer quality` is ONLY `phpstan + test:unit` (see composer.json) — it is NOT the
full CI gate. The real gate (CI `.github/workflows/test.yml`, which gates the deploy) is:
  - `docker compose exec -T web composer phpstan`           (PHPStan L8)
  - `docker compose exec -T web composer cs:check`          (php-cs-fixer dry-run)
  - `docker compose exec -T web vendor/bin/phpunit`         (ALL tests — but run PER FILE
    locally; the full run OOMs the VM, see gotchas)
  - `docker compose exec -T web bin/console lint:twig templates/`
  - schema only, when you touched entities: `bin/console doctrine:schema:validate`
Authenticated WebTestCase render-smokes (assertResponseIsSuccessful) cover page rendering;
host curl is sandbox-blocked, and the test DB has no logged-in session for raw curl anyway.

ENVIRONMENT GOTCHAS (memory-pressured Docker VM):
- If the web container restart-loops, the dev DB `wtips` is missing:
  `docker compose exec -T postgres psql -U postgres -c 'CREATE DATABASE wtips;'` then it recovers.
- Before phpunit/phpstan: `docker compose stop adminer mailpit tailwind` to free RAM, then
  `docker compose exec -T web php bin/console cache:warmup --env=test`. Run integration tests ONE
  FILE AT A TIME (batches OOM/137); pass `</dev/null` to every `docker compose exec` inside
  read-loops. Restart helpers when done. The test DB auto-builds from the `test` fixture group via
  tests/bootstrap.php — after changing fixtures/migrations it rebuilds automatically (or rm
  tests/.database.cache to force it). Always rebuild Tailwind after CSS/utility changes
  (`bin/console tailwind:build`).

GIT: commit per feature directly to main (message: "Feature: <name>"), `git push origin main`
after each green feature, no co-author footer. Brand stays config-driven (APP_BRAND_NAME).

When P1+buildable-P2 are done: run the FULL per-file suite to confirm green, update 05-backlog.md,
and post a short summary of what shipped + what's stubbed/blocked.
```
