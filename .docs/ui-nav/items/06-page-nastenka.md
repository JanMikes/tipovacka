# Item 06 — „Nástěnka hráče" (rebuild of `/nastenka`)

**Status:** TODO
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
