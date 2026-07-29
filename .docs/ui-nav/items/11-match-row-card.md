# Item 11 — Match rows: one card, boosts inside it

**Status:** TODO
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
