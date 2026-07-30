# Item 24 — `/souteze` drops the three-`StatCard` row

**Status:** TODO
**Filed:** 2026-07-30, from the product owner.

## The instruction (verbatim)

> „url /souteze — remove the 3 cards (completely row) AKTIVNÍ SOUTĚŽE, HRÁČŮ CELKEM, SLEDOVANÝCH
> ZÁPASŮ (we might use them somewhere else later so keep logic documented but remove them from here)"

## What is there today (established from the code — do not re-derive)

`templates/public/competitions_list.html.twig`, inside `<header>`:

- **lines ~44–60** — the `{% set %}` block that derives the three sub-labels (`competitions_meta`
  from `liveCompetitionCount` / `todayMatchCount`, `players_meta` from `newPlayerCount`,
  `matches_meta` from `matchSourceCount`), with the comment explaining that every number is
  platform-wide and nothing is padded;
- **lines ~61–65** — `<div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">` holding
  `<twig:StatCard label="Aktivní soutěže" …>`, `…"Hráčů celkem"…`, `…"Sledovaných zápasů"…`.

Fed by `src/Controller/Public/CompetitionsListController.php:55`,
`$this->queryBus->handle(new GetCompetitionsPageStats())`, passed to the template as `stats`.

The query lives in `src/Query/GetCompetitionsPageStats/` — message, `…Query` handler and
`CompetitionsPageStatsResult`. **`/souteze` is its only call site.**

## What to do

1. **Delete the whole row and the `{% set %}` block that only feeds it** from
   `templates/public/competitions_list.html.twig`. „Completely row" is explicit: not the cards with
   the container left behind, and not a `{% if %}` hiding them. Check the resulting `<header>`
   spacing — the grid carried `mt-8`, so its removal changes the gap between the CTA and the first
   section below.
2. **Stop calling the query** in `CompetitionsListController` and drop the `stats` view variable and
   the now-unused `use` import. If nothing else on the page needs the `QueryBus`, leave the
   injection alone only if something else uses it — otherwise remove it too.
3. **Keep the query itself, and document that it has no call site.** The product owner wants the
   logic preserved for later use. Follow the precedent item 15 set for `Competition:FilterBar`, which
   has survived with no production call site since: state it in a docblock on
   `GetCompetitionsPageStats` — what it computes, that it is platform-wide and viewer-independent,
   that `/souteze` used to render it as three `StatCard`s, and that it currently has **no call
   site**, so a reader does not mistake it for dead code and delete it.
4. **`tests/Integration/Query/GetCompetitionsPageStatsQueryTest.php` stays and must stay green** —
   it exercises the query directly and is now the only thing keeping it honest. Do not weaken it.
5. **`tests/Integration/Public/CompetitionsListFlowTest.php`** asserts the three card labels. Replace
   those assertions with one that asserts the labels are **absent**, so the removal cannot silently
   regress. Leave the rest of that test alone.

## What must NOT change

- **`StatCard` the component stays.** It is used elsewhere and is rendered in `/_design` half A. The
  three labels also appear in `templates/design/styleguide.html.twig` as that component's sample
  copy — **that is correct and must be left alone.** (You could not edit it anyway; see below.)
- The rest of `/souteze` is untouched: the hero's eyebrow, heading and „Vytvořit soutěž" /
  „Registrace zdarma" CTA; „Soutěže, kde tipuješ"; the PIN join bar; „Tvé soutěže"; „Veřejné
  soutěže"; and the `strana` / `moje-strana` pagination. Item 15 already removed both filter bars and
  the search — **do not reintroduce any filter chrome**, and do not add a replacement for the row.
- The page stays **public and context-aware** (item 07): anonymous visitors see the public list,
  members see what they play in and organize. Nothing about access changes.
- Czech in the UI, English in code and comments. No „sázka" in any form. Money on this page is always
  an entry fee, never a prize pool — and there is no „Výherní bank" card (item 15 removed it; do not
  let it back in).

**Supersedes, does not lose, an earlier decision.** Items 07 and 15 deliberately made these three
figures measured, platform-wide and byte-identical logged in or out („nothing here is padded or
rounded up"). That reasoning is not being reversed — the figures are simply no longer shown on this
page. Preserve the reasoning in the query's docblock so it survives for whatever surface uses it next.

## Acceptance criteria

1. `/souteze` renders 200 for an anonymous visitor and for a member, with **no** „Aktivní soutěže",
   „Hráčů celkem" or „Sledovaných zápasů" anywhere in the markup, and no empty grid container left
   behind.
2. `GetCompetitionsPageStats` still exists, is still handled, its test is still green, and its
   docblock says it has no call site and why it was kept.
3. `<header>` looks deliberate without the row at desktop and at 320 px — no orphaned gap.
4. `composer quality` clean (phpstan level 8 will catch an unused import or an unused constructor
   dependency).

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Load the page both ways** — logged out and as a member (`composer quality` does not catch a Twig
  error; it passes on a page that throws at render time).
- `docker compose exec web vendor/bin/phpunit tests/Integration/Public` and
  `tests/Integration/Query`. **Never run `phpunit tests/` whole — it OOMs (exit 137).** Strip ANSI
  before grepping.
- Check the `<header>` spacing by **measuring** the gap, not by eye, at desktop and 320 px.
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. Push to `main`. Do not update the status board and do not edit
`.docs/ui-nav/UI-MAP.md` — report what UI-MAP needs and the orchestrator lands it.

---

## What landed

### The `<header>` spacing — measured, not eyeballed

The deleted grid carried `mt-8`, and the `<header>` itself carried `mb-8`, so the CTA was 193 px
(desktop) / 483 px (320 px) above the next block, of which the cards were 129 px / 419 px. Removing
the row would have left **32 px** between the hero's CTA and the next section's eyebrow — while
**every other block boundary on this page is 56 px** (`mb-14`) and the closing CTA sits 48 px below.
32 px reads as the hero being glued to „Soutěže, kde tipuješ", so the header was moved to `mb-14`:
the hero now uses the rhythm the page already had, and no new value was invented.

Measured in headless Chrome against the dev fixtures (gap = hero-block bottom → next block top):

| View | Before (cards) | After |
|---|---|---|
| 1440 px, anonymous | header 298 px tall · gap **193** (32 + 129 cards + 32) | header 137 px · gap **56** |
| 320 px, anonymous | header 692 px tall · gap **483** (32 + 419 + 32) | header 241 px · gap **56** |
| 1440 px, member/organizer | header 298 px tall · gap **193** | header 137 px · gap **56** |
| 320 px, member/organizer | header 670 px tall · gap **483** | header 219 px · gap **56** |

All four: `.stat` count 3 → **0**, no `.grid` left in the `<header>`, `scrollWidth == clientWidth`
(zero horizontal overflow), and the three labels absent from the markup. Console clean apart from the
pre-existing `/_wdt/…` 404 from the dev web-debug toolbar, which appears identically before the change.

## Assumptions made

Product/implementation decisions the item file did not settle, resolved conservatively:

1. **The `<header>` went from `mb-8` to `mb-14`** rather than keeping `mb-8` (the app's usual hero
   margin) — see the measurements above. `mb-8` was correct while a 129/419 px card row absorbed the
   space below the CTA; with the row gone it would have made the hero the tightest boundary on a page
   whose every other boundary is 56 px. No CSS was touched: it is a utility swap in the template, so
   `assets/styles/app.css` (a sibling's file this round) stayed untouched.
2. **`testTheHeroFiguresAreIdenticalLoggedInAndLoggedOut` was deleted, not weakened.** It asserted
   `assertCount(3, …)` over `header .stat` and compared the two renderings — it described the row
   itself, so with the row gone it describes nothing that exists (item 15's own rule: do not delete a
   test that still describes something true; this one no longer does). Its `statCardMarkup()` helper
   went with it. What it guaranteed survives: the query takes no viewer (structural) and
   `GetCompetitionsPageStatsQueryTest::testTheFiguresDoNotDependOnAViewer` still pins it.
3. **The absence is pinned twice, for a member and for an anonymous visitor**
   (`testHeroCarriesNoStatCardRow` + `…ForAnAnonymousVisitorEither`), because the removed row was the
   one thing on this page item 15 had proven identical in both contexts. Each asserts the three labels
   absent, **no `.stat`**, and **no `.grid` inside the `<header>`** — the last one is what catches „the
   cards were deleted but the container was left behind". `testHeroCarriesNoPrizePoolCard` keeps its
   „bank" assertion (still true, still worth guarding) and lost only the three positive label asserts.
4. **The „no call site" docblock lives on the message** (`GetCompetitionsPageStats`), as instructed,
   with a two-line pointer to it on the handler — a reader who opens `…Query.php` first (the file with
   the SQL, so the likelier entry point) sees the warning there too, instead of concluding it is dead.
   `CompetitionsPageStatsResult`'s per-property comments were already accurate and were left alone.
5. **The `QueryBus` injection stays** in `CompetitionsListController`: three other queries
   (`ListBrowsableCompetitions` ×2, `ListMyPlayingCompetitions`, `GetCreditWallet`) still use it.
6. **The item file's own `Status:` line was left as `TODO`** — the dispatch prompt says the status
   board is the orchestrator's, and this line is part of it.
