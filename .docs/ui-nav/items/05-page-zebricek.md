# Item 05 — „Žebříček" becomes a real, standalone, publicly viewable page

**Status:** TODO
**Depends on:** item 02 (round/kolo — for the „Poslední kolo" tab and the „KOLO" hero stat),
item 04 (the grouped tom-select switcher). Item 03 (fixtures) makes it far easier to verify.

---

## Why

„Žebříček" is a top-level nav item but today `portal_leaderboard` (`/portal/zebricek`) is only a
**redirector** to whichever competition the user happens to be in — a global-looking label with a
soutěž-scoped destination, and nothing at all for logged-out visitors. The product owner wants a real
page, driven by the competition picker, that works signed in and signed out.

## Reference design — read these before writing any markup

- `.docs/ui-nav/screenshots/img05-zebricek-a.png` — hero, switcher, „Tvoje pozice" strip, podium
- `.docs/ui-nav/screenshots/img04-zebricek-b.png` — podium + filter bar + table head
- `.docs/ui-nav/screenshots/img03-zebricek-c.png` — the full table
- `.docs/ui-nav/screenshots/img02-zebricek-d.png` — condensed „… pozice 13–24 …" gaps and the footer

There is also a static HTML prototype of this exact page at
`/Users/janmikes/www/wtips-design-system/project/pages/zebricek.html` (served at
`http://127.0.0.1:8765/pages/zebricek.html`). **Read it** — it is the source the screenshots came
from, and it will answer markup questions faster than guessing.

## The single biggest shortcut

Most of this page is **already styled and currently unused**. `assets/styles/app.css` contains, in the
Leaderboard section (~l. 518-568), classes written for this design that no template renders today:

- `.you-strip` and its children (`.ys-hero`, `.lbl`, `.pos`, `.ys-divider`, `.col .k/.v`, `.ys-delta`,
  `.ys-delta-lbl`, `.ys-delta-flat`) — this **is** the „Tvoje pozice" bar,
- `.lb-tabs` / `.lb-tab` / `.lb-tab.active` — this **is** the Celkem / Poslední kolo / Týden / Měsíc
  strip (the CSS comment even says so),
- `.lb-table`, `.lb-thead th`, `.lb-tr`, `.lb-tr.me`, `.lb-ty` („TY" badge), `.lb-acc-bar > i`
  (the účspěšnost bar), `.lb-delta-chip`, `.podium` / `.pod.gold|.silver|.bronze`.

**Reuse them.** Adding parallel classes for the same furniture is the specific failure this stream's
CSS discipline exists to prevent. The `Leaderboard/Podium` and `Leaderboard/Delta` components already
exist too.

## Routing

- New public route **`/zebricek`** (outside the portal firewall) renders the page. Competition is
  chosen with **`?soutez={uuid}`**.
- The nav „Žebříček" entry points here for **both** variants. Item 01 deliberately left the
  logged-out nav without this link because the page did not exist — **add it now** (in
  `templates/components/Layout/Nav.html.twig`, both desktop and mobile lists) and update the tests
  that pin the exact link sets (`tests/Integration/Auth/NavigationTest.php`).
- **Consolidate the leaderboard routes rather than preserving them.** There are no users yet and the
  stream owes no backwards compatibility (see `PLAN.md` conventions), so:
  - **Delete** `portal_leaderboard` (`/portal/zebricek`) — the old redirector. Do not alias it.
  - **Delete** `portal_competition_leaderboard` (`/portal/souteze/{competitionId}/zebricek`) and let
    `/zebricek?soutez={uuid}` be the single leaderboard URL. No redirect.
  - Move its three sub-pages under the new page so the whole feature lives in one place —
    `/zebricek/matice`, `/zebricek/clen/{userId}`, `/zebricek/shoda`, each carrying `?soutez={uuid}`
    (or an equivalent scheme you judge cleaner). They must keep **working**; they need not keep their
    URLs.
  - Fix every `path()` call, test and doc that referenced the deleted names, in the same commit.
    `grep -rn` for each route name before deleting it — nothing inside the app may 404.

## Content by state

### Logged out

- Switcher lists the **public global competitions** (item 04's logged-out variant).
- No „Tvoje pozice" strip (there is no „you").
- The hero CTA („Tipnout další zápas →" in the design) becomes a registration CTA — „Registrace
  zdarma" → `app_register`, matching the nav CTA the product owner asked for in item 01.
- No competition selected / none available → an `EmptyState`, not a blank page or an error.

### Logged in

- Switcher lists **all** the user's competitions, grouped live-first (item 04).
- „Tvoje pozice" strip renders from `GetMemberCompetitionStats` — which **already** returns rank,
  totalMembers, totalPoints, accuracyPercent, scoredCount/evaluatedCount, exactCount and streak.
  („do TOP 5 / do TOP 3" are the point gaps to those ranks — derive them from the leaderboard rows.)
- Own row in the table is highlighted with the existing `.lb-tr.me` + `.lb-ty` „TY" badge.
- A user viewing a public competition they are **not** a member of sees the table but no „Tvoje
  pozice" strip.

## Sections

Build in the order shown in the screenshots: hero (eyebrow, „Žebříček" headline, and the right-hand
stat row HRÁČŮ / ODEHRÁNO / KOLO / AKTUALIZACE) → switcher → „Tvoje pozice" strip → TOP 3 podium →
filter bar (search, period tabs, „Seřadit") → table → footer with the result count and paging.

**The period tabs are Celkem / Poslední kolo / Týden / Měsíc and all four must work** — the product
owner chose the full option. „Poslední kolo" uses the read side item 02 leaves behind (check that
item's „What landed" section for the exact call); Týden and Měsíc are time-window slices over
evaluated guesses and do not depend on item 02.

Table columns: POZICE · Δ · HRÁČ · BODY · ÚSPĚŠNOST (with the `.lb-acc-bar`) · PŘESNÉ · TREFA ·
STREAK. The „… pozice 13–24 …" condensed separator in `img02` is a real behaviour: when the viewer's
own row is far down the table, collapse the ranks in between rather than paginating them away.

## Guard rails

- **Authorization.** Anonymous visitors may only ever reach public global competitions. Respect the
  existing `leaderboard_view` voter; do not weaken it to make the public page work — extend it
  explicitly for the anonymous+global case and test that a private competition's leaderboard is not
  reachable by guessing its UUID.
- Only points/rank data belongs here. **Do not leak other members' tips** — that is governed by
  `TipVisibilityGate` / `CompetitionEntitlements` and is not part of this page.
- Feed „Rozložení tipů"-style data, if you show any, from `TipStatsProvider` batched per page —
  never per row (documented N+1 trap in `CLAUDE.md`).

## Acceptance criteria

1. `/zebricek` returns 200 logged out and shows a public global competition's table.
2. `/zebricek` logged in defaults to a sensible competition (live before ended) and shows the „Tvoje
   pozice" strip with the viewer highlighted in the table.
3. All four period tabs change the numbers and are linkable (state in the URL, not only in JS).
4. Switching competitions in the picker reloads the page scoped to it.
5. A private competition's leaderboard is **not** reachable anonymously by UUID.
6. `/portal/souteze/{id}/zebricek` and its `/matice`, `/clen/{userId}`, `/shoda` sub-pages still work.
7. The nav shows „Žebříček" in both variants and `NavigationTest` is updated to match.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → `tests/Integration/{Portal,Public,Auth}` chunks
(never `phpunit tests/` whole — OOM) → render checks on `/zebricek` logged out, logged in, with a
competition the viewer is not in, and on all four tabs. Update `UI-MAP.md` §1/§2/§3. Update the status
board row to DONE + sha. Commit `UI: standalone Žebříček page`, push to `main`.
