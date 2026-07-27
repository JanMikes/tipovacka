# UI & Navigation Restructure — status board + orchestration protocol

**What this is.** A 2026-07 working stream focused on **information architecture, page
structure and navigation** — reorganizing what already exists so the product makes sense
from a user's perspective. The domain is considered largely complete; this stream is about
*presentation and organization*, not new business rules. Small domain/query additions are
allowed when a UI reorganization genuinely needs them (e.g. a new read query to feed a new
page section), but they are a means, not the goal.

**Relationship to other docs**
- [`.docs/DOMAIN.md`](../DOMAIN.md) — authoritative business rules. **Never contradict it.**
  If a UI change implies a business-rule change, say so in the item file and update DOMAIN.md.
- [`.docs/rebuild/PLAN.md`](../rebuild/PLAN.md) — the finished 2026-07 domain rebuild. Historical.
- [`.docs/redesign/`](../redesign/) — the earlier visual/design-system redesign. The dark
  design system, tokens and component vocabulary come from there and are **already implemented**.
  This stream builds on it; it does not restart it.
- [`UI-MAP.md`](UI-MAP.md) — **read this first.** Snapshot of every route, template,
  component and Stimulus controller that exists today. Keep it updated as items land.
- [`items/`](items/) — one self-contained backlog item per file. This is the actual work.

---

## Status board

Legend: `TODO` not started · `IN PROGRESS` claimed by an agent · `DONE` merged & pushed ·
`BLOCKED` needs a decision from the user.

| # | Item | Status | Commit |
|---|------|--------|--------|
| — | _(no items yet — awaiting requirements from the product owner)_ | — | — |

<!-- Add one row per item file. Keep the order = intended execution order. -->

---

## Orchestration protocol

### Roles

- **Orchestrator** (the main Claude Code session): talks to the product owner, turns spoken
  requirements into item files, decides order, launches one implementer at a time, and keeps
  this status board honest. The orchestrator does **not** write feature code.
- **Implementer** (a subagent, one per item): reads `UI-MAP.md` + exactly one item file,
  implements it end-to-end, verifies, commits, pushes, updates the board.

### Rules

1. **One item at a time, sequentially.** Items touch overlapping templates (`base.html.twig`,
   `Layout/Nav.html.twig`, `dashboard.html.twig`, `competition/detail.html.twig`) — running
   two implementers in parallel produces conflicts. Never launch a second implementer while
   one is `IN PROGRESS`.
2. **Item files are self-contained.** An implementer gets no conversational context. Everything
   it needs — the why, the exact routes/templates, the acceptance criteria — lives in the file.
   Cross-reference other item files by path when there is a genuine dependency; never assume
   the reader has seen them.
3. **Every item ends with a commit + push.** One commit per item, message
   `UI: <item title>` plus a short body. Push to `main` (this project deploys from `main`).
4. **Every item ends with the board updated** — status `DONE` + commit sha, in the same commit.
5. **Nothing is "done" until it renders.** Verification is part of the item, not optional.

### Definition of done (applies to every item unless the item says otherwise)

```bash
docker compose exec web composer cs:fix        # style first
docker compose exec web composer quality       # phpstan lvl 8 + unit tests
docker compose exec web vendor/bin/phpunit tests/Integration/<relevant subdirs>
```

Plus: load every touched page in the browser (or via `curl -s localhost:58080/<path>`) and
confirm HTTP 200 + the expected markup. `composer quality` does **not** cover templates —
a broken Twig tag only shows at render time.

Never run `phpunit tests/` whole — it OOMs (exit 137). Chunk by subdirectory.

### Launching an implementer (orchestrator crib sheet)

Prompt shape:

> You are implementing one backlog item from the UI & navigation restructure stream of the
> `tipovacka` project (`/Users/janmikes/www/tipovacka`).
>
> 1. Read `/Users/janmikes/www/tipovacka/CLAUDE.md` — the project conventions are binding.
> 2. Read `.docs/ui-nav/UI-MAP.md` — the current UI surface.
> 3. Read `.docs/ui-nav/items/<FILE>.md` — this is your task. Implement it completely.
> 4. Follow the Definition of Done in `.docs/ui-nav/PLAN.md`.
> 5. Commit and push. Update the status board row for this item to DONE with the sha, in the
>    same commit.
>
> Do not start other items. If you hit a genuine product decision the item file does not
> answer, pick the most conservative reading, implement it, and record the assumption in a
> `## Assumptions made` section appended to the item file.

### Resuming after a cold start

A fresh session with zero context can continue by:
1. Reading this file (protocol + board).
2. Reading `UI-MAP.md` (current state).
3. Picking the first `TODO` row and launching an implementer as above.
4. If a row says `IN PROGRESS`, check `git log --oneline -5` — either the work landed (mark
   `DONE`) or it did not (reset the row to `TODO` and relaunch).

---

## Conventions specific to this stream

- **Language: Czech in the UI, English in code/comments.** Every user-facing string is Czech.
  Never the word „sázka". Vocabulary is fixed in DOMAIN.md — soutěž, zdroj zápasů, tip, žebříček.
- **Reuse before redesign.** The component vocabulary in `templates/components/` is rich
  (`Pill`, `Badge`, `StatCard`, `EmptyState`, `Avatar`, `Breadcrumbs`, `TeamFlag`, …). Prefer
  composing existing components. Introduce a new component only when nothing fits, and when
  you do, add it to `templates/components/` and document it in `.docs/features/` if it is a
  reusable pattern.
- **The styleguide page `/_design` is the shop window.** If you add or change a shared
  component, render it there (`templates/design/styleguide.html.twig`).
- **Icons must be imported before use** — `bin/console ux:icons:import lucide:<name>`, commit
  the SVG. A missing icon is a render-time exception in dev.
- **Never run `asset-map:compile`.** If assets look frozen: `rm -rf public/assets`, then
  `docker compose restart web`.
- **No new literal prices.** Everything monetary comes from `Credits/PricingConfig`.
