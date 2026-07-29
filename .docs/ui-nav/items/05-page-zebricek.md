# Item 05 — „Žebříček" becomes a real, standalone, publicly viewable page

**Status:** DONE
**Depends on:** item 02 (round/kolo — for the „Poslední kolo" tab and the „KOLO" hero stat),
item 04 (the grouped tom-select switcher). Item 03 (fixtures) makes it far easier to verify.

---

## Why

„Žebříček" is a top-level nav item but today `leaderboard` (`/zebricek`) is only a
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

- New public route **`/zebricek`** renders the page. Competition is chosen with **`?soutez={uuid}`**.
  Item 09 removed the `/portal` firewall prefix, so „public" is no longer a matter of which prefix
  the path carries: the whole app sits behind one firewall and the authenticated pages carry
  `#[IsGranted('ROLE_USER')]` on the controller. A public leaderboard therefore simply **omits**
  that attribute — and must add its own row to
  `tests/Integration/Security/AnonymousReachabilityTest`, which pins who may reach what.
- The nav „Žebříček" entry points here for **both** variants. Item 01 deliberately left the
  logged-out nav without this link because the page did not exist — **add it now** (in
  `templates/components/Layout/Nav.html.twig`, both desktop and mobile lists) and update the tests
  that pin the exact link sets (`tests/Integration/Auth/NavigationTest.php`).
- **Consolidate the leaderboard routes rather than preserving them.** There are no users yet and the
  stream owes no backwards compatibility (see `PLAN.md` conventions), so:
  - **Delete** `leaderboard` (`/zebricek`) — the old redirector. Do not alias it.
  - **Delete** `competition_leaderboard` (`/souteze/{competitionId}/zebricek`) and let
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
6. `/souteze/{id}/zebricek` and its `/matice`, `/clen/{userId}`, `/shoda` sub-pages still work.
7. The nav shows „Žebříček" in both variants and `NavigationTest` is updated to match.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → `tests/Integration/{Portal,Public,Auth}` chunks
(never `phpunit tests/` whole — OOM) → render checks on `/zebricek` logged out, logged in, with a
competition the viewer is not in, and on all four tabs. Update `UI-MAP.md` §1/§2/§3. Update the status
board row to DONE + sha. Commit `UI: standalone Žebříček page`, push to `main`.

---

## What landed

- **`/zebricek` is a real page** — `src/Controller/Public/LeaderboardController.php` +
  `templates/public/leaderboard.html.twig`. No `#[IsGranted]`; the audience is the
  `leaderboard_view` voter's decision. Sections in the screenshots' order: hero (eyebrow,
  „Žebříček", HRÁČŮ / ODEHRÁNO / KOLO / AKTUALIZACE) → `SoutezSwitcher` (+ „Registrace zdarma"
  when logged out) → `.you-strip` → `Leaderboard:Podium` → filter bar → table → footer.
- **Routes consolidated, not aliased.** `competition_leaderboard*` and the old `/zebricek`
  redirector are **deleted**. The sub-pages are `leaderboard_matrix` (`/zebricek/matice`),
  `leaderboard_member` (`/zebricek/clen/{userId}`) and `leaderboard_resolve_ties`
  (`/zebricek/shoda`), each scoped by `?soutez={uuid}` via the
  `ResolvesLeaderboardCompetition` trait (missing/invalid id → 404). Every `path()` call, the
  two notification handlers and every test were fixed in the same commit.
- **`LeaderboardVoter` gained a second attribute rather than being weakened.**
  `leaderboard_view` = member/owner/admin **or anybody when `Competition.isGlobal`**;
  the new `leaderboard_details` keeps the old member-only rule and guards the two
  tip-revealing sub-pages, so widening the public board could not widen them.
- **All four period tabs.** `LeaderboardTimeFilter` gained `Last30Days` (`?obdobi=mesic`,
  „Měsíc"), and `Last7Days` is now labelled „Týden" (its `longLabel()` still spells the window
  out). „Poslední kolo" renders only when `GetCompetitionCurrentRound` returns a round, and so
  does the KOLO hero stat.
- **The condensed board.** `Service/Leaderboard/LeaderboardTableBuilder` +
  `Value/LeaderboardTable(Entry)` do search (`?hledat`), sort (`?razeni`, `Enum/LeaderboardSort`
  — display order only, POZICE stays the real rank) and the „… pozice 13–24 …" fold
  (head 12 + viewer ±2 + tail 2, `?vse=1` expands). No pagination — paging would hide the
  viewer's own row behind a button.
- **New read query** `GetCompetitionMatchProgress` (one aggregate, competition match scope
  respected) for the „ODEHRÁNO 38 / 64" hero stat.
- **Deleted** the `Leaderboard:CompetitionLeaderboard` Live Component: every bit of the table's
  state is now a query parameter, so a live island bought nothing and would have duplicated it.
- **Fixed in passing:** `portal/leaderboard/resolve_ties.html.twig` referenced an undefined
  `tiedCompetitions` (the controller passes `tiedGroups`) and 500'd on every render.
- **CSS:** one block, `/* --- item 05: Žebříček page --- */`, at the end of the leaderboard
  section — `.lb-toolbar`, `.lb-search`, `.lb-sort`, table gutters, `.lb-gap`, `.lb-foot`.
  Everything else reuses `.you-strip`, `.lb-tabs`, `.lb-table`, `.lb-tr.me`, `.lb-ty`,
  `.lb-acc-bar`, `.podium`.
- **Tests:** `tests/Integration/Public/PublicLeaderboardFlowTest.php` (10 cases — every
  acceptance criterion, incl. the private-competition-by-UUID guard and the sub-pages being
  refused anonymously) and `tests/Unit/Service/LeaderboardTableBuilderTest.php` (8 cases).
  `AnonymousReachabilityTest`, `NavigationTest`, `SoutezSwitcherFlowTest`,
  `CompetitionLeaderboardFlowTest`, `PodiumFlowTest` and `LeaderboardDeltaFlowTest` updated.

## Assumptions made

1. **A competition the viewer may not see falls back; it does not 403.** The nav entry carries
   no id, so the page must always land on something. The private competition's name, members
   and detail link never appear — only the id the visitor typed themselves echoes back in
   `<meta og:url>`. This matches the switcher's documented leak-prevention contract.
2. **The „Tvoje pozice" strip is derived from the same filtered board as the table**, not from
   `GetMemberCompetitionStats` as the item suggested. `LeaderboardRow` already carries rank,
   points, Δ, accuracy, exact and streak, and reading one board keeps the strip from
   contradicting a re-ranked window tab (the reason the old controller did it this way).
3. **„Měsíc" is a rolling 30-day window** over match kickoffs, the same shape as „Týden".
4. **„Seřadit" is a real `?razeni` select**, applied server-side, changing display order only.
   The design shows a dropdown with no submit; the form carries a „Použít" button so it works
   without JavaScript.
5. **The anonymous switcher lists the same scope as `/souteze` discovery**
   (`CompetitionBrowseScope::Discoverable`), i.e. global competitions over a source that is not
   completed. A finished global competition's board stays reachable by URL — it is simply not
   offered in the picker, exactly as it is not offered on the discovery page.
6. **The logged-in default is the viewer's primary (most recently joined) soutěž, live before
   ended** — the same choice the Nástěnka makes, so both pages agree on „your" soutěž.
7. **A logged-in user in no soutěž gets the public feed** rather than an empty page.
8. **`/zebricek` stays behind the unverified-email airlock.** The airlock is an allow-list and
   an unverified account is mid-onboarding; anonymous visitors are unaffected (the subscriber
   only fires for a logged-in user), so the public page is genuinely public.
9. **The page controller lives in `src/Controller/Public/`**, keeping item 09's invariant that
   every controller under `src/Controller/Portal/` carries `#[IsGranted('ROLE_USER')]` true. The
   three members-only sub-pages stayed in `Portal/Leaderboard/`.
