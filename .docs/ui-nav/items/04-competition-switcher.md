# Item 04 — `SoutezSwitcher` becomes a real grouped tom-select picker

**Status:** DONE
**Depends on:** nothing
**Blocks:** items 05 (Žebříček) and 06 (Nástěnka) — both are driven by this control

---

## Why

The product owner wants the competition picker on Žebříček and Nástěnka to be *„tomselect here with
some good dropdown/select UI, like dates from to etc, when logged in, all my competitions, grouped by
sections → expired, live. Always live first."*

Today `templates/components/SoutezSwitcher.html.twig` is **not** tom-select: it is a server-rendered
`<details>`/`<summary>` disclosure containing plain anchor links, with no search, no grouping and no
dates. Its only call site is `templates/portal/dashboard.html.twig`. Making it a proper picker once,
here, keeps items 05 and 06 from each inventing their own.

Reference screenshots: `.docs/ui-nav/screenshots/img05-zebricek-a.png` (the „SOUTĚŽ / Firemní MS 2026"
control under the headline) and `img06-nastenka-full.png` („ZOBRAZENÁ SOUTĚŽ" in the hero).

## Scope

### Behaviour

- Rebuild the control on **tom-select**, reusing `assets/controllers/tom_select_controller.js` and the
  existing dark skin in `assets/styles/app.css` (§ „Tom Select dark skin", ~l. 814-846). Extend that
  controller if the option renderer needs more fields — **do not** fork a second controller.
- **Grouping via optgroups, „live" first, „ukončené" second.** Use the vocabulary already used in the
  UI („Probíhající" / „Ukončené" — pick one and be consistent; never the word „sázka").
- Each option shows the competition name, its match-source name as a subtitle, and the **date range**
  („2. 6. 2026 – 11. 6. 2026"). Dates are stored UTC and displayed in Europe/Prague — follow the
  project's datetime convention, do not format UTC directly.
- Searchable by competition name and source name.
- Selecting an option navigates to the same route with the new competition id — keep the existing
  `route` / `param` prop contract so `?soutez=<uuid>` keeps working.
- **Must work without JavaScript.** The current `<details>` version is JS-free; do not regress that.
  Progressive enhancement: a real `<select>` inside a `<form>` that submits on change, upgraded by
  tom-select. `tom_select_controller.js` already supports `data-tom-select-submit-on-change-value`.

### Contents — the answer from the product owner

*„The switcher has all including that what is in Moje soutěže."*

So the option list is **every competition the user is a member of** — it is not narrowed to some
subset, and it is the primary way to move between competitions. Keep sourcing it from
`ListMyCompetitions`.

### Logged-out variant

Item 05 puts this control on a page anonymous visitors can see. In that case the list is the
**public global competitions** (`ListDiscoverableGlobalCompetitions`), with no „my" grouping — group
by live/ended exactly the same way. Support this via a prop rather than a second component.

### Edge cases to preserve

The current component already handles these; do not lose them:
- zero competitions → renders nothing,
- exactly one competition → a static non-interactive chip, not a dropdown,
- unknown/foreign id in the URL → falls back to the first competition (the dashboard controller
  documents this as deliberate leak prevention — keep that property).

### Existing call site

`templates/portal/dashboard.html.twig` must keep working unchanged through this item. Item 06 rebuilds
that page; this item must not break it in the meantime.

## Styling

Per `.docs/ui-nav/PLAN.md` CSS discipline: reuse the tom-select skin and existing tokens first. Any
genuinely new rule goes at the **end** of the tom-select section under a
`/* --- item 04: competition switcher --- */` comment. Never reorder existing rules.

Note `app.css` currently hides the native control with
`select[data-controller~="tom-select"] { opacity: 0 }` — make sure the no-JS path is not left invisible
by that rule. This is the single most likely way to ship a broken control here; verify with JS disabled.

## Acceptance criteria

1. Logged in with several competitions: the control lists them all, grouped with live first, each
   showing name + source + date range, and is searchable.
2. Choosing a competition reloads the page scoped to it, and the URL carries the id.
3. With JavaScript disabled the control still renders visibly and still switches competitions.
4. One competition → static chip. Zero → nothing rendered. Foreign id → falls back, no data leak.
5. `/nastenka` still works exactly as before this item.
6. It is rendered on `/_design` (the styleguide is the shop window for shared components).

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → `tests/Integration/Portal` chunk → render checks on
`/nastenka` and `/_design`, **with and without JavaScript**. Update `UI-MAP.md` §3 (component
description) and §4 if the controller contract changed. Document the component's props in
`.docs/features/` if the pattern is reusable enough to deserve it (it is — a short doc, linked from
`CLAUDE.md`'s Features list, matching `team-picker.md`'s style). Update the status board row to DONE +
sha. Commit `UI: grouped tom-select competition switcher`, push to `main`.

---

## Assumptions made

Recorded per the PLAN protocol — each is the most conservative reading of a question the item
file did not settle.

1. **`route` must be path-parameter-free; the Žebříček call site moved to the resolver route.**
   A no-JS `<form method="get">` can only append a query string, so it can never fill a
   competition id into a *path* placeholder — which is what
   `route="competition_leaderboard" param="competitionId"` needed. Rather than invent a new
   generic „switch" endpoint, `LeaderboardController` (`/zebricek`, already the nav resolver)
   learned `?soutez=<id>` and redirects to that competition's board. The leaderboard call site
   now passes `route="leaderboard" param="soutez"`. `?soutez=<uuid>` on the Nástěnka is
   unchanged, as the item required. Both resolvers keep the „unknown/foreign id → primary
   soutěž" fallback.

2. **Live vs. ended is the source's `completedAt`, not „end date in the past".** The explicit
   domain flag (`matchSourceIsCompleted`) is the signal; a source whose planned `endAt` has
   passed but that nobody has closed still counts as „Probíhající". This also keeps the control
   clock-free, so the grouping is deterministic under the test MockClock.

3. **The logged-out feed is a prop, not a coupling.** The component accepts either
   `list<CompetitionListItem>` (the „my soutěže" read model) or a list of the new
   `App\Value\CompetitionSwitcherOption`, which any other read model — including the public
   competition list — maps to in its controller via `CompetitionSwitcherOption::fromDates()`.
   The item named `ListDiscoverableGlobalCompetitions` directly, but that query was being
   renamed/reshaped by the concurrent „Soutěže page" item at the time; depending on its class
   would have coupled this shared component to it. The five scalars are the contract instead.

4. **The current query params of the page are not carried over on switch.** Switching soutěž
   submits only `?<param>=<id>`; e.g. the Žebříček period tab (`?obdobi=…`) resets to the
   default. Preserving arbitrary query state would need hidden inputs whose correctness depends
   on the host page, and item 05 rebuilds that page anyway.

5. **The dropdown caret is scoped to this control.** `app.css` already colors
   `.ts-wrapper.single .ts-control::after`, but the app imports tom-select's *core* stylesheet,
   which never generates that pseudo-element — so no picker in the app has a caret today. The
   design shows the switcher as a dropdown, so the item-04 block generates the box for
   `.soutez-switcher` only. Giving every tom-select a caret is a real improvement but a
   cross-cutting visual change that belongs to its own item.

6. **The existing `my_competitions|length > 1` guards at both call sites were left alone.** The
   component itself renders the one-competition chip; whether a page wants it is the page's
   call, and item 06 rebuilds the Nástěnka.
