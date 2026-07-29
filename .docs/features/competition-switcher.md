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
| `route` | `string` | route the GET form submits to — **must be reachable with no path parameter** |
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

## Navigation contract — why `route` must be path-parameter-free

The control is a real `<form method="get">`. A form can only append a query string, so it can
never fill a competition id into a **path** placeholder. Pages that scope by path go through a
resolver route instead:

| Page | `route` / `param` | Result |
|---|---|---|
| Nástěnka | `dashboard` / `soutez` | `/nastenka?soutez=<id>` — read by `DashboardController` |
| Žebříček | `leaderboard` / `soutez` | `/zebricek?soutez=<id>` — read by `LeaderboardController` (item 05 made the page itself id-less; there is no redirect any more) |

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
