# Item 06 — „Nástěnka hráče" (rebuild of `/nastenka`)

**Status:** DONE
**Depends on:** item 04 (switcher). Item 03 (fixtures) makes verification realistic.

---

## Why

`/nastenka` is a 564-line mixed feed — a personal summary, a competition list, a **zdroj zápasů**
list and a match feed all stacked with equal weight. The nav now calls it „Nástěnka hráče", so it
should be the player's home: one competition in focus, chosen with the switcher, everything else
following from it.

**Logged-in only** — it already sits behind the portal firewall; keep it there.

## Reference design

- `.docs/ui-nav/screenshots/img06-nastenka-full.png` — the whole page
- `.docs/ui-nav/screenshots/img07-nastenka-hero-right.png` — the „Tvoje pozice" card for the hero
- Static prototype: `/Users/janmikes/www/wtips-design-system/project/pages/dashboard-hrac.html`
  (`http://127.0.0.1:8765/pages/dashboard-hrac.html`) — **read it**, the screenshots came from it.

## The switcher scopes the page

Product owner's answer: *„The switcher has all including that what is in Moje soutěže."* So the
picker (item 04) lists **every** competition the user is in, and it is the control that drives the
page. The existing `?soutez={uuid}` contract on this route already works this way — keep it,
including the deliberate fallback when the id is unknown or foreign (the current controller documents
this as leak prevention; do not lose it).

„Moje soutěže" stays a full cross-competition list — it is the overview, not a filtered echo.
**Flag this back to the product owner in your summary** rather than silently deciding otherwise: the
answer settled what the *switcher contains*, and this is the one reading of it that leaves both the
switcher and the section useful.

## Sections, in order

1. **Hero** — eyebrow „NÁSTĚNKA", headline „Ahoj, {nickname}.", the „ZOBRAZENÁ SOUTĚŽ" switcher, and
   the one-line subtitle. On the right, the **„Tvoje pozice" card** from `img07`: competition name,
   big rank `7.` `/42`, then BODY / ZMĚNA / DO TOP 5. Same data as the Žebříček „Tvoje pozice" strip
   (`GetMemberCompetitionStats` + leaderboard rows for the gap) — if item 05 has landed, reuse
   whatever it factored out rather than computing it twice.
2. **„Poslední Tvoje tipy"** — recent evaluated tips: day label, teams with flags, final score, my
   tip, points won. Feed from `ListRecentEvaluatedGuessesForUser`; the current dashboard already
   renders this shape at l. 407-475 with `Match:MatchRow state="finished"`.
3. **„Moje soutěže"** + a „Historie →" link — the competition cards.
4. **„Následující zápasy"** — the filter chip row (Vše / Live / Dnes / Tipovatelné / Ukončené, each
   with a count) plus a „SOUTĚŽ" dropdown. Match rows carry MŮJ TIP and „Detail zápasu", and each row
   shows the „Rozložení tipů" strip.
5. **„Odehrané zápasy"** (left column) beside a **„Žebříček" sidebar** (right column) — the mini
   leaderboard with the „Všichni · N" / „Přátelé" toggle and „Celý žebříček →" pointing at item 05's
   page. The current dashboard's `.lb-row` mini-leaderboard markup (l. 92-146) is the right starting
   point.

## Rozložení tipů — the entitlement rule

The design shows the distribution bar in two states: an unlocked bar, and a locked gold „PRÉMIUM ·
DISTRIBUCE TIPŮ · Uvidíš, jak tipuje N hráčů · Odemknout →" placeholder. That is exactly what
`Match/TipStats` + `TipStatsProvider` already produce, driven by `CompetitionEntitlements` (premium
for everyone, or the viewer's own bought boost).

**Resolve it once per page via `TipStatsProvider`, never per row** — `CLAUDE.md` calls the per-match
query an N+1 and the batch provider O(competitions). This is the single easiest way to wreck this
page's performance.

## What to drop

- **The „PŘIPOJIT SE K SOUTĚŽI" PIN bar** — product owner, 2026-07-29: *„Remove 'PŘIPOJIT SE K
  SOUTĚŽI' from the dashboard."* It is the
  `include('_partials/join_by_pin_form.html.twig', …)` around current l. 171-174. **Remove the
  include from this page only** — the partial itself, the `pin_input` Stimulus controller and the
  join route all stay, because item 07 already gives the PIN bar a permanent home on `/souteze`
  (section 3, matching `screenshots/img13-souteze-c.png`). Joining a competition is a Soutěže-page
  action, not something the player's home needs to advertise every day.
- **„Moje zdroje zápasů"** (current l. 248-311) — a normal player never owns a zdroj; it belongs on
  the Soutěže page's organizer side (item 07), not here.
- **„Objev další soutěže"** (current l. 477-561) — it is a near-duplicate of
  `templates/public/competitions_list.html.twig` l. 70-144 and belongs on the Soutěže page (item 07).
  Do not delete the underlying query; item 07 needs it.
- The three count-only `StatCard`s (current l. 148-169) — superseded by the „Tvoje pozice" card.

## Acceptance criteria

1. `/nastenka` is logged-in only and returns 200 with every section above.
2. The switcher lists all the user's competitions and reloads the page scoped to the chosen one;
   `?soutez=` with a foreign or unknown UUID falls back without leaking anything.
3. A user with **zero** competitions gets a sensible empty state with a create/join CTA — not a
   broken hero. A user with exactly one gets the static chip, not a dropdown.
4. Locked and unlocked „Rozložení tipů" both render correctly, and the page issues no per-match tip
   stats query.
5. The match filter chips filter, and their counts match what they filter to.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → `tests/Integration/Portal` chunk → render checks
for: several competitions, exactly one, zero, a premium competition, a boosts competition where the
viewer has and has not bought. Update `UI-MAP.md` §2/§3 and §6 (pain points 1 and 3 are addressed
here). Update the status board row to DONE + sha. Commit `UI: Nástěnka hráče`, push to `main`.

---

## What landed

- **`/nastenka` is the player's home.** `src/Controller/Portal/DashboardController.php` (still
  `#[IsGranted('ROLE_USER')]`, route name `dashboard`, already declared in
  `AnonymousReachabilityTest`) + a rewritten `templates/portal/dashboard.html.twig` (564 → ~320 l.)
  with two page partials, `portal/_dashboard_match_row.html.twig` and
  `portal/_dashboard_leaderboard_row.html.twig`, so the two match lists and the two žebříček row
  kinds can never drift apart.
- **Sections in the screenshots' order:** hero (eyebrow „NÁSTĚNKA", „Ahoj, {nickname}.",
  `<twig:SoutezSwitcher route="dashboard" param="soutez">`, subtitle, and the **„Tvoje pozice"
  `.hero-rank` card** from `img07`) → „Poslední Tvoje tipy" (+ „Historie →") → „Moje soutěže"
  → „Následující zápasy" (chip row + „SOUTĚŽ" control) → „Odehrané zápasy" beside the
  „Žebříček" sidebar (+ „Celý žebříček →" `/zebricek?soutez=<id>`, item 05's page).
- **Dropped, as instructed:** the „PŘIPOJIT SE K SOUTĚŽI" PIN bar (the include only —
  `_partials/join_by_pin_form.html.twig`, the `pin_input` controller and `/pripojit*` all stay and
  are still rendered on `/souteze`), „Moje zdroje zápasů", „Objev další soutěže", and the three
  count-only `StatCard`s. `ListMyOwnedMatchSources` and `ListBrowsableCompetitions` were left
  alone — the sections moved, the read models did not die.
- **One match feed, batched.** The page reads `ListUserMatches`, which gained an optional
  `competitionId` scope (applied on the membership join, so the tip-stats pairs follow from it).
  „Rozložení tipů" therefore comes from a single `TipStatsProvider::forPairs` batch — there is no
  per-match path on this page at all. `ListUpcomingMatchesForUser` was **deleted**: the dashboard
  was its only caller and `ListUserMatches` strictly supersedes it (so was the now-unused
  `SportMatchRepository::listUpcomingForUser`).
- **„Můj tip" on an upcoming row.** `UserMatchItem` gained `myHomeScore` / `myAwayScore`, filled
  from the same single guess query, but **only when exactly one** of the viewer's soutěže includes
  the match (two soutěže can hold two different tips) — always the case when the query is scoped.
  Without it a row read „Tip odeslán" next to an empty „+ Zadat tip" slot.
- **„Tvoje pozice" is shared with item 05.** `LeaderboardController`'s private `gapToRank()` and
  its me-row loop moved into `App\Value\LeaderboardStanding` (`fromRows()` → row, playerCount,
  gapToTop3, gapToTop5); both pages now derive the standing from the one board they already load,
  so `/zebricek` and `/nastenka` can never contradict each other.
- **The default soutěž now matches `/zebricek`:** the most recently joined **running** soutěž, a
  finished one only when nothing is running. Before this the Nástěnka happily opened on a
  completed competition while the žebříček showed a live one.
- **CSS:** one block at the end of `@layer components`, `/* --- item 06: Nástěnka --- */` —
  `.hero-rank*`, `.result-row*` and `.mf-bar` / `.mf-scope` / `.mf-count` / `.lb-tab.has-count`.
  Everything else reuses `.card-glass`, `.tip-row` (Match:MatchRow), `.lb-row`/`.lb-ty`/`.lb-pos`,
  `.lb-tabs`/`.lb-tab`, `.result-tip`, `.stat-lbl`. `.lb-tab` itself was NOT modified — the count
  badge is an opt-in `.has-count` modifier.
- **Tests:** `DashboardFlowTest` rewritten (11 cases — every acceptance criterion, incl. the
  foreign-UUID fallback, both empty/one-soutěž edge states, chip counts vs rendered rows, and a
  profiler-based guard that the query count does not scale with the match count),
  `DashboardStatsFlowTest` retargeted at the hero card, `TipStatsSurfacesTest` now visits
  `/nastenka?soutez=<boosts>` because the page is soutěž-scoped.
- **Verified by rendering** (dev fixtures, `localhost:58080`): six soutěží (dev user), exactly one
  (`miska@tipovacka.dev` → static chip, no dropdown), zero (empty state + create/join CTA),
  premium („Sousedský pohár" — unlocked bars), boosts owned („Tipovačka MS 2026" — unlocked) and
  boosts not owned („Fandíme Česku" — the gold „Odemknout za 10 kr." placeholder).

## Assumptions made

1. **„Moje soutěže" stays the full cross-soutěž list**, as the item file directs — everything else
   on the page is scoped by the switcher. **Flagging it to the product owner** as instructed: this
   is the one reading that leaves both the switcher and the section useful, but it does mean one
   section on the page deliberately ignores the control at the top of it. The selected soutěž's
   card is marked „Zobrazená" and every other card carries a „Zobrazit na nástěnce" link, so the
   relationship between the two is at least visible.
2. **The „SOUTĚŽ" dropdown widens, it does not re-scope.** The design shows a „Všechny soutěže"
   dropdown in the filter bar, which would duplicate the hero switcher if it listed soutěže again.
   It is therefore a two-option scope control on `?zapasy=` — the soutěž in focus (default) or
   „Všechny soutěže" — affecting **only** the two match lists, and it is not rendered at all for a
   viewer with a single soutěž. A real GET form with a „Použít" button, so it works without JS
   (same precedent as item 05's „Seřadit").
3. **The chips filter the whole scoped list; „Odehrané zápasy" is its own section.** „Vše" counts
   every scoped match (upcoming first, then finished — `SportMatchRepository::listAllForUser`
   already orders it that way), so the „Ukončené" chip and the „Odehrané zápasy" section below
   show the same rows. The static prototype does the same thing; dropping the „Ukončené" chip the
   item names felt like the bigger deviation.
4. **No „Přátelé" toggle in the žebříček sidebar.** The design's „Všichni · N / Přátelé" pair needs
   a friends concept, and the domain has none — inventing one would be a new business rule, which
   this stream explicitly is not for. The sidebar renders the „Všichni · N" chip only.
5. **„Historie →" points at `/zebricek/clen/{me}?soutez=<id>`** — the member breakdown, which *is*
   the history of the viewer's tips in the soutěž in focus. It is guarded by `leaderboard_details`,
   and the viewer is a member of the soutěž by construction, so it is always reachable from here.
6. **„Odehrané zápasy" shows the last 5 finished matches of the soutěž**, with the viewer's own tip
   and the points it earned joined in from the already-loaded evaluated-guess list (indexed by
   match id in the controller — no per-row lookup).
7. **A viewer with no row on the board yet gets no hero card** rather than a „0." placeholder; the
   rest of the page renders normally.

## Product-owner confirmation (2026-07-30)

- **„Přátelé" toggle: confirmed dropped.** Product owner: *„Přátelé — ignore it."* The žebříček
  sidebar renders only „Všichni · N". There is no friends concept in the domain and none is to be
  invented; this is no longer an open assumption.
