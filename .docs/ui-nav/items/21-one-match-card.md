# 21 — One match card: migrate `/zapasy` and delete the `variant` prop

> **Status:** TODO
> **Depends on:** item 18 (which built `variant="dashboard"`), item 19 (which switches competition detail
> to it). **Start only after item 19 has landed** — it changes the second of the three call sites.
> **Owner decision date:** 2026-07-30

## Why (the requirement, in the product owner's terms)

Item 18 built the new match card and, at the product owner's instruction, scoped it to the Nástěnka:
*„since i specced it i expect to change it on that page."* Item 19 then switched competition detail to it
as well. Asked whether the new look should become the card everywhere, the product owner chose
**one design everywhere**, having already said *„consistency is important across platform."*

So the `variant` prop has done its job — it let the redesign land on one page and be proven not to disturb
the others — and now it should go, leaving **one card**.

## What changes

1. **`/zapasy`** (`templates/portal/matches/index.html.twig`) renders the new card.
2. **`variant` disappears from `Match:MatchRow`.** The dashboard shape becomes *the* shape; the item 11
   layout (`variant="default"`) is deleted along with its CSS, its branch and any prop it alone used.
3. **`/_design`** stops showing two variants and shows one (item 13's gallery must not imply a variant
   still exists).
4. Every prop that only the deleted shape honoured is either **removed** or **taught to the survivor** —
   see the trap below.

## ⚠ The trap: props the two shapes treat differently

Item 18's report says the dashboard variant **ignores `tipPrompt`** and renders its own „Zadat tip" footer.
Item 11 introduced `tipPrompt` precisely so competition detail could show „+ Zadat tip" in the empty tip
box. So:

- **`tipPrompt`** — if the survivor genuinely ignores it, **delete the prop and every call site that
  passes it**. A prop that silently does nothing is worse than no prop. Do not leave it „for later".
- **`tipMissingLabel` („Netipováno")** — **B5 is a domain decision, not a style choice.** It may appear
  only where the row knows exactly ONE soutěž (competition detail), because a cross-competition row
  aggregating several soutěže cannot honestly claim the viewer never tipped; `/zapasy` and the Nástěnka
  say „Uzamčeno" instead. **The unified card must preserve that distinction.** Read B5's „Assumptions
  made" before touching it, and keep the tests that pin the wording per surface.
- **`footNote`** — competition detail uses it for „Uzávěrka …", the other two for „zdroj · venue". Both
  must still render.
- **`tipStats`** — `/zapasy` passes a list with **more than one** entry (a match can sit in several of the
  viewer's soutěže). Verify the unified card renders **all** of them, not just the first: the dashboard
  variant has only ever been exercised with the single-soutěž case, so this is the most likely latent bug.
  It must still come from the page's `TipStatsProvider` **batch** — never a query per row.

## Out of scope

- No new visual design. This is a migration, not a redesign — the card is whatever item 18 shipped.
- No change to what any surface *contains*, only to how the card looks and that it is one link.
- Competition detail's own restructure (item 19) and the tip-visibility rule (item 20).

## Acceptance criteria

- [ ] `Match:MatchRow` has **no `variant` prop**, one layout, and no dead CSS or branch left from the old one.
- [ ] All three surfaces — `/zapasy`, the Nástěnka, competition detail — render the same card, each still passing its own props correctly.
- [ ] **B5 holds**: „Netipováno" appears on competition detail only; `/zapasy` and the Nástěnka still say „Uzamčeno".
- [ ] A `/zapasy` row belonging to **several** soutěže renders **every** „Rozložení tipů" strip, and the page still resolves them in ONE batch (assert the query count).
- [ ] The whole card is ONE `<a>` on every surface, with **nothing interactive nested inside it** — the „Rozložení tipů" strip is a control and must stay a **sibling** (assert `a.tip-row-link a|button|input|select` = 0).
- [ ] `/_design` shows one card, no „variant" language.
- [ ] `grep` finds no remaining reference to `variant="default"`, `variant="dashboard"` or `tipPrompt` outside history.

## Verification

```bash
docker compose exec web composer cs:fix
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/Portal
docker compose exec web vendor/bin/phpunit tests/Integration/Public
docker compose exec web vendor/bin/phpunit tests/Integration/Query
docker compose exec web vendor/bin/phpunit tests/Integration/DesignStyleguideFlowTest.php
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk; strip ANSI codes before grepping.

**`composer quality` cannot see a layout.** Measure, on all three surfaces plus `/_design`:

- Pairwise bounding-box intersection across painted leaves — **zero overlaps, zero horizontal overflow** —
  in every row state: open · tipped · live · locked · finished-with-points · playoff.
- **By the card's own width, not the viewport** (B7): competition detail is narrower at 1440 px than at
  1024 px because of its column, and item 18 exercised widths from **1088 down to 238 px**. Reproduce that
  spread; `/zapasy` is a new container for this card and is the one width nobody has measured yet.
- A long-name stress pass (B7 used „FK Slovácko B (Uherské Hradiště)") — names must ellipsize, not wrap.
- `getClientRects().length` on a **block** element is always 1 — use a `Range` over the contents,
  clustered by vertical centre, if you count wrapped lines.

After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`; if
assets look frozen, `rm -rf public/assets` then restart `web`.

## CSS discipline

You own **`assets/styles/app.css`**. This item *removes* rules, which is the one case where touching
existing ones is expected — but delete only what the retired shape owned, and **do not reformat or reorder
what stays**. Item 18's rules are in the „Horizontal match row" section; item 17's `.bell-panel` /
`.credit-chip` and item 19's block are **not yours**.

## Git

**Commit with `git commit -o <path> [<path>…] -m …`** (`-o` = `--only`) — index-independent. Do not use
`git add` + `git commit`: an agent staged explicit paths, verified `git diff --cached --stat`, and still
committed another agent's file, because a sibling wrote to the index in between. (`-o` takes only *tracked*
paths; new files need `git add -- <file>` first.)

Never `git add -A` / `git add .` / `git commit -a`. Another session also commits here — `git pull --rebase`
if a push is rejected, never force-push. **An API outage killed five agents mid-task today: commit early**,
and if you are cut off say what is unverified.

## Assumptions made

_(Implementer appends here if the item did not answer a question it had to answer.)_
