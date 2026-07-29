# Item 07 — „Soutěže" (`/souteze`) becomes the context-aware competitions page

**Status:** TODO
**Depends on:** item 03 (fixtures) for realistic verification. Independent of 04/05/06.

---

## Why

The nav's „Soutěže" now points at `/souteze` for everyone (item 01). Today that route is a thin
public list with **no filters, no organizer view and no notion of the visitor's own competitions** —
and there is no „competitions I organize" page anywhere in the app. This item makes `/souteze` the
one place where every relationship to a competition lives: the ones you play in, the ones you
organize, and the ones you could join.

## Reference design

- `img16-souteze-hero.png` — hero + stat cards
- `img12-souteze-b.png` — „HRAJEŠ V / Soutěže, kde tipuješ"
- `img13-souteze-c.png` — the PIN join bar
- `img14-souteze-organizuji.png` — „ORGANIZUJEŠ / Tvé soutěže" heading
- `img15-souteze-global-list.png` — the filter bar + card grid
- `img11-souteze-a.png` — hero without the stat row

## Sections, in order

1. **Hero** — eyebrow „VÁŠ WORKSPACE", headline „Soutěže", the description line, and on the right
   „Hledat" + „Vytvořit soutěž".
   **Stat cards: AKTIVNÍ SOUTĚŽE · HRÁČŮ CELKEM · SLEDOVANÝCH ZÁPASŮ — with live data.**
   **Remove the „VÝHERNÍ BANK" card** (product owner, explicitly). Note that the design's sub-labels
   („2 živě dnes", „+38 tento týden", „Ve 3 turnajích") are real numbers too — either compute them or
   omit the sub-label, but **never render a plausible-looking hard-coded number**.
2. **„Soutěže, kde tipuješ"** — competitions the visitor is a member of. Each card shows TVÁ POZICE
   (`4. / 28`), BODY with the round delta, and a contextual footer („Tipuj do pá 20:00 · 3 zápasy" →
   „Tipuj 3", or „Další kolo · Osmifinále · odveta" → „Otevřít"). A LIVE pill where applicable.
3. **PIN join bar** — „Zadej 8místný kód a rovnou se připoj". The partial already exists:
   `templates/_partials/join_by_pin_form.html.twig` with the `pin_input` Stimulus controller. Reuse it.
4. **„Tvé soutěže" (ORGANIZUJEŠ)** — **rendered only if the visitor organizes at least one
   competition**, per the product owner. There is no owner-scoped query today (`ListMyCompetitions`
   carries an `isOwner` flag; the dashboard filters in the template) — add a proper query rather than
   filtering a full list in Twig.
5. **The public/global list** — competitions available to join.

## The shared card + filter component

Product owner's answer for `img15`: **„Both, one shared component."**

Build **one** card component and **one** filter-bar component, used by both section 4 and section 5,
differing only by context:

| | Organizer („Tvé soutěže") | Public/global |
|---|---|---|
| CTA | „Spravovat →" | „Připojit se" / „Otevřít" (member) |
| Filters | Sport · **Viditelnost** · Stav | Sport · Stav |
| Card meta | N hráčů · bank/entry fee · „% dokončeno" progress | N hráčů · entry fee or „Zdarma" |

The „% dokončeno" progress bar has no existing class; `.lb-acc-bar > i` is the closest existing
pattern (4px, `--grad-accent`) — reuse it rather than inventing a second progress bar.

Filters must be **server-side and linkable** (query params), not JS-only — the filter state should
survive a reload and be shareable. The result count („6 z 6 soutěží") reflects the active filters.

## Logged out

The same page, honestly scoped: the hero loses „Váš workspace" framing and the „Vytvořit soutěž"
button becomes „Registrace zdarma" (matching the nav CTA); sections 2, 3 and 4 are hidden; the public
list is the whole page, with per-card CTAs going to `app_login` / `app_register`. This is what
anonymous visitors already get from `public_competitions_list` — keep that behaviour intact and
additive.

## Performance warning

`ListDiscoverableGlobalCompetitionsQuery` currently runs a **per-competition COUNT inside the loop**
(a real N+1) and has no pagination or limit. This page makes that list much more prominent and adds
filters on top. Fix the N+1 as part of this item and add a sane limit/pagination — do not ship the
existing query behind a filter bar unchanged.

## Nav active state

Item 01 noted that no `portal_competition_*` route lights up „Soutěže", because matching is
route-prefix based against `public_competitions_list`. Now that this is the real hub, make the nav
highlight „Soutěže" for the competition routes too.

## Acceptance criteria

1. `/souteze` returns 200 logged out (public list only) and logged in (all sections).
2. „Tvé soutěže" appears only for users who organize something; „Soutěže, kde tipuješ" only for users
   who are members of something. Neither leaves an empty heading behind.
3. Filters are query-param driven, survive a reload, and the result count matches.
4. Every hero stat is real; no hard-coded numbers anywhere on the page.
5. The PIN bar joins a competition exactly as it does today.
6. The discoverable-competitions query issues a constant number of queries regardless of list length.
7. „Soutěže" is highlighted in the nav on competition pages.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → `tests/Integration/{Public,Portal,Auth}` chunks →
render checks logged out, logged in as a pure player, and logged in as an organizer; plus each filter
combination. Update `UI-MAP.md` §2/§3 and §6 (pain points 1 and 3). Update the status board row to
DONE + sha. Commit `UI: context-aware Soutěže page`, push to `main`.
