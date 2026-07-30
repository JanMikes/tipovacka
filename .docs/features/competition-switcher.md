# Competition switcher (`<twig:SoutezSwitcher>`)

The one control for „which soutěž am I looking at". A searchable, grouped tom-select picker
that reloads the current page scoped to the chosen soutěž — and a plain HTML form when there
is no JavaScript.

Files: [`src/Twig/Components/SoutezSwitcher.php`](../../src/Twig/Components/SoutezSwitcher.php) ·
[`templates/components/SoutezSwitcher.html.twig`](../../templates/components/SoutezSwitcher.html.twig) ·
[`src/Value/CompetitionSwitcherOption.php`](../../src/Value/CompetitionSwitcherOption.php) ·
shared `assets/controllers/tom_select_controller.js` · the `/* --- item 04 --- */` block at the
end of the tom-select section in `assets/styles/app.css`. Rendered on `/_design`.

## Props

| Prop | Type | Meaning |
|---|---|---|
| `competitions` | `list<CompetitionListItem>` **or** `list<CompetitionSwitcherOption>` | the feed (see below) |
| `currentId` | `string\|null` | RFC4122 id of the active soutěž |
| `route` | `string` | route the GET form submits to — **must be reachable with no path parameter carrying the competition** |
| `routeParams` | `array` | other path parameters of `route` (constant across every option), e.g. the match id on the soutěž-scoped match page |
| `param` | `string` | query parameter carrying the id (default `soutez`) |
| `label` | `string` | eyebrow label above the control (default `Soutěž`) |
| `id` | `string` | DOM id of the `<select>`; override when one page renders two switchers |

Extra attributes land on the root `<div>` (`attributes.defaults`), so a call site can widen or
narrow it: `class="sm:w-96"`.

```twig
<twig:SoutezSwitcher
    :competitions="my_competitions"
    currentId="{{ selected_competition.competitionId.toRfc4122 }}"
    route="dashboard"
    param="soutez"
    label="Zobrazená soutěž"
/>
```

## Two feeds, one component

Logged in, hand it `ListMyCompetitions` straight — *every* soutěž the viewer is a member of,
which is what makes the switcher the primary way to move between soutěže. Any other read model
(the logged-out variant lists the public global competitions) maps its rows to
`CompetitionSwitcherOption::fromDates(...)` in the controller; the component takes those as-is.
That keeps the component from depending on every list query in the app.

```php
'switcher_competitions' => array_map(
    static fn (SomeCompetitionItem $item): CompetitionSwitcherOption => CompetitionSwitcherOption::fromDates(
        id: $item->competitionId->toRfc4122(),
        name: $item->name,
        subtitle: $item->sportName,
        startAt: $item->sourceStartAt,
        endAt: $item->sourceEndAt,
        isFinished: false,
    ),
    $items,
),
```

`fromDates()` is also the single home of the date-range formatting — source dates are stored
**UTC** and rendered in **Europe/Prague** („2. 6. 2026 – 11. 6. 2026", „od 2. 6. 2026",
„do 11. 6. 2026", or empty). Never format those dates anywhere else.

## Grouping

Two optgroups, always in this order: **„Probíhající"** then **„Ukončené"**, driven by
`CompetitionSwitcherOption::$isFinished` (for „my soutěže" that is the source's
`completedAt`, the explicit domain signal — not „end date in the past"). Empty groups are not
rendered. `lockOptgroupOrder: true` in the shared tom-select controller keeps live on top even
while the user is typing a search.

## Navigation contract — why the competition may never be a path parameter

The control is a real `<form method="get">`. A form can only append a query string, so it can
never fill a competition id into a **path** placeholder. Pages that scope by path go through a
resolver route instead:

| Page | `route` / `routeParams` / `param` | Result |
|---|---|---|
| Nástěnka | `dashboard` / — / `soutez` | `/nastenka?soutez=<id>` — read by `DashboardController` |
| Žebříček | `leaderboard` / — / `soutez` | `/zebricek?soutez=<id>` — read by `LeaderboardController` (item 05 made the page itself id-less; there is no redirect any more) |
| Zápas | `competition_sport_match_detail` / `{competitionId: <current>, sportMatchId: <match>}` / `soutez` | the form action is **the current page**; `?soutez=<id>` makes it **302 to `/souteze/<id>/zapasy/<match>`** — read by `CompetitionMatchDetailController` (item 22) |

`routeParams` is the escape hatch for a route whose path carries something *other* than the
competition — the match id is the same for every option, so `path(route, routeParams)` resolves it
once and the form still only appends `?soutez=`.

**A page whose path DOES carry the competition redirects instead** (item 22 — the third pattern,
after „query parameter" and „resolver route"). The switcher is rendered with the CURRENT
`competitionId` in `routeParams`, so its action is the page it sits on; the page then treats
`?soutez=` as „go to that soutěž" and **302s to that soutěž's own path-scoped URL**. The canonical
URL stays path-based, the control stays a plain GET `<form>`, and **the component needs no change
at all** — which is why this is preferred over teaching the component about path placeholders.

The redirect is the ONLY thing that reads the parameter: an id that is unknown, foreign, or names
a soutěž that does not include this match is simply ignored and the page stays where it is.

Both apply the same rule: **an id the viewer may not see silently falls back to one they may.**
That is deliberate leak prevention — guessing an id must never open somebody else's board — so
any new call site owes the same fallback. On the žebříček the decision is the
`leaderboard_view` voter's, which is what lets a public global competition through while a
private one still falls back.

## Works without JavaScript

This is the part that breaks if you are not careful.

- The markup is a `<label>` + native `<select>` + a `<noscript>` submit button, inside
  `.field`. With JS on, `tom_select_controller.js` upgrades the select and submits the form on
  change (`data-tom-select-submit-on-change-value="true"`), and the `<noscript>` button is not
  rendered at all.
- `app.css` hides tom-select seeds globally with
  `select[data-controller~="tom-select"] { opacity: 0 }` (a FOUC guard). That rule would leave
  the no-JS path **invisible**, so the item-04 block re-asserts `opacity: 1` for
  `.soutez-switcher` and re-hides it only inside `@media (scripting: enabled)`. Written in that
  direction on purpose: the worst case is a brief flash of the native select, never an
  invisible control. **Do not "simplify" it back to a single rule.**
- The dropdown is rendered into `<body>` (`dropdownParent: 'body'`, see the B3 block in
  `app.css`) because the switcher usually sits inside a `.card-glass`, which clips
  (`overflow: hidden`) and opens a `backdrop-filter` stacking context.

## Shared single-select behaviour (B8) — no longer switcher-specific

Two things used to be scoped to `.soutez-switcher` and are now the contract for **every**
single-select tom-select in the app (see the `/* --- B8: tom-select focus layout --- */`
block at the end of `app.css`):

- **The dropdown caret.** The app imports tom-select's *core* stylesheet, which never
  generates `.ts-wrapper.single .ts-control::after`; item 04 generated it for the switcher
  only. B8 generates it for every single-select control and reserves its gutter through
  tom-select's own `--ts-pr-caret`. Multi-select pickers (team filter, scorer picker)
  deliberately have none — their control grows with the chips.
- **Focus does not resize the control.** In single mode the search `<input>` is taken out of
  the flex flow and overlays the control (`position: absolute; inset: 0`), so focusing can
  never wrap it onto a second line and push the page down. The selected `.item` stays
  readable underneath until the user actually types, then it goes `visibility: hidden` —
  hidden, never `display: none`, so it keeps reserving its height. Do not give the control a
  fixed height „to be safe": it must keep sizing itself from its content.

## Option metadata (and the tom-select `dataAttr` trap)

Each `<option>` carries `data-sub` (the zdroj-zápasů name) and `data-meta` (the date range).
tom-select copies **every `dataset` key** onto the option data, so `data.sub` / `data.meta` are
available to the renderer, and `searchField: ['text', 'sub']` makes the control searchable by
competition **and** source name.

Note the shared controller also sets `dataAttr: 'data-data'` for the person pickers. For
`<select>` elements tom-select looks that name up in `dataset` (which keys it as `data`), so the
JSON blob is never parsed — plain `data-*` attributes are the reliable way to attach option
metadata here.

## Edge cases (all three are contract)

- **zero** competitions → renders nothing at all,
- **exactly one** → a static, non-interactive chip (no dropdown, no form),
- **unknown/foreign `currentId`** → falls back to the first option.
