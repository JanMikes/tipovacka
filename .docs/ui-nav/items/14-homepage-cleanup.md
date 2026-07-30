# 14 — Homepage: cut the invented numbers, the countdown and „Proč Wtips", fix the wrapped score

> **Status:** TODO
> **Depends on:** nothing. Runs concurrently with three other agents — see „Files another agent owns".
> **Owner decision date:** 2026-07-30

## Why (the requirement, in the product owner's terms)

Four changes to the marketing landing page (`/`, `templates/home.html.twig`), given verbatim:

> On homepage (marketing landing page) remove the blue banner with „MS 2026 začíná za"
> Remove „248 aktivních hráčů v ukázce / 14 matchdayů sledovaných naživo / 3 bodovací modely"
> Remove „PROČ WTIPS" section completely
> Can you fix the score positioning, it is malformed

The through-line for the first three: **the homepage claims things that are not true.** „248 aktivních
hráčů", „14 matchdayů sledovaných naživo" and a World-Cup countdown are invented figures and a
manufactured deadline, on the first page a visitor sees. The same pass removed fabricated stats from
the sign-in page (`ROUND2.md` batch 16) — this is the marketing half of that clean-up.

## What changes

| # | Where | Change |
|---|---|---|
| 1 | `home.html.twig:~369` — „MS 2026 začíná za chvíli. Kdo to letos vystihne?" | **Delete the whole banner**, not just the sentence. The product owner calls it „the blue banner"; remove the element and its container, and check nothing above/below relied on its margin. |
| 2 | `home.html.twig:~353-355` | **Delete all three figures** — „**248** aktivních hráčů v ukázce", „**14** matchdayů sledovaných naživo", „**3** bodovací modely" — and the row that holds them. |
| 3 | The „PROČ WTIPS" section (see `home.html.twig:229`, the comment „3. PROČ WTIPS — 6 feature cards") | **Delete the section completely**, heading, all six cards and its wrapper. |
| 4 | `home.html.twig:79-81` | **Fix the wrapping score** — see below. |

### 4 — the score is wrapping, and the cause is known

Screenshot: [`screenshots/bug-b20-home-hero-score.png`](../screenshots/bug-b20-home-hero-score.png).
The hero demo card renders „2 – 1" broken across two lines: „2 –" on the first, „1" on the second,
each glyph at `3.25rem`, which reads as a malformed layout rather than a score.

The markup:

```twig
{# Match row #}
<div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 py-2">
    <div class="flex flex-row-reverse items-center gap-3 text-right"> … Argentina … </div>
    <div class="text-center text-[3.25rem] font-black leading-[0.95] tracking-[-0.04em] text-white tabular-nums">
        2<span class="mx-1.5 align-[6px] text-2xl text-white/40">–</span>1
    </div>
    <div class="flex items-center gap-3"> … Francie … </div>
</div>
```

The score sits in the **`auto`** track between two `1fr` tracks and **nothing prevents it wrapping**.
When the side tracks squeeze the middle one below its max-content width, the string breaks at the
dash. `tabular-nums` and `leading-[0.95]` then make the two lines look like a rendering fault.

**The minimal fix is to stop it wrapping** (a `nowrap` utility on that element). **Verify that is
sufficient** rather than assuming — if the side tracks can still overflow their own content at narrow
widths, that is the *same* mechanism as **B7**, where a flex child without `min-width: 0` kept its
min-content width and painted over its neighbours. Read B7 in `BUGS.md` before deciding; the two team
blocks are flex containers holding names that can be long.

Do **not** "fix" it by shrinking the font, and do not restructure the grid unless measurement shows
the grid itself is wrong.

**The floating chips are intentional.** „+12 b Marek vystihl skóre", „1. MÍSTO 147 / 248" and
„+9 b Ana trefila výsledek" are decorative overlays that deliberately sit over the card's edges. They
are **not** part of this defect — do not remove or reposition them.

## Out of scope

- **Every other page.** `templates/public/{features,pricing,for_business,faq}.html.twig` are untouched,
  even though this change makes some of them thinner on entry points (`ROUND2.md` batch 15 records
  that separately, and whether those four pages survive at all is an open product decision).
- **The footer.** Queued as its own work (`ROUND2.md` batch 15).
- **`templates/auth/login.html.twig`** — another agent owns it and is removing that page's fabricated
  figures right now.
- **Do not replace the deleted numbers with real measured ones.** That is a different product
  decision, and the honest figures today would be near zero.
- No new component, no new CSS class, no route change.

## Implementation notes

- Read the whole template first. It is a long marketing page built from numbered sections (the
  comment at `:229` shows the convention); deleting a section cleanly means taking its wrapper and its
  numbering comment, and renumbering the comments that follow **only if** they are already sequential.
- After deleting three blocks, **check the page's vertical rhythm.** Sections carry their own top and
  bottom spacing, so removing one can leave a double gap or collapse two backgrounds into each other.
- Check whether any CSS class or icon becomes unused after the deletions. An icon that is no longer
  referenced can stay in `assets/icons/lucide/` (harmless), but a now-dead rule in `assets/styles/app.css`
  must **not** be removed by you — that file belongs to another agent this round. Report it instead.
- The page is public and anonymous-reachable; that must not change.

## Acceptance criteria

- [ ] `/` returns 200 for an anonymous visitor **and** still redirects a logged-in user to `/nastenka`.
- [ ] The strings „MS 2026", „248 aktivních hráčů", „matchdayů", „bodovací modely" and „Proč Wtips" (in any casing) appear **nowhere** in the rendered page.
- [ ] No invented statistic remains anywhere on `/` — grep the template for stray numerals presented as facts and report anything you leave.
- [ ] The hero score renders on **one line** at every width from 1600 px down to 320 px, and the team blocks neither overflow nor overlap it.
- [ ] No double gap or collapsed section boundary where the three blocks used to be.
- [ ] `templates/home.html.twig` is the **only** file changed (plus tests). No CSS file touched.

## Verification

```bash
docker compose exec web composer cs:fix
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/Public
docker compose exec web vendor/bin/phpunit tests/Integration/Auth
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk by subdirectory; strip ANSI codes before
grepping PHPUnit output.

`composer quality` **does not render templates**, so it cannot see any of this. Load `/` and:

- **Measure the score, don't look at it.** Confirm the score element's `getClientRects().length === 1`
  (one line box) at **1600 / 1440 / 1280 / 1024 / 768 / 430 / 320 px**, and that its rect does not
  intersect either team block's rect. That is the same harness B7 and B8 used — see their sections in
  `BUGS.md`.
- Confirm zero horizontal page overflow at each of those widths.
- Confirm the three deleted blocks are gone and the page reads continuously where they were.

After `composer db:reset` you **must** `docker compose restart web`, or every page 500s on stale
FrankenPHP worker connections. Never run `asset-map:compile`; if assets look frozen, `rm -rf public/assets`
then `docker compose restart web`.

## Files another agent owns right now — do not touch

Three agents are working in this same checkout concurrently. You will see modified files that are not
yours; **leave them alone and never stage them.**

- `templates/auth/*`, `templates/components/Auth/*`, `templates/invitation/*`,
  `templates/components/Layout/Nav.html.twig`, `templates/portal/dashboard.html.twig`,
  `src/Service/Security/*` — the invite-funnel agent.
- **`assets/styles/app.css`**, `templates/portal/competition/{settings,detail}.html.twig`,
  `assets/controllers/confirm_controller.js` — the B13/B16 agent.
- `templates/components/Boost/Panel.html.twig`, `templates/components/Guess/*`,
  `templates/portal/sport_match/detail.html.twig`, `src/Service/EffectiveTipDeadlineResolver.php`,
  `.docs/DOMAIN.md` — the boost-copy agent.
- `.docs/ui-nav/PLAN.md`, `UI-MAP.md`, `BUGS.md`, `ROUND2.md` — **the orchestrator**. Do not edit them;
  report your sha and any delta instead.

**Never `git add -A`, `git add .` or `git commit -a`** — an agent did that once and swept two others'
unfinished work into its commit; right now it would destroy three agents' work. Stage explicit paths
and verify with `git diff --cached --stat` before committing. Another session has also been committing
here today, so `git pull --rebase` if a push is rejected; never force-push.

## Assumptions made

_(Implementer appends here if the item did not answer a question it had to answer.)_
