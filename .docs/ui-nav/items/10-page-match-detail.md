# Item 10 — Match detail (`/zapasy/{id}`) reworked

**Status:** DONE
**Depends on:** item 04 (switcher), item 05 (leaderboard patterns), B4 (the „why is my competition
missing" panel), B7 (match row layout). All are DONE.

---

## Why

The product owner redesigned the match detail page. Today it is „hero + Vaše tipy (one card per
competition)"; the new design turns it into the place where a single match is fully understood —
tip distribution, match timeline, and a per-match ranking — and it is a natural home for **two of
the three boosts**, each with a visible locked state and CTA.

Product owner: *„When bought booster or is premium, many the boosters can/will be (and more visible
+ CTA when does not have as on other pages probably) shown here."*

## Reference design

- `.docs/ui-nav/screenshots/img17-match-detail-unlocked.png` — entitled viewer (distribution visible)
- `.docs/ui-nav/screenshots/img18-match-detail-locked.png` — not entitled (distribution paywalled)

The two screenshots differ **only** in the Rozložení tipů card. Everything else is identical.

## Product-owner decisions (2026-07-29) — settled, implement as written

1. **One competition at a time, chosen with a switcher.** A match can belong to several of the
   viewer's competitions (that was bug B4), and members, scoring rules and boost entitlements all
   differ per competition — so every number on this page is scoped to one. Use the existing
   `SoutezSwitcher` (item 04, `.docs/features/competition-switcher.md`), listing **only the
   competitions that include this match** for this viewer. Remember its contract: **the `route` prop
   must be reachable with no path parameter** — a no-JS GET form can only append a query string, so
   scope with `?soutez={uuid}` on the match route and keep the unknown/foreign-id fallback.
2. **The tip form stays, directly below the hero.** This page remains where a player tips. The new
   analysis sections go around it, not instead of it.
3. **Team form is computed from the match source** — the „ARG · V2 R0 P0" sub-label. See below.
4. **The timeline's „tipovalo N hráčů" counts are dropped entirely** — *„we can completely drop off
   for now → will be implemented later with fantasy."* Render the timeline as pure match events. Do
   not invent a substitute number.

## Layout

### 1. Hero card

- Status pill top-left — „ŽIVĚ · 67'" in the screenshots; must also handle scheduled, finished,
  postponed and cancelled. Reuse the existing `Pill` variants.
- Meta line: round · venue. **`SportMatch` has exactly one `round` field and one `venue`** — the
  design's „Skupina A · Matchday 3 · Maracanã" is three fragments. Render `round` and `venue`; if a
  source encodes both a group and a matchday, that lives inside the single `round` string. **Do not
  invent a second round field.**
- „Zapsat výsledek" CTA top-right → `sport_match_set_score`, only for whoever may set a score today
  (check the existing voter; do not widen it).
- Teams: name, `<twig:TeamFlag>` coin, and the big score. Before kickoff, show the kickoff time in
  place of the score (the current page already does this).

### 2. Tip form — directly below the hero

Keep the existing tip entry (`Guess:GuessSubmitForm`), now scoped to the selected competition. Keep
its locked variant (B5) and its „Netipováno" wording.

**Keep B4's „Proč tu nejsou všechny vaše soutěže" panel.** It is still meaningful and is *not*
redundant with the switcher — the switcher lists competitions that **include** this match, the panel
explains the ones that **exclude** it. Make sure a viewer is never told both things about the same
competition.

### 3. Rozložení tipů (left) — gated by `BoostType::TipDistribution`

- Entitled: „✓ PRÉMIUM" pill + „ROZLOŽENÍ TIPŮ" eyebrow, „N hráčů tipovalo", and three labelled bars
  — „Výhra {home}", „Remíza", „Výhra {away}" — each with an absolute count and a percentage.
- Not entitled: gold „★ PRÉMIUM" pill, an „Odemknout →" CTA, the bars blurred behind a lock coin,
  headline „Uvidíš, jak tipuje konkurence" and a one-line explanation.

**Copy fix — do not transcribe the screenshot verbatim.** It reads „…počtem hráčů, kteří **vsadili**
na 1 / X / 2". `CLAUDE.md` and `DOMAIN.md` forbid gambling vocabulary — never „sázka" or its verb
forms. Use „…kteří tipovali 1 / X / 2".

This is the same entitlement `Match/TipStats` already renders elsewhere. **Feed it from
`TipStatsProvider`** — do not write a second path to the same data, and never a per-row query.

### 4. Průběh zápasu (right)

The existing match timeline (`MatchEvent`, `templates/portal/sport_match/_timeline.html.twig`):
minute, a colour-coded dot, and the event („Gól — Messi (ARG)", „Žlutá — Dembélé (FRA)", „Výkop").
**No tip counts** (decision 4). Hide the whole card when a match has no events yet.

### 5. Pořadí za zápas (bottom) — gated by `BoostType::OthersTips`

Eyebrow „POŘADÍ ZA ZÁPAS", headline „Nejvíc bodů z tohoto zápasu", and „N hráčů s tipem" on the right.
Table columns: **# · HRÁČ · TIP · PŘESNOST · BODY** — rank (top three get the coloured dot), avatar +
name, the player's tip („2:1"), an accuracy marker („PŘESNĚ" as plain text, „VÝSLEDEK" as a gold
pill), and points („+8", green).

This reveals **other players' concrete tips**, which is exactly what `BoostType::OthersTips`
(„Konkrétní tipy kolegů") sells. So it must be gated by the same entitlement, with the same
locked-state + CTA treatment as the distribution card. `OthersTips` includes `TipDistribution`, so a
viewer holding it sees both cards unlocked.

**Do not hand-roll the gate.** `Competition/TipVisibilityGate` composes the entitlement with the
deadline: a viewer sees others' tips iff entitled **or** past that match's deadline. That means once
the deadline has passed this table is visible to everyone — which is correct, and is why both
screenshots (a live match) show it unlocked. Managers and admins get **no** free pass
(`CompetitionEntitlements`, `$managersSeeTipsForFree`).

## Team form („ARG · V2 R0 P0")

Compute wins / draws / losses for each team from **finished matches in this match source**, using
`CompetitionMatchProvider` for the competition's scope so the numbers agree with everything else on
the page. Decide and record: whether it counts the whole source or only the current round (whole
source is the conservative reading), and what happens for a team with no finished matches (render
nothing rather than „V0 R0 P0"). One query for both teams — not one per team, and not per row.

## Guard rails

- **Every number must be real.** No placeholder counts, no hard-coded percentages.
- `TipStatsProvider` batched; never a per-match or per-row query.
- Premium XOR boosts — one `monetization` column; never render both funding models.
- Czech in the UI, English in code/comments. Never „sázka" or its verb forms.
- The page is logged-in only; keep it that way and keep its row in
  `tests/Integration/Security/AnonymousReachabilityTest` accurate.

## Acceptance criteria

1. `/zapasy/{id}` renders hero, tip form, distribution, timeline and per-match ranking.
2. The switcher lists exactly the viewer's competitions that include this match, and scopes every
   number on the page; `?soutez=` with an unknown or foreign UUID falls back without leaking.
3. Distribution and ranking each render both entitled and locked states, with a working CTA.
4. Past the tip deadline, others' tips are visible without a boost (`TipVisibilityGate`).
5. A match with no events hides the timeline; a match with no tips yet degrades gracefully.
6. Team form is computed, and absent rather than zeroed when there is nothing to compute.
7. B4's „why is my competition missing" panel still works and never contradicts the switcher.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → `tests/Integration/{Portal,Query,Command,Service,
Security,Auth}` chunks (never `phpunit tests/` whole — it OOMs). Then render, **in a browser**: an
upcoming match, a live match, a finished match; as an entitled and a non-entitled viewer; in a
premium competition and a boosts competition; and for a viewer in one competition and in several.
Update `UI-MAP.md` §2/§3. Update the status board row to DONE + sha. Commit
`UI: match detail — distribution, timeline, per-match ranking`, push to `main`.

---

## Assumptions made

Decisions the item file did not settle, taken on the most conservative reading and recorded here.

1. **Team form counts the WHOLE source (competition-scoped), not just the current round.** As the
   item suggested, this is the conservative reading. It is computed by the new
   `GetTeamForm` query through `CompetitionMatchProvider::applyCompetitionMatchFilter`, so a
   Teams-mode or Subset competition counts only the matches it really includes — one query for
   both teams, never one per team or per row. A team with no finished match is **absent** from the
   result (never „V0 R0 P0"); the short name alone still renders when the team has one.
2. **A draw that went to a shootout counts as a draw.** `SportMatch` documents the regular score as
   the primary result, so W/D/L reads `homeScore`/`awayScore` and ignores
   `overtimeHomeScore`/`overtimeAwayScore`.
3. **„Pořadí za zápas" also renders before the match is scored.** Evaluations are written when the
   final score lands, so a live or past-deadline match has tips but no points. Rather than hide the
   section (the reference screenshots show it on a LIVE match) or invent numbers,
   `GetMatchRanking` now lists every active guess, with `rank`/`totalPoints` **null** until the
   match is evaluated (`MatchRankingResult::$isScored`). In that state the page drops the
   #, PŘESNOST and BODY columns entirely — three columns of dashes say nothing — and titles the
   card „Jak tipovali ostatní" instead of „Nejvíc bodů z tohoto zápasu". Revealing those tips is
   still `TipVisibilityGate`'s decision, unchanged.
4. **PŘESNOST is derived, not stored.** „PŘESNĚ" = the exact score, „VÝSLEDEK" = the right 1/X/2
   outcome only. Computed in the template from the match's own final score (the same way the
   competition-scoped page already did), so no rule-points read model was needed.
5. **The state pill on the distribution card names WHY the split is readable.** `TipStats` gained
   an `entitled` flag (the provider already computed it), so the card can say „✓ PRÉMIUM" /
   „✓ ODEMČENO" when the viewer is entitled and „PO UZÁVĚRCE" when it is merely past the deadline —
   instead of claiming premium for a competition whose toggle is off.
6. **The locked „Pořadí za zápas" reuses `Boost:Panel` (`feature="others"`) for its CTA** rather
   than a second copy of the pricing / affordability / „soutěž už skončila" logic. It sits inside
   the same shell as the locked distribution card (blurred skeleton behind it), so both paywalls
   read as one treatment.
7. **The switcher gained a `routeParams` prop.** Its contract is that the COMPETITION can never be
   a path parameter; `/zapasy/{id}` carries the *match* there, which is constant across every
   option. `path(route, routeParams)` resolves it once and the no-JS GET form still only appends
   `?soutez=`. Documented in `.docs/features/competition-switcher.md`.
8. **The hero's meta line is „kolo · venue · datum".** The item named round and venue; the kickoff
   date was kept because a finished match would otherwise show no date anywhere. No second round
   field was invented.
9. **The kickoff row of the timeline carries no minute.** The design shows „1' · Výkop", but that
   minute is a convention rather than recorded data, so the cell is left empty.
10. **„Správa zápasu" stayed** (upravit / odložit / přesunout / zrušit / smazat). Only the score
    action moved out of it, into the hero's „Zapsat výsledek" CTA, so it is not offered twice.
