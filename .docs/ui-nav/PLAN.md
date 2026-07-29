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
| 01 | [Navigation slim-down + noindex on de-linked marketing pages](items/01-nav-and-noindex.md) | DONE | `586d48f` |
| 02 | [„Kolo" (round) on a match: optional input, import support, grouping](items/02-match-round.md) | DONE | `b2c83dd` |
| 03 | [Development fixtures: a realistic, complete world](items/03-dev-fixtures.md) | DONE | `11d83aa` |
| 09 | [Drop the `/portal` URL prefix; unify the soutěž URL space](items/09-drop-portal-prefix.md) | DONE | `59e6dc2` |
| 07 | [„Soutěže" (`/souteze`) becomes the context-aware competitions page](items/07-page-souteze.md) | DONE | `b041ff0` |
| 04 | [`SoutezSwitcher` becomes a real grouped tom-select picker](items/04-competition-switcher.md) | DONE | `b041ff0` + `d98f81f` |
| 08 | [Competition detail: a playing surface, with everything else behind „Nastavení"](items/08-page-competition-detail.md) (+ bug B6) | DONE | `436841f` |
| 05 | [„Žebříček" becomes a real, standalone, publicly viewable page](items/05-page-zebricek.md) | DONE | `843a80e` |

Separate sub-backlogs, each with its own board (work them after the numbered items unless the
product owner reprioritises):

| Sub-backlog | Scope | Status |
|---|---|---|
| [`BUGS.md`](BUGS.md) | Bug / hardening backlog (B1…) — independent of the page restructure | TODO |
| [`CREATE-WIZARD.md`](CREATE-WIZARD.md) | Create-competition wizard + copy backlog (W1…) | TODO |

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

1. **One item at a time, sequentially.** Never launch a second implementer while one is
   `IN PROGRESS`. The shared surfaces below are touched by almost every item, so parallel
   implementers conflict — and the CSS one conflicts *semantically*, which git will not catch:

   | Surface | Why it collides |
   |---|---|
   | `assets/styles/app.css` (**846 lines, one file**) | The entire hand-written `@layer components` lives here. Two items adding component styles land in the same layer; worse, they can silently redefine the same class or fight over specificity/order without a git conflict. **Highest-risk file in the repo.** |
   | `templates/base.html.twig` | Page shell — every layout item touches it. |
   | `templates/components/Layout/Nav.html.twig` | Every navigation item touches it. |
   | `templates/portal/dashboard.html.twig` (564 l.) | Every IA item that moves something off the dashboard. |
   | `templates/portal/competition/detail.html.twig` (576 l.) | Every item that restructures the soutěž hub. |
   | `templates/admin/layout.html.twig` | Admin-shell items. |
   | `importmap.php`, `assets/app.js`, `assets/stimulus_bootstrap.js`, `assets/controllers.json` | Shared JS registration points — small files, so any two edits conflict. |

   **Stimulus controllers themselves are low-risk**: one behaviour per file in
   `assets/controllers/`, so two items adding two controllers do not collide. Same for
   `assets/icons/lucide/` — `ux:icons:import` only writes new SVGs, there is no manifest.

2. **CSS discipline** (because of the above). When an item needs styles:
   - Reuse an existing class first — read the `@layer components` block before adding anything.
   - New classes go **at the end of the section they belong to**, under a comment naming the
     item (`/* --- item NN: <title> --- */`), never interleaved into an existing rule block.
   - Prefer Tailwind utilities in the template over a new class unless the pattern repeats.
   - Never reorder or reformat existing rules — it makes the next item's diff unreadable.
3. **Item files are self-contained.** An implementer gets no conversational context. Everything
   it needs — the why, the exact routes/templates, the acceptance criteria — lives in the file.
   Cross-reference other item files by path when there is a genuine dependency; never assume
   the reader has seen them.
4. **Every item ends with a commit + push.** One commit per item, message
   `UI: <item title>` plus a short body. Push to `main` (this project deploys from `main`).
5. **Every item ends with the board updated** — status `DONE` + commit sha, in the same commit.
6. **Nothing is "done" until it renders.** Verification is part of the item, not optional.

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

- **No backwards compatibility is owed. There are no users yet.** (Product owner, 2026-07-29:
  *„don't be afraid to make huge changes or in URL — no users yet."*) So:
  - **Rename, merge or delete routes freely** when it produces a better information architecture.
    Do **not** add redirects, route aliases or „legacy" shims to preserve an old URL — delete the old
    route instead. The `/turnaje` legacy redirect is itself a deletion candidate.
  - Prefer the clean end-state over an incremental one. Do not leave a worse structure standing
    because changing it would be a bigger diff.
  - The constraint that replaces it: **nothing may 404 or 500 from inside the app.** If you delete a
    route, fix every `path()` call, every test and every doc that referenced it, in the same commit.
    `grep` for the route name before you delete it.
  - Database migrations are still generated, never hand-written, and must still run cleanly — „no
    users" frees the URL space, not the schema discipline.
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
- **Access is declared, not inferred from the URL** (item 09). There is no `/portal` prefix and
  no path-based `access_control` except `^/admin`. A page that needs a login carries
  `#[IsGranted('ROLE_USER')]` on its controller. Consequently **every new or moved route must be
  declared in `tests/Integration/Security/AnonymousReachabilityTest`**, which is keyed by
  controller class and fails on any route it does not know about. That test — not the URL, not
  `security.php` — is the authoritative answer to „who can see this page".
