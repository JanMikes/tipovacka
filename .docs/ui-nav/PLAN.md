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
| 06 | [„Nástěnka hráče" (rebuild of `/nastenka`)](items/06-page-nastenka.md) | DONE | `6561d1d` |
| 10 | [Match detail (`/zapasy/{id}`): distribution, timeline, per-match ranking](items/10-page-match-detail.md) | DONE | `6bfd659` |
| 11 | [Match rows: one card, boosts inside it](items/11-match-row-card.md) | DONE | `a8b7e8b` |
| — | [Cursor spotlight turned off behind a master switch](../features/cursor-spotlight.md) | DONE | `3605a60` |
| 12 | [One name for the tip split: „Rozložení tipů" everywhere](items/12-naming-rozlozeni-tipu.md) | DONE | `c3c052e` (+ `PricingConfig` docblock) |
| 13 | [`/_design` becomes the live component gallery](items/13-design-gallery.md) | DONE | `f624743` |
| — | Boost panel copy + „Uložit tip" / „Jak tipovali ostatní" (round 2) | DONE | `295b47c` |
| — | Domain: the „Měnit tip" window is **per match**, not per day (round 2) | DONE | `d44539a` |
| 14 | [Homepage: drop invented figures, countdown, „Proč Wtips"; fix the wrapped score](items/14-homepage-cleanup.md) | DONE | `287499c` |
| 15 | [Strip the filter chrome off Žebříček and `/souteze`](items/15-simplify-list-pages.md) | DONE | `6099926` |
| 16 | [Homepage: a closing CTA that claims nothing, and no „MS 2026"](items/16-homepage-cta-and-ms2026.md) | DONE | `01e8dda` + `358884d` (tests) |
| 17 | [App chrome: credits in the bar, simpler footer, B18 + B20](items/17-chrome-pass.md) | DONE | `09c9d21` + `90f7fb8` |
| 18 | [Nástěnka: quick actions, clickable cards, new match-card design](items/18-page-nastenka-pass.md) | DONE | `3e0cffc` + `096546b` + `11184db` |
| 19 | [Competition detail: description, invite CTA, reorder, 5 matches, credits modal](items/19-page-competition-detail-pass.md) | DONE | `4a3d3a8` … `82eb31f` |
| 20 | [Tips are revealed by the RESULT, not the deadline (all surfaces)](items/20-tip-matrix-visibility.md) | DONE | `9303b78` |
| 21 | [One match card: migrate `/zapasy`, delete the `variant` prop](items/21-one-match-card.md) | DONE | `ed432eb` + `c9d6a56` |
| 24 | [`/souteze` drops the three-`StatCard` row](items/24-souteze-drop-stat-cards.md) | DONE | `b0fbe7c` |
| 23 | [One canonical copy and price per booster, everywhere](items/23-boost-copy-and-prices.md) | DONE | `2578def` |
| 27 | [The invitation landing says it once, not three times](items/27-invitation-landing-copy.md) | DONE | `7dc2ea3` |
| 29 | [The dev wallet can no longer demonstrate „nemáte dost kreditů"](items/29-dev-wallet-after-the-price-rise.md) | DONE | `61794ab` |
| 30 | [„Chybí natipovat X zápasů" badge, on both card surfaces](items/30-nastenka-missing-tips-badge.md) | TODO — blocked on 25 | — |
| 31 | [A secondary arrow link is a link, not a button](items/31-arrow-links-are-links.md) | IN PROGRESS | — |
| 28 | [No boost intro on the join landing](items/28-no-boost-intro-on-the-join-landing.md) | DONE | `5c59521` |
| 22 | [One match page per soutěž; `/zapasy/{id}` becomes the source-side page](items/22-match-page-per-competition.md) | DONE | `876e674` |
| 25 | [A missing tip is red, not amber](items/25-missing-tip-goes-red.md) | IN PROGRESS | — |
| 26 | [Competition detail: „Pravidla" modal, no boost card, no team pills](items/26-competition-detail-rules-modal.md) | TODO — blocked on 25 + 31 | — |
| 32 | [CI red: a boost refund assertion expects the old price](items/32-ci-red-boost-refund-amount.md) | DONE | `3f733a5` |
| 33 | [`GetGuessesForMatchInCompetition` has no consumer left](items/33-orphaned-guesses-read-model.md) | TODO | — |

**Round of 2026-07-30, second half.** Items 22–27 came from the product owner in one sitting, so they
overlap heavily on three files — `templates/portal/competition/detail.html.twig`,
`templates/components/Match/MatchRow.html.twig` and `assets/styles/app.css`. Item 22 owns all three,
which is why 25 and 26 are **queued rather than dispatched**: they are small, and a fourth agent in
those files is exactly how work gets swept. Ownership for this round:

| Item | Owns |
|---|---|
| 22 | both match-detail controllers + templates, `SportMatchVoter`, `MatchRow`, `GuessSubmitForm`, `dashboard*`, `matches/index`, `competition/detail`, `styleguide` + `DesignStyleguideController`, `app.css`, `UI-MAP.md`, `competition-switcher.md` |
| 23 | `BoostType`, `PricingConfig`, `PricingExtension`, `Boost/Panel`, `Match/TipStats`, `_boost_intro_modal`, `home`, `pricing`, `CreateWizard`, the ledger queries, `DOMAIN.md`, `CLAUDE.md` |
| 27 | `invitation/landing.html.twig` + `tests/Integration/Invitation` |
| orchestrator | `PLAN.md`, `BUGS.md`, `items/`, `screenshots/` |

Separate sub-backlogs, each with its own board (work them after the numbered items unless the
product owner reprioritises):

| Sub-backlog | Scope | Status |
|---|---|---|
| [`BUGS.md`](BUGS.md) | Bug / hardening backlog (B1…) — independent of the page restructure | B1–B9, B11, B13–B21, B23, B25 DONE · B10 **WONTFIX** (by design) · B12, B22, B24, B26–B28 DONE · B29, B30, B31 DONE - **the bug backlog is empty** |
| [`CREATE-WIZARD.md`](CREATE-WIZARD.md) | Create-competition wizard + copy backlog (W1…) | DONE (W1–W6) |

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

1. **2–3 implementers at a time, on provably disjoint surfaces** — amended 2026-07-30; this rule
   used to say „one at a time, sequentially". What actually breaks is not concurrency, it is two
   agents touching one file, so the rule is now about **file ownership, not head count**:
   - Before dispatching, list the files each item will touch and check the lists do not intersect.
     If they do, either give the file to exactly one item (and put the other item's needed change
     into that item's spec — items 12/13 do this with `styleguide.html.twig`), or serialise them.
   - **Every prompt must name the files the other in-flight agents own.** An agent cannot see its
     siblings; if the prompt does not say it, it does not know it.
   - **The orchestrator owns `PLAN.md` and `UI-MAP.md` while more than one agent is in flight**, and
     agents are told not to edit them. Two agents updating the same board table in one checkout is
     the likeliest way to lose work: they are not merging branches, they are committing to the same
     working tree, so one `git commit -- <path>` sweeps the other's half-finished edit. The
     orchestrator records each sha from the agent's report instead.
   - **Commit with `git commit -o <path> [<path>…]` (`--only`). Never `git add` + `git commit`.**
     Learned the hard way on 2026-07-30: an agent staged explicit paths, verified the index with
     `git diff --cached --stat`, and its commit **still swept in `assets/styles/app.css`, which
     another agent owned** — the sibling staged into the index in the window between the `git add`
     and the `git commit`. **Verifying the index proves nothing: it is shared mutable state and
     another process can write to it after you look.** `-o` takes exactly the named paths from the
     working tree and ignores everything else staged, so it is index-independent. (Recovery, if it
     happens anyway: `git reset --soft`, then re-commit with `-o`.)
   - Never `git add -A` / `git add .` / `git commit -a`.
   - **Never restore a file from HEAD to take a "before" measurement, and never run a tree-wide
     `git restore` / `git checkout .` / `git stash`.** This is what actually destroyed work on
     2026-07-30. One agent said so outright: *„I briefly overwrote `assets/styles/app.css` with the
     HEAD version to take a baseline"* — and its sibling watched a finished, measured edit vanish
     with a clean `git status` and no commit containing it. The orchestrator then lost hours blaming
     „another session": **there is no other session. Every agent in this tree is one this stream
     dispatched**, and a `/clear` does not stop the ones already running.
     **To measure a before/after, do not touch the file.** Delete or override the rules in the live
     CSSOM, toggle a class, or copy the file to the scratchpad and read it there. `-o` protects the
     index; nothing protects the working tree from another process restoring it.
   - `-o` does **not** save you when two agents edit the *same* file — it commits the working-tree
     version of that path, other agent's hunks included. That is what file ownership is for.
   - **File ownership is not the only shared state — the DATABASE and the `web` container are shared
     too.** `composer db:reset` drops every connection and reloads the fixtures for *everyone*;
     `docker compose restart web` bounces the app under any sibling driving a browser. On 2026-07-30
     an agent's `db:reset` failed with „database is being accessed by other users", so it restarted
     `web` to force the connections shut — while another agent was mid-verification. Nothing was
     lost, but that was luck rather than design. **Only one agent may hold the database at a time.**
     Before dispatching, ask not only „do their file lists intersect?" but „do they both need
     `db:reset`, and is one of them driving a browser right now?" — if so, serialise them.
   - **Never run `composer cs:fix` repo-wide while a sibling is in flight** — it rewrites their files
     and pulls their hunks into your working tree. Scope it to your own paths, or run `cs:check`, fix
     your own findings by hand, and report the rest.

   The shared surfaces below are touched by almost every item, so they need an explicit single owner
   per round — and the CSS one conflicts *semantically*, which git will not catch:

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

## Product-owner decisions (2026-07-30)

Settled in conversation; each is now binding on this stream.

0. **JavaScript-off support is DEFERRED** (product owner: *„javascript off is deferred, everyone has
   javascript today"*). Stop spending verification effort on it: no more „disable scripting and confirm
   the page still works" passes, and a control may rely on JS from now on.

   **Nothing already built is to be undone** — the JS-free controls this stream shipped (the
   `SoutezSwitcher` GET form, the leaderboard search, the Nástěnka filters, B25's collapse-instead-of-hide)
   all still work and several are better code for it. This is forward-looking only.

   **One caveat worth keeping, because it is not the same claim:** „everyone has JavaScript enabled" is
   true; „everyone's JavaScript always runs" is not. A thrown error, a failed asset fetch, or a Stimulus
   controller that fails to connect produce exactly the same page as scripting-off — and this stream has
   two concrete instances: **B16**, where a `disconnect()` silently reduced the confirm dialog to a plain
   submit *indistinguishable from the no-JS fallback*, and **B25**, where a reveal button left content
   unreachable. So the JS-off pass had been doubling as a „what happens when JS fails" check. With it
   gone, that failure mode is only caught by **the console** and by **server-side guards** — so keep
   checking the console when driving a browser, and keep destructive actions safe on the server (CSRF,
   idempotence) rather than relying on a dialog that may not have loaded.

1. **The tip split is „Rozložení tipů" — one name, everywhere.** The product owner's mocks say
   „DISTRIBUCE TIPŮ"; the documented vocabulary wins instead. The sweep found the app had **three**
   names for one feature — „Rozložení tipů" (the section heading), „Lišta tipů ostatních" (the boost
   that unlocks it) and „Distribuce tipů" (the homepage) — so a player was sold one name, shown a
   second and asked to buy a third. Item 12 collapses them; the boost becomes „Rozložení tipů
   ostatních". Enum values, CSS classes and every other identifier stay English.
2. **`/_design` is the live component gallery, plus a marked deferred section.** This resolves the
   contradiction between the page's own docblock („DEFERRED elements […] CUT items must NOT appear
   here") and this file's „shop window" rule. Item 13 restructures it into two halves. The page stays
   admin-only, unlinked, and completely inert.
3. **The cursor spotlight stays off.** Committed as `3605a60` with an `ENABLED` master switch in
   `assets/spotlight.js` and every CSS layer gated behind `.spotlight-on`, so re-enabling it is one
   constant. Gating the CSS as well as the JS is the point — with only the listener off, `--mx/--my`
   fall back to `50% 50%` and each card would still paint a static centred glow on hover.
4. **Fantasy and the PP/PEN split remain deferred** (unchanged from `CREATE-WIZARD.md` W1). Fantasy
   has no domain concept at all; overtime stays ONE combined score meaning „after prolongation *or*
   shootout".

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
- **The styleguide page `/_design` is the shop window — and it has two halves** (item 13).
  Half A „Sdílené komponenty" is the live gallery: if you add or change a shared component,
  render it there with its **real** tag (`templates/design/styleguide.html.twig`), fed by a
  private `sample…()` method on `DesignStyleguideController`. Never hand-copy a component's
  markup, and never query the database — the page must render on an empty one. Half B
  „Připravujeme / reference" is for design-system elements whose feature is **not** built.
  The whole page stays admin-only, unlinked and inert: half A is piped through the template's
  `inert()` macro, which strips every `href`, `<form>`, submit button and Stimulus hook, so a
  sample UUID can never 404 and the boost paywall can never POST. If you touch that macro, keep
  `DesignStyleguideFlowTest::testNothingOnThePageCanAct` green — it asserts the page holds
  exactly **one** `<form>` (the switcher's, targeting `/_design` itself) and **no**
  `method="post"`.
- **Icons must be imported before use** — `bin/console ux:icons:import lucide:<name>`, commit
  the SVG. A missing icon is a render-time exception in dev.
- **Never run `asset-map:compile`.** If assets look frozen: `rm -rf public/assets`, then
  `docker compose restart web`.
- **An arrow means navigation, so it is a link — unless it is an action** (item 31, product owner
  2026-07-30). A secondary „go see more" link carrying a `lucide:arrow-right` is styled as a **text
  link** (accent text, inline arrow, no border, no background), never as a bordered `btn-ghost`. A
  **primary action keeps its button chrome even with an arrow** — „Zadat tip →" and the marketing
  hero CTAs stay buttons. The rule was deliberately scoped to the ghost family after measuring the
  literal reading: 26 arrow-bearing buttons, of which 16 are primary/marketing CTAs.
- **No new literal prices.** Everything monetary comes from `Credits/PricingConfig`.
- **Access is declared, not inferred from the URL** (item 09). There is no `/portal` prefix and
  no path-based `access_control` except `^/admin`. A page that needs a login carries
  `#[IsGranted('ROLE_USER')]` on its controller. Consequently **every new or moved route must be
  declared in `tests/Integration/Security/AnonymousReachabilityTest`**, which is keyed by
  controller class and fails on any route it does not know about. That test — not the URL, not
  `security.php` — is the authoritative answer to „who can see this page".
