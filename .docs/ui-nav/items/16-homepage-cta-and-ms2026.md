# 16 — Homepage: a closing CTA that claims nothing, and no „MS 2026" anywhere

> **Status:** TODO
> **Depends on:** item 14 (`287499c`), which deleted the banner this item partly restores.
> **Owner decision date:** 2026-07-30

## Why (the requirement, in the product owner's terms)

Item 14 deleted the homepage's blue countdown banner, which was also the page's **closing CTA** — so the
landing page now ends with no call to action at all. Told that, the product owner:

> 1) Homepage no closing CTA -> well good call, we can put it there but something simple in the accent
> card style -> there was specific text for MS which is already over
> 2) No MS 2026 anywhere

So: bring the closing CTA back in the same visual language, but with **evergreen copy** — the old text
was tied to a World Cup that has finished, which is exactly why it had to go.

## What changes

### 1. A closing CTA, accent-card style, evergreen

Restore a closing section in the **`surface-accent` card style** item 14 removed (git show `287499c`
for the markup that was deleted — reuse its *styling*, not its copy). Requirements:

- **No date, no countdown, no tournament name, no figure.** Nothing that expires and nothing that
  asserts adoption. This is the whole point of the item.
- Short: a heading, at most one supporting line, one primary action.
- The primary action is **„Vytvořit soutěž"** → `competition_create` for a logged-in visitor and
  **„Registrace zdarma"** → `app_register` for an anonymous one, matching how the nav already branches.
  (`/` redirects a logged-in user to `/nastenka`, so in practice the anonymous branch is what ships —
  but write both rather than hard-coding the anonymous case.)

**Suggested copy** — the product owner asked for „something simple", so this is a proposal, not a
decision; it is one string each and cheap to change:

> **Založte si soutěž a pozvěte kámoše.**
> Výsledky se zapisují samy, tabulka se počítá sama. Bez sázek, jen pro radost.
> [ Registrace zdarma ]

„Bez sázek" is already the homepage's own phrasing and is worth keeping — it states what the product is
not, which is a differentiator rather than a claim.

### 2. „MS 2026" appears nowhere

Item 14 deliberately left two occurrences and marked its own acceptance criterion unmet, because
neither is a countdown or a figure. The product owner has now settled it — **both go**:

| Where | What to do |
|---|---|
| The „Dostupné turnaje" pill row | Remove the „MS 2026" entry. **Do not leave a gap** — if the row reads oddly with one fewer pill, rebalance it; if „MS 2026" was the only *current* tournament in the list, say so in your report rather than inventing a replacement. |
| The mock window title „Firemní MS 2026 · detail soutěže" | Rename to something evergreen, e.g. „Firemní liga · detail soutěže". |

Then **grep the whole of `templates/` for „MS 2026"** and report every remaining hit with a verdict.
Expect hits in `fixtures/` (`DevFixtures`, `AppFixtures`) — those are **dev/test data and stay**; this
item is about user-facing marketing copy. `/_design`'s sample DTOs are admin-only and inert, but if one
says „MS 2026" flag it rather than editing (see ownership below).

## Out of scope — and one thing explicitly NOT to do

**The homepage's product mockups keep their invented data.** The demo match card („Tipy 248 hráčů", the
58/22/20 % split, Argentina–Francie) and the demo leaderboard **stay exactly as they are.** Asked
directly whether „no mock/fake data anywhere" reached them, the product owner said:

> This one is marketing material just mock itself, here it is okay

`ROUND2.md` batch 20 records the boundary this draws: invented figures presented as facts *about the
business* (adoption counts, recommendation rates, live-activity pills, countdowns) are out — those were
removed by item 14 and batch 16 — while invented data *inside a picture of the product* is fine, because
it is self-evidently an illustration. **Do not "finish the job" by neutralising the mockups.**

Also out of scope: the footer (queued separately), every other template, and any new CSS class.

## Implementation notes

- The accent card style already exists (`surface-accent`, plus the button classes the deleted banner
  used). **Reuse it; add no CSS.** The item-14 agent confirmed `surface-accent`, `btn-light` and
  `btn-clear` are all still used elsewhere in the template, so nothing was orphaned by the deletion.
- **Mind the vertical rhythm.** Item 14 moved a surviving section to `bg-navy-900` to avoid two
  `bg-navy-850` sections colliding; the rhythm is now hero → 900 → 850 → 900 → footer (`#07101e`).
  Inserting a section changes that sequence — check the whole page, not just the new block.
- Related known defect, **do not fix here**: **B21** — the hero `<h1>`'s non-breaking spaces claim a
  ~700 px min-content floor, which starves the demo card until team names vanish at 1024 px. It has its
  own row because the fix changes how the headline reads.

## Acceptance criteria

- [ ] `/` returns 200 anonymously and still redirects a logged-in user to `/nastenka`.
- [ ] The page ends with a closing CTA in the accent card style, carrying **no date, countdown, tournament name or figure**.
- [ ] The CTA's target branches on `app.user` (register vs. create).
- [ ] `grep -ri "MS 2026" templates/` returns **nothing**, and every hit elsewhere is reported with a verdict.
- [ ] The demo match card and demo leaderboard are **byte-identical** to before this item.
- [ ] No double gap or collapsed section boundary; the rhythm still alternates.
- [ ] `templates/home.html.twig` is the only file changed (plus tests). **No CSS file touched.**

## Verification

```bash
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/Public
docker compose exec web vendor/bin/phpunit tests/Integration/Auth
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk by subdirectory; strip ANSI codes first.

`composer quality` **does not render templates**, so load `/` and check at **1600 / 1440 / 1024 / 430 /
320 px**: zero horizontal page overflow, the CTA readable and its button reachable at every width, and
the page reading continuously top to bottom. If you count wrapped lines, note that
`getClientRects().length` on a **block** element is always 1 — use a `Range` over the element's contents
and cluster the rects by vertical centre.

Note **B20**: the public nav already overflows at 320 px on every page. That is not yours — do not fix
it, and do not let it mask an overflow you introduce. Measure your own section's box.

After `composer db:reset` you **must** `docker compose restart web`. Prefer not to reset — other agents
may be verifying against the same database. Never run `asset-map:compile`.

## Git — read this, it bit an agent today

**Commit with `git commit -o <path> [<path>…] -m …` (`-o` = `--only`). Do NOT use `git add` + `git
commit`.** An agent staged explicit paths, verified with `git diff --cached --stat`, and its commit
**still swept in another agent's `assets/styles/app.css`** — a sibling staged into the index between the
`add` and the `commit`. Verifying the index proves nothing: it is shared mutable state another process
can write to after you look. `-o` is index-independent.

Never `git add -A` / `git add .` / `git commit -a`. **Do not run `composer cs:fix` repo-wide** — it
rewrites siblings' files into your working tree. Another session also commits here: `git pull --rebase`
if a push is rejected, never force-push.

## Files another agent owns right now — do not touch

- `templates/public/leaderboard.html.twig`, `templates/public/competitions_list.html.twig`,
  `templates/design/styleguide.html.twig`, `src/Controller/DesignStyleguideController.php`, the
  leaderboard/competition queries and `LeaderboardTimeFilter` — the item-15 agent.
- `templates/components/Layout/{Nav,Footer}.html.twig`, `templates/base.html.twig`,
  `src/Twig/Components/Notification/*`, **`assets/styles/app.css`** — the chrome-pass agent.
- `.docs/ui-nav/PLAN.md`, `UI-MAP.md`, `BUGS.md`, `ROUND2.md` and `.docs/DOMAIN.md` — the orchestrator.
  Report deltas; do not edit.

## Assumptions made

_(Implementer appends here if the item did not answer a question it had to answer.)_
