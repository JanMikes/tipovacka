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
