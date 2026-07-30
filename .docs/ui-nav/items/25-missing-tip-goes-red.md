# Item 25 — a missing tip is red, not amber

**Status:** TODO — **blocked on item 22**, which owns every file this touches. Do not start it before
item 22 has landed.
**Filed:** 2026-07-30, from the product owner.

## The instruction (verbatim)

> „this component is match row used on dashboard and competition detail -> when missing guess, use
> red (danger) instead of yellow/warning border and the „chybí tip" badge danger as well"

The mock was pasted inline in the conversation and is not on disk. It shows one `Match:MatchRow` card
in the „no tip yet" state: an **amber left accent stripe**, the header „2. KOLO · 9. 8. · 10:15" with
an **amber „⚠ CHYBÍ TIP" pilulka** right-aligned, the two team blocks around „VS", a „Zadat tip →"
footer, and the locked „Rozložení tipů" paywall strip below the divider. Both amber elements — stripe
and pilulka — become red.

## What is there today (established from the code — do not re-derive)

**There is no red/„danger" `Pill` variant.** `assets/styles/app.css` (§ Pills, ~l. 285–295) defines
nine: `soon` · `tipped` · `done` · `locked` · `accent` · `success` · `warn` · `neutral` · `live`.
`pill-soon` and `pill-warn` are the same amber (`#f5b544`); `pill-live` is the only red
(`#ff5d7a`) and is commented **„marketing only"**. So this item adds a variant — it does not swap an
existing one — and `templates/components/Pill.html.twig` line 2 documents the list, so update it.

**„Chybí tip" is set by the call sites, not by the component.** `MatchRow` takes `pillVariant` /
`pillLabel` / `pillIcon` as props. The four places that pass `variant: 'soon'` + „Chybí tip":

| File | Line | Note |
|---|---|---|
| `templates/portal/competition/detail.html.twig` | ~293 | competition detail |
| `templates/portal/_dashboard_match_row.html.twig` | ~37 | Nástěnka |
| `templates/portal/matches/index.html.twig` | ~73 | `/zapasy` |
| `templates/design/styleguide.html.twig` | ~131, ~409, ~457 | the gallery's samples (`/_design` half A) |

**The left stripe** is the card's state stripe in `assets/styles/app.css` (~l. 945 onward): every
`state` (`open` · `tipped` · `live` · `locked` · `finished`) paints a 4 px stripe, and `open` is the
amber one. The `state` is derived at each call site, not in the component.

## What to do

1. **Add a red pill variant** — name it for what it means, in the existing vocabulary style, and
   build it from a colour already in the palette (the `loss` token) rather than introducing a new
   hex. Follow the shape of its nine siblings exactly (background / border-color / color at the same
   alpha steps). Add it at the END of the Pills block under a `/* --- item 25: … --- */` comment;
   never reorder or reformat the existing rules. Update `Pill.html.twig`'s documented variant list.
2. **Turn the `open` state stripe red** in `.tip-row.is-dash`, in the same place and the same way.
3. **Point the four call sites at the new variant.**
4. **Render it in `/_design`** — the gallery is the shop window and already shows the card in all five
   states plus a standalone „Chybí tip" pilulka. Both must show the red one. The page stays inert;
   keep `DesignStyleguideFlowTest::testNothingOnThePageCanAct` green.

### The distinctions that must survive

- **Red means „you owe a tip and can still give one".** It must NOT leak onto:
  - a card that **has** a tip and is merely still open (`state: 'tipped'`, „Tip odeslán") —
    the call sites already branch, but verify rather than assume;
  - the **„BRZY"** pilulka (deadline approaching) or any other `soon` / `warn` use anywhere in the
    app — those stay amber. This item changes the missing-tip case only, not the amber token.
  - a **locked** card that was never tipped. That is B5's „Netipováno" / „Uzamčeno" — a fact, not a
    call to action, and it is deliberately neutral. **Leave it grey.** Red there would nag about
    something the player can no longer do.
- **The partially-tipped cross-competition case goes red too.** On `/zapasy` and the Nástěnka the
  label can read „Chybí tip (1/2)" — a match sitting in two of the viewer's soutěže with a tip in
  only one. A tip is genuinely missing, so it is the same state and gets the same colour.
- **Check red does not now mean two things on one card.** `loss` red already marks lost points, and
  a finished card can carry a points badge. Look at a finished card and a missing-tip card side by
  side on the Nástěnka and confirm the page still reads unambiguously; if it does not, say so in your
  report rather than inventing a third colour.

## Also land these, handed over from item 23 (`2578def`)

Item 23 renamed the boosters and the „Rozložení tipů" section but **could not touch
`templates/design/styleguide.html.twig`**, which item 22 owned at the time. You own that file, so
finish its sweep in the same pass — the gallery is the shop window and is currently advertising
retired names. Read each line before changing it; some are prose *about* a component and some are the
component's sample copy.

| Line (approx.) | Now | Should be |
|---|---|---|
| 721, 724 | „Rozložení tipů ostatních" | „Jak tipují ostatní?" |
| 728, 731 | „Konkrétní tipy kolegů" | „Přesné tipy soupeřů" |
| 732 | „Rozložení tipů plus konkrétní tipy soutěžících v partičce." | the canonical sentence: „Chcete vědět, jak tipuje váš soupeř? Odemkněte si přesné tipy ostatních hráčů ve vaší soutěži." |
| 735, 738 | „Měnit tip během turnaje" | „Počkejte si na sestavy" |
| 380, 468, 473, 495, 502 | captions/headings saying „Rozložení tipů" | „Jak tipují ostatní?" — the heading they describe was renamed |

⚠️ **`DesignStyleguideFlowTest:140` asserts `assertStringContainsString('Rozložení tipů', $body)`
and passes today only because of line 502.** Rename 502 and that test fails — update the assertion in
the **same** commit. Prices in the gallery already derive from `pricing.*`, so 15/35/50 landed on
their own; only names and captions are left.

**A counting lesson from item 23, which applies to this item too.** Its spec said `Match/TipStats`
had „two user-visible occurrences" of the heading; there were **six** — the `<h2>` plus five
`.tip-stats-eyebrow` states, including the **locked** one, which is precisely what a non-paying
player sees. The count came from grepping the exact markup form rather than the string. So here:
**grep the string, count every hit, and check each state**, rather than trusting the four call sites
tabulated above. If you find more, say so.

## Also land these, handed over from item 23 (`2578def`)

Item 23 renamed the boosters and the „Rozložení tipů" section but **could not touch
`templates/design/styleguide.html.twig`**, which item 22 owned at the time. You own that file, so
finish its sweep in the same pass — the gallery is the shop window and is currently advertising
retired names. Read each line before changing it; some are prose *about* a component and some are the
component's sample copy.

| Line (approx.) | Now | Should be |
|---|---|---|
| 721, 724 | „Rozložení tipů ostatních" | „Jak tipují ostatní?" |
| 728, 731 | „Konkrétní tipy kolegů" | „Přesné tipy soupeřů" |
| 732 | „Rozložení tipů plus konkrétní tipy soutěžících v partičce." | the canonical sentence: „Chcete vědět, jak tipuje váš soupeř? Odemkněte si přesné tipy ostatních hráčů ve vaší soutěži." |
| 735, 738 | „Měnit tip během turnaje" | „Počkejte si na sestavy" |
| 380, 468, 473, 495, 502 | captions/headings saying „Rozložení tipů" | „Jak tipují ostatní?" — the heading they describe was renamed |

⚠️ **`DesignStyleguideFlowTest:140` asserts `assertStringContainsString('Rozložení tipů', $body)`
and passes today only because of line 502.** Rename 502 and that test fails — update the assertion in
the **same** commit. Prices in the gallery already derive from `pricing.*`, so 15/35/50 landed on
their own; only names and captions are left.

**A counting lesson from item 23, which applies to this item too.** Its spec said `Match/TipStats`
had „two user-visible occurrences" of the heading; there were **six** — the `<h2>` plus five
`.tip-stats-eyebrow` states, including the **locked** one, which is precisely what a non-paying
player sees. The count came from grepping the exact markup form rather than the string. So here:
**grep the string, count every hit, and check each state**, rather than trusting the four call sites
tabulated above. If you find more, say so.

## What must NOT change

- No other `Pill` variant's colour, and no change to the amber token itself.
- The card's geometry. This is a colour change: `MatchRow` is container-relative and renders from
  1088 px down to 238 px with no width media query (item 21) — do not touch its layout.
- Czech in the UI, English in code, identifiers and comments. Class names stay English.
- CSS discipline (`PLAN.md`): reuse before adding, new rules at the END of the section they belong
  to under an item-named comment, never interleaved, never reordered.

## Acceptance criteria

1. On competition detail, the Nástěnka and `/zapasy`, a match with no tip shows a **red** left stripe
   and a **red** „Chybí tip" pilulka.
2. „Tip odeslán", „BRZY", „Uzamčeno" / „Netipováno", live and finished cards are **visually
   unchanged** — compare before/after.
3. `/_design` shows the red pilulka and the red-striped card state, and stays inert.
4. The new variant is documented in `Pill.html.twig` and appears in `UI-MAP.md`'s Pill variant list
   (which currently says „the **nine** variants" — the count changes).

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Load all three surfaces** and compare the five card states before and after. `composer quality`
  cannot see a colour.
- **Read the computed colour**, do not trust the class name: `getComputedStyle` on the stripe and on
  the pilulka, on each of the five states, and confirm only the missing-tip one changed.
- `docker compose exec web vendor/bin/phpunit tests/Integration/Portal tests/Integration/DesignStyleguideFlowTest.php`
  — some tests assert the pill variant class. **Never run `phpunit tests/` whole — it OOMs (exit
  137).** Strip ANSI before grepping.
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. Push to `main`. Do not update the status board; report your sha.
