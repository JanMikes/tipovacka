# Item 31 — a secondary „go see more" link with an arrow is a link, not a button

**Status:** TODO — **blocked on item 22**, which owns `templates/portal/dashboard.html.twig` and
`assets/styles/app.css`.
**Filed:** 2026-07-30, from the product owner. **This item establishes a standing convention**, not
just a fix — see „The rule" below.

## The instruction (verbatim)

> „„Zobrazit celou tabulku" and „Všechny soutěže" is wrong style, should be as this (link-like),
> basically when the link have arrow in it, use this style and remember that"

Three inline mocks: the two offenders (a full-width bordered ghost button under the „Tvoje pozice"
card, and a centred bordered pill under the „Moje soutěže" grid) and the desired shape — „Celý
žebříček →", plain accent-coloured text with the arrow inline, no border, no background.

## The rule (settled with the product owner, 2026-07-30)

> **A secondary „go see more" navigation link that carries a `lucide:arrow-right` is styled as a
> text link, not as a bordered button. A primary action keeps its button chrome even with an arrow.**

The literal reading of the instruction („when the link has an arrow") was put to the product owner
with the blast radius measured — **26 arrow-bearing buttons**, of which 16 are `btn-primary` /
`btn-light` CTAs including „Zadat tip →" and the homepage hero. **They chose the narrower rule**:
convert the `btn-ghost` family only. The distinction is *action vs navigation*, and the arrow is the
tell for navigation, not the cause of the styling.

## What to convert (established from the code — do not re-derive)

`grep -rn "lucide:arrow-right" templates` finds 38 arrow links; 26 sit inside a `.btn`. Broken down
by variant:

| Variant | Count | This item |
|---|---|---|
| `btn-ghost …` (incl. `btn-sm`, `w-full`, `w-full justify-between`, `mt-8 w-full`) | **10** | **convert to the link style** |
| `btn-primary …` | 10 | **leave alone** — actions |
| `btn-light btn-lg …` | 6 | **leave alone** — marketing hero CTAs |

The two the product owner screenshotted are both in the ghost family:
`templates/portal/dashboard.html.twig:136` („Zobrazit celou tabulku", `btn btn-ghost btn-sm w-full
justify-between`) and `:242` („Všechny soutěže", `btn btn-ghost btn-sm`).

**The target style already exists**, inline, at `templates/portal/dashboard.html.twig:360` and
`templates/portal/competition/detail.html.twig:412`:

```twig
<a href="…" class="text-sm font-semibold text-accent-300 transition-colors hover:text-accent-200">
    Celý žebříček
    <twig:ux:icon name="lucide:arrow-right" class="inline h-4 w-4" />
</a>
```

## What to do

1. **Give the pattern a class.** It is about to appear ~12 times, and `PLAN.md`'s CSS discipline says
   to prefer a Tailwind utility in the template *unless the pattern repeats* — this one does. Add one
   class (`.link-arrow` or better) at the END of the section it belongs to, under a
   `/* --- item 31: arrow links --- */` comment. Never reorder or reformat existing rules. Convert the
   two existing inline instances to it as well, so there is one definition and not three.
2. **Convert the 10 `btn-ghost` + arrow sites.** Enumerate them yourself from the grep — the table
   above is a count, not a list, and a stale line number is worse than none.
   ⚠️ Two of them carry layout utilities that were doing real work: `w-full justify-between` (the
   full-width button under „Tvoje pozice") and `mt-8 w-full`. A text link is not a block, so **check
   each one's alignment after conversion** rather than dropping the utilities blindly — if a site
   genuinely needs the link to fill its column, keep that on the element.
3. **Leave every `btn-primary` and `btn-light` arrow alone**, per the rule.
4. **Write the rule down where the next implementer will find it** — `.docs/ui-nav/UI-MAP.md` §5
   („Styling"), next to item 18's `.card-clickable` / `.card-stretch` / `.card-raise` note, which is
   the precedent for recording a small shared pattern there. Keep it to two or three sentences: what
   the class is, when to use it, and that a primary action keeps its button.

## What must NOT change

- **No primary or marketing CTA loses its button chrome.** If you find an arrow link that is
  *neither* clearly an action nor clearly „go see more", leave it as it is and **say so in your
  report** — an ambiguous case is a product decision, not yours or mine.
- **Keyboard and screen-reader behaviour.** These stay `<a>` elements with the same targets; a text
  link still needs a visible `:focus-visible` state — the `.btn` class was providing one, so make
  sure the new class does too. Do not let the focus ring disappear in the conversion.
- **Hit area.** A bordered button is a bigger tap target than a line of text. On the two full-width
  sites especially, check the link is still comfortably tappable on a phone.
- The arrow icon itself (`lucide:arrow-right`) and its `inline h-4 w-4` sizing.
- Czech in the UI, English in code, identifiers and comments. No „sázka" in any form.

## Acceptance criteria

1. „Zobrazit celou tabulku" and „Všechny soutěže" on `/nastenka` render as text links with an inline
   arrow — no border, no background — matching „Celý žebříček".
2. All 10 ghost-variant arrow links are converted and use **one** shared class; the two pre-existing
   inline instances use it too.
3. Every `btn-primary` / `btn-light` arrow link is untouched.
4. Each converted link keeps a visible focus ring and its original target.
5. The rule is written into `UI-MAP.md` §5.
6. `composer quality` clean.

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Load every page you touched.** `composer quality` cannot see a style. List the pages in your
  report.
- **Measure, do not eyeball**: for each converted link, its bounding box before and after, and
  confirm nothing below it shifted into something else. The two `w-full` cases are where alignment
  breaks.
- **Tab to each converted link** and confirm the focus ring is visible against the dark surface.
- At 320 px, confirm no converted link overflows its column (`scrollWidth` vs box `width`).
- `docker compose exec web vendor/bin/phpunit tests/Integration/Portal tests/Integration/Public`
  and `tests/Integration/DesignStyleguideFlowTest.php` — some tests assert on `.btn`. **Never run
  `phpunit tests/` whole — it OOMs (exit 137).** Strip ANSI before grepping.
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. Push to `main`. Do not update the status board; report your sha.
