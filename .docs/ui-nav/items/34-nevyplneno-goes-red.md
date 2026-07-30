# Item 34 — „Nevyplněno" is red too

**Status:** TODO
**Filed:** 2026-07-30, from the product owner, closing a question item 25 raised rather than decided.

## How this arose

Item 25 (`6165577`) made „you owe a tip and can still give one" **red**: a `danger` `Pill` and a
`missing` card stripe on the match cards. Its implementer then noticed the *same fact* still rendered
**amber** on three other surfaces, and deliberately left them alone — item 25's „What must NOT
change" forbade red leaking onto any other `soon`/`warn` use — but flagged it: the same state now
read red on the card and amber on the page that card links to.

**Product owner, 2026-07-30: „yes the Nevyplněno should be red too."**

## What to change (grepped, exactly three call sites)

All three are `<twig:Pill label="Nevyplněno" variant="soon" icon="circle-alert" />` → **`variant="danger"`**:

| File | Line | Context |
|---|---|---|
| `templates/portal/competition/match_detail.html.twig` | ~162 | the viewer's own tip on the merged match page |
| `templates/portal/competition/match_detail.html.twig` | ~475 | „Tipy členů" — a member's tip, organizer view |
| `templates/portal/competition/manage_member_tips.html.twig` | ~160 | „Správa tipů členů" |

Line numbers are a hint — item 22 created `match_detail.html.twig` very recently. **Grep the string
and change every hit**; if there are more than three, say so. (Three misjudged counts happened in
this stream today; all three came from grepping a markup shape instead of the string.)

`pill-danger` **already exists** — item 25 added it, built from `--color-loss`. Do not add a colour,
do not touch `assets/styles/app.css`, and keep `icon="circle-alert"`.

## What must NOT change

- **„Netipováno" stays grey/`locked`.** This is the distinction B5 established and it is the whole
  reason the change is safe: „Nevyplněno" appears **only while the tip can still be filled** (both
  match-page call sites carry a code comment saying exactly that), whereas „Netipováno" is the
  post-deadline fact. Red asks for an action the viewer can still take; grey states one they cannot.
  **Verify that guard still holds at each site before you flip it** — if any of the three can render
  after the deadline, that is a bug to report, not to paint red.
- **The comments above those pills are load-bearing documentation.** They explain the B5 rule; update
  them only if flipping the colour makes them inaccurate.
- **No other `soon` / `warn` use changes.** „Brzy", the deadline-approaching pills and everything else
  stay amber. This item is three call sites.
- **`LockedStateSurfacesTest`** asserts the *text* „Nevyplněno" is present before the lock and absent
  after. A variant change must not disturb that — but re-read it: if it asserts a class anywhere,
  update it in the same commit.
- Czech in the UI, English in code, identifiers and class names. No „sázka" in any form.

## Acceptance criteria

1. All three „Nevyplněno" pills render `pill-danger`, in the same red as „Chybí tip" on the cards.
2. „Netipováno" is unchanged everywhere, and so is every other `soon`/`warn` pill in the app.
3. A tip that is still fillable reads red; the same absence after the deadline still reads grey
   „Netipováno".
4. `composer quality` clean.

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Read the computed colour, do not trust the class name.** `getComputedStyle` on each of the three
  pills, and on a „Netipováno" and a „Brzy" pill on the same page to prove they did not move.
- Load the merged match page as a plain member **and** as an organizer (call site ~475 is
  organizer-only), plus „Správa tipů členů".
- `docker compose exec web vendor/bin/phpunit tests/Integration/Portal/Competition tests/Integration/Portal`.
  **Never run `phpunit tests/` whole — it OOMs (exit 137).** Strip ANSI before grepping.
- ⚠️ **Never put a Twig comment inside a `<twig:Foo …>` attribute list** — parse error, 500, invisible
  to `composer quality`. It bit an agent today. Comments go above the tag.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`, and never a tree-wide `git restore` / `checkout .` / `stash`. Push to `main`.
Do not update the status board; report your sha.
