# Item 07 — „Soutěže" (`/souteze`) becomes the context-aware competitions page

**Status:** DONE
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

## Route freedom

There are no users yet (see `PLAN.md` conventions): rename or delete routes wherever it produces a
better structure, with no redirects. In particular `public_competitions_list_legacy` / the `/turnaje`
legacy redirect is a deletion candidate, and the `public_competitions_list` route name is a poor fit
now that the page is not merely public — rename it if a better name exists. Fix every `path()` call,
test and doc in the same commit; nothing inside the app may 404.

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

---

## Assumptions made

Product decisions the item file did not settle, resolved conservatively:

1. **The hero's scope.** „Aktivní soutěže / Hráčů celkem / Sledovaných zápasů" describe *what the
   page is about*: for a signed-in visitor their own world (competitions they play in ∪ organize),
   for an anonymous one the public list — which is the whole page they get. A visitor in nothing
   therefore sees honest zeroes, not the platform's global totals.
2. **The hero's sub-labels.** Computed, never constant, and omitted when they have nothing to say:
   „N živě teď" (falling back to „N zápasů dnes" — Prague calendar day), „+N tento týden"
   (memberships joined in the last 7 days), „Ve N turnajích" (distinct zdroje zápasů in scope).
3. **No prize-pool language anywhere.** The design's „N Kč v banku" / „rozděleno" card meta went
   the same way as the „VÝHERNÍ BANK" hero card the product owner cut: entry fees are burned
   credits and there are no payouts (DOMAIN.md). Cards show the entry fee („Vstupné X" / „Zdarma").
4. **„Stav" chips differ per context.** Discovery keeps the pre-existing rule that a global
   competition over a **completed** source is not listed at all (it cannot be joined) — so the
   public bar offers Všechny / Nadcházející / Probíhající and the organizer bar adds Skončené.
   `CompetitionStateFilter::forScope()` is the single place that decides.
5. **„Hledat" is a real filter.** The hero button anchors to the public list, whose filter bar
   carries a name search (`hledat` / `moje-hledat`, matched against the competition and its zdroj
   zápasů name). A button that only scrolled would have been a lie.
6. **The round delta is a points gain, never negative.** The design's „−3 v kole" cannot exist —
   points only accumulate. The card shows „+N v kole" (the viewer's points in the competition's
   current kolo, resolved exactly like `CompetitionRoundResolver`) and hides it at zero.
7. **Pagination over infinite scroll.** 12 cards per page, `strana` / `moje-strana`, „Zobrazit
   další" link. An out-of-range page clamps rather than 404-ing, so a stale shared link still works.
8. **`/turnaje` was deleted, not redirected** (PLAN's no-back-compat convention). `/turnaje/{id}`
   and the rest of the zdroj-zápasů tree are untouched.
9. **The styleguide (`/_design`) was not extended** with the new card/filter-bar. A parallel agent
   was mid-flight on that exact page (the `SoutezSwitcher` section and its test) and the stream's
   rule 1 says shared surfaces are not to be touched concurrently. Worth a follow-up.
