# Item 30 — „Chybí natipovat X zápasů" on the competition cards, on both surfaces

**Status:** TODO — **blocked on items 22 and 25.** Item 22 owns `templates/portal/dashboard.html.twig`;
item 25 adds the red `Pill` variant this badge needs.
**Filed:** 2026-07-30, from the product owner, in two messages minutes apart.

## The instructions (verbatim)

**On `/nastenka`:**

> „On /nastenka there are competition cards i want to see „CHYBÍ NATIPOVAT X ZÁPASŮ" red badge on the
> card (the card still remains the whole clickable) -> put it to top right corner"

**On `/souteze`:**

> „On /souteze page, these are cards with my competitions. Make the card whole clickable. the
> „Tipuj X" remove -> instead there will be red badge in top right corner „Chybí NATIPOVAT X ZÁPASŮ"
> and remove duplicite „31. 7. 17:30 · 152 zápasů" -> keep only the date"

**These are one feature on two surfaces and are deliberately one item.** The same badge, the same
count, the same rule about what „missing" means — implemented once and rendered twice. Two items
would have produced two counts that could disagree, which is precisely the class of bug this stream
keeps finding.

The mock was pasted inline and is not on disk. It shows the „Moje soutěže" grid: two cards, each with
an eyebrow (the zdroj zápasů name, „3. MSFL SEZÓNA 26/27"), the soutěž name as the title, and a
footer line („ZOBRAZENÁ" / „Zobrazit na nástěnce") with an arrow at the right. The badge goes in the
**top-right corner** of the card, where nothing sits today.

## What is there today (established from the code — do not re-derive)

- The grid is in `templates/portal/dashboard.html.twig` (~lines 200–245), section „Moje soutěže" —
  **deliberately un-scoped**: it is the one section the soutěž switcher does *not* filter, so the
  player's home still answers „where else do I play" (item 06).
- Each card is already clickable **as a whole** via item 18's pattern: `.card-clickable` +
  `.card-stretch` (a link painted over the entire card, **last in the DOM**) + `.card-raise` for a
  control that must stay reachable above it. The template's own comment documents this.
- Fed by `ListMyCompetitions` → `CompetitionListItem`
  (`src/Controller/Portal/DashboardController.php:70`, passed out as `my_competitions`).
- The red `Pill` variant does **not exist yet** — `Pill` has nine and the only red one is
  `pill-live`, marked „marketing only". **Item 25 adds it.** Use item 25's variant; do not invent a
  second red.

## What to do

1. **Add the count — ONE implementation, consumed by BOTH surfaces.** The number of matches in a
   competition which the viewer **has not tipped and still can**. Surface 1 needs it on
   `ListMyCompetitions` → `CompetitionListItem`; surface 2 on `ListMyPlayingCompetitions` →
   `PlayingCompetitionItem`. **Do not write the counting logic twice** — put it in one place both
   queries call, the way `CompetitionMatchProvider` is the one answer to „what's in this
   competition". Two copies would drift, and a badge that says 3 on one page and 4 on the other is
   worse than no badge.

   ⚠️ **Batched for the whole page, never one query per card.** This is the documented N+1 trap in
   `CLAUDE.md`, and it is why `TipStatsProvider` exists in the shape it does. Both grids render every
   soutěž the viewer plays in, so a per-card query is a per-soutěž round trip on the two pages a
   player opens most. Resolve it in the query that already builds the list.

2. **Define „still can tip" from the existing services, not by hand.** Which matches a competition
   includes is `CompetitionMatchProvider`'s answer and nothing else's; whether this viewer can still
   tip a given match is `EffectiveTipDeadlineResolver`'s (per-match effective deadline: the
   extend-only `max()` of competition lock / late-added kickoff / „Měnit tip" window / manager
   override). A match whose deadline has passed is **not** missing a tip — it is „Netipováno", which
   B5 established is a fact, not a call to action. Getting this wrong turns the badge into a
   permanent red nag on every finished soutěž.

3. **Render the badge top-right, only when the count is > 0.** Zero missing tips renders **nothing** —
   no empty badge, no „0 zápasů".

4. **Czech plurals, correctly.** „CHYBÍ NATIPOVAT **1 ZÁPAS**" / „**2 ZÁPASY**" (2–4) /
   „**5 ZÁPASŮ**" (5+, and 0 if it ever rendered). The app already does this elsewhere; match the
   existing idiom rather than inventing one.

5. **Keep the whole card clickable.** The badge is **not** interactive — it is a `<span>`, so it needs
   no `.card-raise` (that is for controls that must stay *clickable* above the stretched link). Do
   **not** nest it inside the `.card-stretch` anchor, and do not add a second link. After the change,
   clicking anywhere on the card — including on the badge — must still follow the card's link.

## Surface 2 — `/souteze`, „Soutěže, kde tipuješ"

`<twig:Competition:PlayingCard>` (`templates/components/Competition/PlayingCard.html.twig`), rendered
by `templates/public/competitions_list.html.twig` from `ListMyPlayingCompetitions` →
`PlayingCompetitionItem`. Today each card shows: eyebrow (zdroj), soutěž name, a „TVÁ POZICE / BODY"
inset panel, then a footer „TIPUJ DO / 31. 7. 18:00 · 16 zápasů" with a „Tipuj 16 →" link at the
right.

Three changes:

1. **The whole card becomes clickable** — item 18's `.card-clickable` + `.card-stretch` pattern, the
   same one the Nástěnka grid already uses. Note *why* this is now possible: removing „Tipuj X →"
   removes the nested interactive element that a stretched link may not contain. Do them together.
2. **„Tipuj X →" is deleted** and replaced by the badge in the top-right corner — same badge, same
   count, same plural rule as surface 1.
3. **The footer line keeps only the date**: „31. 7. 18:00", dropping „· 16 zápasů". The match count
   was duplicated by the „Tipuj 16" link beside it, and with that link gone the badge carries the
   number that actually matters (how many are *missing*, not how many exist).

⚠️ **The count in the badge is NOT the number that was in „Tipuj 16".** That link counted the matches
the batch-tipping page would offer; the badge counts matches with **no tip yet** that are **still
tippable**. On a soutěž where the player has tipped some, the two differ — and the badge's number is
the one the product owner asked for („chybí natipovat"). If you find the existing link's number was
already „missing tips", say so; do not assume either way.

**Interaction with item 31** („a secondary arrow link is a text link, not a button"): „Tipuj X →" is
one of the arrow links that item 31 would otherwise touch. This item deletes it. Whichever lands
second must not resurrect it.

## What must NOT change

- **`Competition:PlayingCard` is shared** — check every call site before changing its props, and keep
  it rendered in `/_design` half A, where it appears with the other cards. The gallery must show the
  new shape, and stays inert.
- **The card's link target and the `.card-stretch` pattern** (item 18). No nested interactive
  elements: the stretched link is the only one. `.card-raise` is for controls that must stay
  *clickable* above it — a badge is not one.
- **„Moje soutěže" stays un-scoped** by the switcher (item 06) — the badge does not turn it into a
  filtered list.
- **The empty state** (B15) for a viewer in no soutěž at all.
- **`ListMyCompetitions` still drives the switcher and `resolveSelected()`** in the same controller —
  adding a field must not change its ordering or its membership semantics.
- **Managers and admins get no free pass** (`CompetitionEntitlements`) — irrelevant to a count of the
  viewer's *own* missing tips, but do not reach for anybody else's tips while you are in there.
- Czech in the UI, English in code, identifiers and comments. No „sázka" in any form.

## Acceptance criteria

1. A soutěž where the viewer has untipped, still-tippable matches shows a red badge in the card's
   **top-right** corner reading „CHYBÍ NATIPOVAT X ZÁPASŮ", with the correct Czech plural.
2. A soutěž with nothing outstanding shows **no badge at all**.
3. A **finished** or fully locked soutěž shows no badge, however many tips were never filled.
4. The whole card is still one clickable target, badge included, with no nested link.
5. Each page issues **no additional query per card** — verify with the profiler's query count before
   and after, on **both** pages, and say the numbers.
6. On `/souteze`, „Tipuj X →" is gone, the whole card is one clickable target, and the footer reads
   only the date — no „· N zápasů".
7. The badge shows **the same number for the same soutěž on both pages**. Check one soutěž on
   `/nastenka` and on `/souteze` side by side; if they differ, the count is implemented twice.
8. `composer quality` clean.

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Load `/nastenka`** as a player with several soutěže in different states. `composer quality` does
  not catch a Twig error and cannot see a badge. `DevFixtures` has a player in multiple worlds — read
  `.docs/FIXTURES.md` and say which user and which soutěže you used, including one that must show
  **no** badge.
- **Query count is an acceptance criterion, not a nicety** — measure it (Symfony profiler), before
  and after, and report both.
- Check the badge at 320 px: the card is narrow there and the string is long. Measure element box
  `width` vs `scrollWidth` for truncation (a `Range` measures text ink, and ink is **not** clipped by
  `overflow`, so a Range alone cannot detect clipping), and bounding-box intersection against the
  card's title so the badge never lands on it.
- `docker compose exec web vendor/bin/phpunit tests/Integration/Portal tests/Integration/Query`.
  **Never run `phpunit tests/` whole — it OOMs (exit 137).** Strip ANSI before grepping.
- Add coverage for criteria 2 and 3 — „no badge when nothing is outstanding" is what would rot.
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. Push to `main`. Do not update the status board; report your sha.
