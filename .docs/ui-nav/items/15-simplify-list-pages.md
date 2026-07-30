# 15 — Strip the filter chrome off Žebříček and `/souteze`

> **Status:** DONE
> **Depends on:** nothing. Check „Files another agent owns" before starting.
> **Owner decision date:** 2026-07-30

## Why (the requirement, in the product owner's terms)

Two list pages carry more filtering apparatus than they earn. The product owner wants both stripped
back to the content, mobile first.

**Žebříček** (`/zebricek`), verbatim:

> v hlavičce zanechat pouze nadpis Žebříček, text pod nadpisem smazat
> smazat ikonky, hráčů, odehrano …
> roletka soutěže zůstává
> zůstává moje pozice (bez tlačítka tipovat)
> Posléze hned tabulka
> zrušit filtry

and, clarifying „zrušit filtry":

> For the filters panel in žebříček — keep only player name search, it can be outside of card, just
> the input to take less space

**`/souteze`**, verbatim:

> On /souteze remove the filters panel too, completely whole filters/search card

## What changes

### A. Žebříček (`templates/public/leaderboard.html.twig` + its controller)

| Element | Fate |
|---|---|
| Hero heading „Žebříček" | **Keep** — it is all that remains of the hero |
| The explanatory text under the heading | **Delete** |
| The four hero stats — HRÁČŮ · ODEHRÁNO · KOLO · AKTUALIZACE, with their icons | **Delete all four** |
| `<twig:SoutezSwitcher>` (the soutěž roletka) | **Keep**, unchanged |
| „Tvoje pozice" (`.you-strip`) | **Keep**, but **without its tip button** |
| TOP 3 podium (`Leaderboard:Podium`) | **Keep on desktop, hide on phones** — decision below |
| The filter bar (search + `?razeni` sort + `?vse` expand) | **Collapse to a single bare name-search input**, outside the card |
| The period tabs — Celkem / Poslední kolo / 7 dní / Měsíc (`?obdobi=`) | **Delete entirely** — decision below |
| The table | **Keep** — it now follows almost immediately |

**Product-owner decisions, both settled 2026-07-30:**

1. **The period tabs go; the board is all-time only.** `?obdobi=`, the `LeaderboardTimeFilter` enum and
   the tab strip that renders from its `cases()` all go. ⚠ **This retires the „Poslední kolo"
   leaderboard resolution built in item 02** — read `items/02-match-round.md` before deleting, and
   remove what becomes genuinely unreachable rather than leaving orphaned code. **Round *grouping* on
   match lists is a different feature and stays**; only the leaderboard's time filter is being retired.
   Item 02's assumption 4 („the tab became visible the moment the enum case was added, because the tab
   strip renders from `cases()`") tells you where the coupling is.
2. **The podium is kept but desktop-only** — hidden on phones, where it pushes the real standings below
   the fold, shown where there is room. A CSS/utility visibility decision, not two markup branches.

`?razeni` (sort) and `?vse` (expand) disappear with the filter card. **Keep `?hledat`** — it is the one
surviving control. Everything must still work with **JavaScript off**: the search stays a real GET
form, as item 05 built it.

**Do not touch** `LeaderboardTableBuilder`'s condensing (the „… pozice 13–24 …" separator), the
`LeaderboardVoter` split between `leaderboard_view` and `leaderboard_details`, or the three sub-pages
(`/zebricek/matice`, `/zebricek/clen/{userId}`, `/zebricek/shoda`).

### B. `/souteze` (`templates/public/competitions_list.html.twig`)

**Remove the whole filter/search card — both instances.** Item 07 gave the page two independent bars
via `<twig:Competition:FilterBar>`: the public one owning `sport` · `stav` · `hledat` · `strana`, and
the organizer one owning the same keys prefixed `moje-` (plus `moje-viditelnost`). **Both go, entirely
— including the search.**

**The component itself survives** (product-owner decision): `Competition:FilterBar` keeps existing and
**stays rendered in `/_design` half A**, where it must now be **clearly marked as having no production
call site**. Read `items/13-design-gallery.md` — that page is the live gallery of *shipped* components,
so a component with no caller is exactly the drift it guards against; label it honestly rather than
letting the gallery imply it is in use.

Consequences to handle deliberately:

- The query params `sport`, `stav`, `hledat`, `strana` and every `moje-*` key become **dead on this
  page**. Remove the handling that reads them so the controller does not carry filter plumbing nothing
  can reach. `CompetitionStateFilter::forScope()` exists to decide which „Stav" chips a context offers
  — if nothing else calls it, it becomes dead too. **Report what you find before deleting anything
  outside this page**; another surface may use it.
- **Pagination is a separate question from filtering.** Item 07 assumption 7 set 12 cards per page with
  a „Zobrazit další" link and clamping for out-of-range pages. The product owner asked to remove the
  *filters/search card*, not pagination. **Keep pagination working**; if it is implemented through the
  same `strana` plumbing you are removing, keep `strana` and remove only the filter keys. Say what you
  did.
- The five sections of `/souteze` (hero, „Soutěže, kde tipuješ", the PIN bar, „Tvé soutěže", „Veřejné
  soutěže") otherwise stay as item 07 built them.

### C. `/souteze` hero stats become global, not viewer-scoped

> the numbers on /souteze in cards shows different numbers when i am logged in or not -> show always
> the same global numbers

The three hero `StatCard`s — **Aktivní soutěže · Hráčů celkem · Sledovaných zápasů**, fed by
`GetCompetitionsPageStats` — currently change with who is looking. That is deliberate: item 07's
assumption 1 scoped them to *„what the page is about"*, i.e. a signed-in visitor's own world
(competitions they play in ∪ organize) and, anonymously, the public list. **The product owner has now
reversed that:** the figures are **platform-wide totals, identical for everyone**, logged in or not.

- Change the query's scope, not the template. One set of numbers, no viewer branch.
- **The sub-labels must follow.** Item 07 assumption 2 made them viewer-relative too — „+N tento týden"
  counts *the viewer's* memberships joined in the last 7 days, „Ve N turnajích" counts distinct zdroje
  *in the viewer's scope*. A global card with a personal sub-label would be worse than either. Make
  them global or drop them; say which and why.
- **Keep every figure measured.** Item 07 was careful that no number on this page is invented, and this
  round is removing fabricated statistics elsewhere (`ROUND2.md` batch 16, item 14). Global totals on a
  young product will be small — that is fine and correct. **Do not pad, round up, or add a „+".**
- **Do not remove these cards.** Only the **Žebříček** hero stats were named for deletion.
- Update item 07's assumption 1 and 2 in `items/07-page-souteze.md` to record that they were
  superseded, with the date — do not silently contradict a recorded decision.

## Out of scope

- The Nástěnka, competition detail and match detail — all separately specced.
- `Competition:Card` and `Competition:PlayingCard` — untouched.
- No change to who may see what. `LeaderboardVoter` and the anonymous-reachability posture stay exactly
  as they are.

## Implementation notes

- **`?obdobi` removal is the risky half.** Grep the enum, its query handling, the tab template, the
  round resolution it calls, and the tests that pin it. A leftover reference is a render-time
  exception, which `composer quality` will not catch.
- The Žebříček search input „outside of card, just the input to take less space" — prefer existing
  form/input classes over a new one. If a new class is genuinely needed it goes at the **end** of the
  section it belongs to in `assets/styles/app.css` under `/* --- item 15: … --- */`, never interleaved,
  and never reordering existing rules. Check the ownership list below before touching that file.
- Every remaining control must keep working with **JavaScript off** — both pages are GET-form driven by
  design (items 05 and 07), and that is a property worth preserving, not an accident.
- Deleting the hero stats removes measured figures, not invented ones — item 07 was careful that every
  number was real. Nothing dishonest is being hidden; the product owner simply wants less chrome.

## Acceptance criteria

- [ ] `/zebricek` renders: heading „Žebříček" → switcher → „Tvoje pozice" (no tip button) → podium (desktop only) → a bare name-search input → the table. No sub-heading text, no hero stats, no period tabs, no sort, no expand control.
- [ ] `?obdobi=`, `?razeni=` and `?vse=` do nothing anywhere; no template, controller, query or test still references `LeaderboardTimeFilter`, and nothing 500s if an old URL carrying those params is opened.
- [ ] `?hledat=` still filters by player name **with JavaScript disabled**.
- [ ] The podium is absent at 430 px and present at 1440 px.
- [ ] `/souteze` renders **no** filter or search card in either the public or the organizer section; the five sections are otherwise intact and pagination still works.
- [ ] `Competition:FilterBar` still renders in `/_design`, labelled as having no production call site.
- [ ] `/zebricek` is still publicly reachable for a global competition and still falls back silently for a competition the viewer may not see; the three sub-pages still require `leaderboard_details`.
- [ ] Nothing inside the app links to a route or param that no longer exists.

## Verification

```bash
docker compose exec web composer cs:fix
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/Public
docker compose exec web vendor/bin/phpunit tests/Integration/Portal
docker compose exec web vendor/bin/phpunit tests/Integration/Security
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk by subdirectory; strip ANSI codes before
grepping.

`composer quality` **does not render templates**, so load every touched page: `/zebricek` anonymously
and as a member, `/zebricek` with a stale `?obdobi=7dni&razeni=body&vse=1` URL (must not 500),
`/souteze` anonymously, as a member and as an organizer, `/_design` as an admin. Check `/zebricek` and
`/souteze` at **1440 px and 430 px** and confirm zero horizontal overflow.

Tests currently pin the removed controls (item 05's period tabs and sort, item 07's filter bars).
**Update them to the new behaviour — do not weaken an assertion to make it pass**, and do not delete a
test that still describes something true.

After `composer db:reset` you **must** `docker compose restart web`, or every page 500s on stale
FrankenPHP worker connections. Never run `asset-map:compile`.

## Files another agent owns right now — check before starting

Agents run concurrently in this shared checkout; **never `git add -A`, `git add .` or `git commit -a`**
(an agent did that once and swept two others' unfinished work into its commit). Stage explicit paths
and verify with `git diff --cached --stat`. Another session also commits here — `git pull --rebase` if
a push is rejected, never force-push.

The live ownership list is in the dispatch prompt, not here, because it changes between rounds.
**`assets/styles/app.css` is the highest-risk file in the repo** — confirm you own it this round before
adding a single rule.

## What landed

### Žebříček — what became dead once the period tabs went

Deleted outright, because nothing could reach them any more:

| Deleted | Why it was unreachable |
|---|---|
| `src/Enum/LeaderboardTimeFilter.php` | the only source of a filter was `?obdobi` |
| `src/Enum/LeaderboardSort.php` | the only source of an order was `?razeni` |
| `GetCompetitionLeaderboard::$filter` + `GetCompetitionLeaderboardQuery::applyPeriodFilter()` + both `WINDOW_*` constants + the `CompetitionRoundResolver` dependency | one board, no windows |
| `CompetitionLeaderboardResult::$showDelta` | it was `AllTime === $filter`, i.e. permanently true |
| `CompetitionLeaderboardResult::$roundLabel` | only `LastRound` ever set it |
| `src/Query/GetCompetitionCurrentRound/` (message + handler + result) | its only caller was the KOLO hero stat |
| `CompetitionRoundResolver::roundProgress()` | its only caller was that query |
| `src/Query/GetCompetitionMatchProgress/` (message + handler + result) | its only caller was the ODEHRÁNO hero stat |
| `LeaderboardTableBuilder::$sort` / `::sort()` / `$expanded`, `LeaderboardTable::$sort` | `?razeni` and `?vse` are gone |
| `CompetitionStateFilter::forScope()` | both callers were the two `/souteze` filter bars |

**Deliberately kept:**

- **`CompetitionRoundResolver::currentRound()`** — competition detail renders its eyebrow („zdroj ·
  kolo") from it, so round *resolution* is still live domain code. Its integration coverage moved
  from `tests/Integration/Query/GetCompetitionLeaderboardRoundTest.php` (deleted with the query) to
  the new **`tests/Integration/Service/CompetitionRoundResolverTest.php`**, retargeted at the
  service. Round **grouping** on match lists was never touched.
- **`CompetitionStateFilter`** the enum — `BrowsableCompetitionItem::$state` still derives a
  competition's state from its matches and `ListBrowsableCompetitions` still accepts it as an
  optional scope. Only `forScope()` died.
- **`ListBrowsableCompetitions`' optional `sportId` / `visibility` / `state` / `search` params**, and
  `BrowsableCompetitionsResult::$sportOptions` / `$filteredCount`. The controller stops *passing*
  them, but they are the query's own tested read contract and the natural counterpart of the
  `FilterBar` the product owner chose to keep. Nothing in production sets them.
- **`LeaderboardTableBuilder`'s fold** — `visibleIndexes()`, `HEAD_SIZE` / `AROUND_ME` /
  `TAIL_SIZE` / `MIN_FOLDED_RANKS` and the gap entries are byte-identical. Only the two dead
  parameters were removed.

### Pagination survived the filter removal

`strana` and `moje-strana` are read exactly as before; only `sport`, `stav`, `hledat` and the
`moje-*` filter keys stopped being read. `ListBrowsableCompetitionsQuery` already clamped an
out-of-range page (`min(max(1, $page), $pageCount)`), so a stale deep link still lands on a real
page — and with no filters, `totalCount > 0` now implies a non-empty page, which is why the two
„Žádná soutěž neodpovídá filtru" empty states went with the bars.

### `Competition:FilterBar` in `/_design`

Three layers, so the label cannot be missed:

1. the section's status pill changed from `Hotovo` to **„Bez použití"** (`variant="warn"`);
2. a warn-coloured paragraph **above** the technical caption: „Tuto komponentu aplikace nikde
   nevykresluje. […] tohle je její **jediné** místo v celé aplikaci.";
3. the page header's pill legend gained a third row — „Bez použití = hotová komponenta, kterou
   aplikace nikde nevykresluje" — and the template docblock says the same, so the next
   component to lose its call site gets labelled the same way.

The component's own docblock and `.docs/features/competition-cards.md` both open with the same
warning. Verified rendered: all 11 chips, „Viditelnost", the sample search value and the „2 z 6
soutěží" count are present, 11 inert links, **0** real `href`s, the form inerted — so
`DesignStyleguideFlowTest::testNothingOnThePageCanAct` still holds (1 `<form>`, no `method="post"`).

### Verification

`cs:check` clean on every touched path · `composer quality` green (phpstan lvl 8 + 496 unit tests) ·
`tests/Integration/{Query,Public,Portal,Security,Service,Auth,Invitation,Event,Command,Console,Admin,Repository,Entity,Webhook,Fixtures}`
+ `DesignStyleguideFlowTest` + `FullHappyPathTest` + `GlobalCommerceJourneyTest` all green.

Measured in headless Chrome against the dev fixtures:

| Page | 1440 px | 430 px |
|---|---|---|
| `/zebricek` anonymous | `scrollWidth == clientWidth == 1440`, overflow **0**; podium **visible** 1088×264 | overflow **0**; podium **hidden** (0×0) |
| `/zebricek` member | overflow **0**; `.you-strip` visible 1088×89, **0** links in it | overflow **0**; `.you-strip` visible 398×89 |
| `/souteze` anonymous | overflow **0** | overflow **0** |
| `/souteze` member/organizer | overflow **0** | overflow **0** |
| `/_design` admin | overflow **0** | — |

- `/zebricek` still carrying `?obdobi=7dni&razeni=body&vse=1` renders **200** anonymously and as a
  member, byte-for-byte the same page as the clean URL (0 tabs, 0 toolbars, 0 `#lb-razeni`, no
  expand CTA).
- **`?hledat=` with JavaScript genuinely disabled** (`window.Stimulus === undefined`): submitting
  the real GET form navigated to `/zebricek?soutez=…&hledat=martas`, the table went 14 rows → 1,
  the footer read „Zobrazeno 1 z 24 hráčů · hledání „martas"", and the soutěž survived the submit
  via the hidden field. A no-match needle renders the „…v tomhle žebříčku není" branch with its
  „Zrušit hledání" link.
- **`/souteze` hero figures byte-identical** anonymous / member / organizer-admin: the three
  `.stat` cards' `outerHTML` hashes to the same `sha256:17dddc93e6a18059` in all three cases
  („Aktivní soutěže 15 · 9 živě teď", „Hráčů celkem 29 · +26 tento týden", „Sledovaných zápasů 40 ·
  Ve 8 turnajích"). Nothing was padded or rounded.

## Assumptions made

Product decisions the item file did not settle, resolved conservatively:

1. **`?vse` and the „Zobrazit celý žebříček" button really are gone**, even though the button lived
   in the table footer rather than in the filter card the item describes. The item says so three
   times (the fate table, the prose, and acceptance criterion 2: „`?vse=` do nothing anywhere", „no
   expand control"), so it is settled rather than an omission. **`LeaderboardTableBuilder`'s
   condensing is untouched** — the fold, its four size constants and the „… pozice 13–24 …"
   separator are byte-identical; only the now-unreachable `$expanded` parameter was removed, which
   is orphaned plumbing rather than behaviour. The consequence is that the ranks inside a folded
   stretch are reachable **through the search** instead of through a button, which is exactly what
   the one surviving control is for; the footer says so („· zbylé pozice najdeš hledáním", rendered
   only when the board is actually condensed). Measured live: 14 of 24 rows shown, and searching
   „martas" reaches rank-whatever directly.
2. **`showDelta` was removed rather than pinned to `true`.** With no windows left it could never be
   false, so the Δ column now always renders. Two assertions that described the windowed board
   („windowed board hides the Δ column", „a re-ranked board carries no all-time Δ") were deleted
   because they no longer describe anything that exists — not weakened.
3. **The hero's platform-wide scope includes private competitions.** „Platform-wide totals" read
   literally; only aggregate counts leave `GetCompetitionsPageStats` (no name, owner or id), so a
   private soutěž contributes to „Hráčů celkem" without becoming visible anywhere. The alternative
   — keeping the old anonymous scope (global competitions only) for everyone — would also have been
   „identical for everyone" but would have called a subset a total.
4. **The three sub-labels were made global, not dropped.** All three are naturally global („N živě
   teď" / „N zápasů dnes", „+N tento týden" = distinct users who joined **anything** in the last 7
   days, „Ve N turnajích" = distinct zdroje zápasů the counted matches belong to), so a global card
   over a personal sub-label never arose. „Ve N turnajích" is now derived from the counted matches
   rather than from the scoped competitions, which is what the label under „Sledovaných zápasů"
   actually claims.
5. **The hero's „Hledat" button on `/souteze` was removed.** Item 07 assumption 5 shipped it as a
   real filter precisely because „a button that only scrolled would have been a lie"; with the
   search field gone it would have become exactly that lie. The section it pointed at is directly
   below.
6. **The Žebříček hero sub-text took the only link into the soutěž with it.** That paragraph was
   „Pořadí v soutěži <name> — …", and its `<a>` was the page's only route to
   `/souteze/{id}`. The item names the sub-text for deletion, so it went; the soutěž is still named
   by `<title>` and by the switcher, and „Soutěže" is one nav click away. Three tests that used
   that link as their „which board is this?" probe now read `<title>` instead — same fact, different
   probe.
7. **The „Top 3" podium caption lost its period suffix** („Top 3 · celkem" → „Top 3"), since
   `active_filter` no longer exists and there is only one period.
8. **Desktop-only = the `md` breakpoint** (`hidden md:block`, ≥768 px). The item says „hidden on
   phones […] shown where there is room", and `md` is this design system's phone/tablet boundary.
   Measured absent at 430 px and present at 1440 px.
9. **`templates/design/styleguide.html.twig`'s three „MS 2026" sample strings were renamed**
   („Firemní MS 2026" → „Firemní liga", „Velká tipovačka MS 2026" → „Velká tipovačka", the
   FilterBar's `search="MS 2026"` → `search="firemní"`) on the orchestrator's instruction — the
   product owner wants no „MS 2026" anywhere. Five more occurrences lived in
   `DesignStyleguideController`'s sample DTOs and went the same way; `/_design` now contains zero.
