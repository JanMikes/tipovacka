# Item 11 — Match rows: one card, boosts inside it

**Status:** DONE
**Depends on:** item 10 (match detail) — **run it after**, they both touch match/tip rendering,
`Match/TipStats` and `assets/styles/app.css`.

---

## Why

Product owner, 2026-07-29: *„Design of the matches rows (for example dashboard, and in competition
detail) does not match design. These now are separate cards and different design … it must be within
one card the boosters. Compare the designs and design accordingly."*

## The comparison

- **Current:** `.docs/ui-nav/screenshots/img19-matchrow-current.png`
- **Expected:** `.docs/ui-nav/screenshots/img20-matchrow-expected.png`

Read both before writing anything. The differences, in order of importance:

| | Current | Expected |
|---|---|---|
| **Structure** | Match row is one card; the „Rozložení tipů" paywall is a **separate card below it**; the competition name is loose text under both | **ONE card** containing the match line, a divider, and the boost strip inside it |
| **State** | no accent edge | a coloured **left accent stripe** on the card marks the match state |
| **My tip** | a dashed „TIPNOUT / + Zadat tip" box *plus* a separate „Tipovat →" button | a single bordered **„MŮJ TIP / 2 : 1"** box, with the points won as a small **„+5" badge overlapping its top-right corner** |
| **Boost strip** | plain bar, „Odemknout za 10 kr. →" | gold treatment **inside the card**: „★ PRÉMIUM" pill · „DISTRIBUCE TIPŮ" · a lock coin with „Uvidíš, jak tipuje N hráčů" · „Odemknout →", over a diagonally hatched bar standing in for the hidden distribution |
| **Meta** | „1. KOLO" under the time; competition name below the card | round under the time („SKUPINA A · …", truncated); no loose text |

## Most of this already exists — reuse, do not rebuild

`assets/styles/app.css` already has the **`.tip-card`** family (~l. 435-516), which is much closer to
the expected design than the `.tip-row` the pages currently use:

- `.tip-card` (+ `.accent`, `.muted`), `.tip-head`, `.tip-stage`, `.tip-teams`, `.tip-team`, `.tip-vs`,
  `.flag`, `.tip-inputs`, `.score-input`, `.final-score`, `.result-banner`
- **`.tip-stats-locked`** (+ `.is-premium`, `.is-muted`), `.tip-stats-lock`, `.tip-stats-copy`,
  `.tip-stats-eyebrow`, `.tip-stats-teaser`, `.tip-stats-cta` — the gold paywall strip
- `.dist-bar` (`.b1`/`.bx`/`.b2`) and `.dist-pcts` — the **unlocked** 1/X/2 distribution

So the job is mostly **composition**: put the tip-stats strip *inside* the match card, below a
divider, and give the card its state stripe. Prefer extending these classes over inventing a parallel
set — that is the specific failure this stream's CSS discipline exists to prevent.

**Do not undo B7.** B7 rebuilt `.tip-row` as a wrapping flex row of four zones after measuring that
no media query could work (the same component renders in columns from 632 to 1088 px, and competition
detail is *narrower at 1440 px than at 1024 px* because of the aside). Whatever you build must keep
that property: **container-relative, no overlap and no horizontal overflow at any width**, with long
team names handled deliberately. Re-run B7's kind of check — measure, do not eyeball.

## Scope — every surface

`Match/MatchRow` + `Match/TipStats` are shared. Fix the components once and verify **all** of:
`/nastenka` (which also has `templates/portal/_dashboard_match_row.html.twig`), `/souteze/{id}`,
`/zapasy`, and `/zapasy/{id}`.

## States to cover

The card must be right in each of these, since the screenshots only show two:

1. **Upcoming, no tip** — „+ Zadat tip" affordance; kickoff time instead of a score.
2. **Upcoming, tip filled** — „MŮJ TIP 2 : 1", no points badge yet.
3. **Live** — live state stripe/pill, running score.
4. **Finished, evaluated** — final score, „MŮJ TIP" plus the **points badge** („+5").
5. **Locked / past deadline** — B5's treatment: no editable affordance, „Netipováno" where nothing
   was filled. Do not regress it.
6. **Boost strip, entitled** — the real `.dist-bar` percentages instead of the hatched placeholder.
7. **Boost strip, not entitled** — the gold paywall in the screenshot.
8. **No boost strip at all** — a competition where tip stats do not apply must not leave an empty
   divider or a stray gutter.

Per B7, the „MŮJ TIP" box and the state pill link to the guessing surface while the match is
tippable, and become plain text once locked (no dead links).

## Open question for the product owner — ask, do not guess

The expected design labels the strip **„DISTRIBUCE TIPŮ"**, while the app (and `CLAUDE.md`) call this
surface **„Rozložení tipů"** — the current screenshot shows the latter. They are two names for one
thing. **Flag it and keep „Rozložení tipů" unless told otherwise**, since that is the documented
vocabulary; record the question in `## Assumptions made`.

## Guard rails

- `TipStatsProvider` batched per page, **never per row** — moving the strip inside the card must not
  turn a batched lookup into an N+1. This is the documented trap in `CLAUDE.md`.
- The entitlement itself comes from `CompetitionEntitlements` / `TipVisibilityGate`; do not re-derive
  who may see what.
- Prices come from `Credits/PricingConfig` — the „Odemknout za N kr." amount is never a literal.
- Czech in the UI, English in code/comments. Never „sázka" or its verb forms.

## Acceptance criteria

1. On every surface, a match and its boost strip render as **one** card, divider between them.
2. No separate paywall card, and no loose competition-name text outside the card.
3. All eight states above render correctly.
4. Zero overlap and zero horizontal overflow at every width — measured, on each surface.
5. No per-match tip-stats query on any page.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → `tests/Integration/{Portal,Public,Query,Security}`
chunks (never `phpunit tests/` whole — it OOMs). Then a **browser** pass over all four surfaces at
desktop and narrow widths, in each of the eight states. CSS discipline: reuse first; new rules at the
END of the section they belong to under `/* --- item 11: match card --- */`; never reorder existing
rules. Update `UI-MAP.md` §3 and the status board row to DONE + sha. Commit
`UI: match rows become one card with the boost strip inside`, push to `main`.

---

## As built

`Match/MatchRow` is now the **card**, not the row:

```
.tip-row                     ← the card; keeps its border, radius, glass surface,
  ├── .tip-row-line          ←  the spotlight hooks and now a 4 px state stripe
  │     [ čas/kolo ] [ pilulka ] [ a.tip-row-match ] [ .tip-row-end ]   ← B7's four zones, verbatim
  └── .tip-row-extra         ← divider + „Rozložení tipů" + a small foot note
```

- **`tipStats` is a prop** (`list<TipStats>`), so the strip is composed *inside* the component
  from the SAME batch the pages already resolved (`TipStatsProvider`) — no second data path, no
  per-row query. `Match/TipStats` `compact=true` may no longer be placed anywhere else.
- **`footNote`** carries what used to be loose text under the card: „zdroj · venue" on
  `/nastenka` + `/zapasy`, „Uzávěrka …" on competition detail.
- The separate **„Tipovat →" action is gone**; the fixture (`a.tip-row-match`) links to the match,
  so a locked card is still not a dead end, and the „MŮJ TIP" box keeps B7's rule (link while
  tippable, plain text once locked).
- **Points render as a „+5" badge** overlapping the box's corner (`.my-tip-pts`, muted at 0),
  replacing the old „5 / bodů" block.
- New CSS lives at the end of `@layer components` under `/* --- item 11: match card --- */`;
  the `.tip-stats-*` family was **extended** (gold paywall, `.tip-stats-head`, `.tip-stats-note`,
  `.tip-stats-ghost` hatched stand-in), not duplicated.

**Measured, not eyeballed** (B7/B8's harness — bounding-box intersection over painted leaves plus
horizontal-overflow checks, headless Chrome): 7 surfaces × 10 widths (1600 → 360) = 70 combinations,
zero overlap, zero horizontal overflow, no strip outside a card, no empty divider. The one
deliberate overlap (the points badge over its own box corner) is excluded by name.

---

## Assumptions made

Decisions the item file did not settle, taken on the most conservative reading and recorded here.

1. **OPEN QUESTION for the product owner — „Rozložení tipů" vs „DISTRIBUCE TIPŮ".** The expected
   design labels the strip „DISTRIBUCE TIPŮ"; the app, `CLAUDE.md` and `DOMAIN.md` call it
   **„Rozložení tipů"**. As instructed, the documented vocabulary was kept everywhere — the strip,
   the full card (item 10), the boost description and the žebříček all say „Rozložení tipů". If the
   product owner prefers „Distribuce tipů", it is one word in
   `templates/components/Match/TipStats.html.twig` plus `Boost:Panel`, `DOMAIN.md` and `CLAUDE.md` —
   but it must change in ALL of them at once, not only in the card.
2. **The price stays in the CTA: „Odemknout za 10 kr. →", not the design's bare „Odemknout →".**
   The item's own guard rail phrases the rule as „the „Odemknout za N kr." amount is never a
   literal", i.e. the amount is expected to be there and to come from `Credits/PricingConfig` —
   which it does. Dropping it would hide the cost until the confirm dialog.
3. **The gold treatment applies to BOTH paid paywalls, not just premium.** The screenshot shows
   „★ PRÉMIUM"; a boosts competition gets the same gold skin with a „Vylepšení" pill (sparkles
   instead of a crown). Gold already means „paid feature" on the item-10 card (`.dist-unlock`), and
   two different paywall colours for one feature would read as two different features. The
   nothing-to-sell variant („Zobrazí se po uzávěrce") stays neutral grey — it sells nothing.
4. **The strip switched from vykání to tykání** („Uvidíš, jak tipuje 6 hráčů"), matching both the
   expected design and item 10's full card, which already said „Uvidíš, jak tipuje konkurence". The
   verb now agrees with the count as well (1 hráč tipuje · 2–4 hráči tipují · 5+ hráčů tipuje) — the
   old copy said „Uvidíte, jak tipuje 3 hráči".
5. **„Netipováno" in the „MŮJ TIP" box is competition detail only.** B5 settled that the
   cross-competition rows (`/nastenka`, `/zapasy`) keep „Uzamčeno", because a row that aggregates
   several soutěže cannot honestly claim the viewer never tipped. So `tipMissingLabel` is a prop and
   only competition detail — which knows exactly one soutěž — passes it. Elsewhere a locked,
   untipped card simply has an empty end zone, exactly as before.
6. **The fixture became the link to the match.** Removing the „Tipovat →" action would otherwise
   leave a locked/finished card with no way to reach `/zapasy/{id}`. Wrapping the teams block in an
   `<a>` (with an accessible name „Česko – Brazílie — Detail zápasu") keeps the target reachable
   without a second visible button and without nesting anchors. `MatchRowTipLinksTest` was updated
   from `.tip-row-actions a` to `a.tip-row-match`.
7. **`state` gained a `live` value** so a running match can paint its own stripe instead of
   borrowing the grey „locked" one, and the score slot now shows a result whenever both scores are
   present (previously only when `finished`). A live match with no score entered still shows the
   kickoff time — inventing a running score was not an option.
8. **The kickoff DAY was kept above the time.** The design mock shows only „18:00"; these lists span
   many days, so dropping the date would lose information. The meta line under it stays the round,
   ellipsized with a `title` (the mock's „SKUPINA A · …" is the same truncation).
9. **B7's wrap hint was re-measured, not removed.** `.tip-row-match`'s `flex-basis` went 360 → 320 px
   because the end zone lost the ~132 px „Tipovat →" button; the zones still wrap (no media query
   sees the column width) and long names still ellipsize. Without it every card on competition
   detail wrapped its „MŮJ TIP" box onto a second line at 1440 px.
10. **A footer that holds only the note carries no divider** (`.tip-row-extra.is-note-only`). A rule
    above a single small line reads as a section break for content that is not there.
11. **The dashboard/`/zapasy` „zdroj · venue" line moved INSIDE the card** rather than into the
    when-zone meta: at 96 px it would have been truncated to nothing. Competition detail passes no
    source (its header already names it) and uses the same slot for „Uzávěrka …".
