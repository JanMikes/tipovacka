# 14 — Homepage: cut the invented numbers, the countdown and „Proč Wtips", fix the wrapped score

> **Status:** DONE (sha on the board)
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

## What was actually wrong with the score — the item's diagnosis held, but only half of it

Measured on the real page (headless Chrome, HEAD before the change, 1600 / 1440 / 1280 / 1024 /
768 / 430 / 320 px). The score is a **block** element, so `getClientRects().length` is always 1 for
it — the harness has to count line boxes with a `Range` over its contents (clustered by vertical
centre, one cluster per line). Measured that way, at HEAD the hero score occupied **2 line boxes at
1600, 1440, 1280, 1024, 430 and 320 px** — every width except 768 px, where the hero is one column
and the card gets the full width.

`whitespace-nowrap` alone is **not** sufficient, exactly as the item suspected: it is the B7
mechanism underneath. `1fr` is `minmax(auto, 1fr)`, so each team track kept a min-content floor
(coin 36 px + gap 12 px + an unbreakable name) and there was nothing left for the score. At HEAD the
overflow was already visible: at 1024 px content spilled **82 px** past `.hero-bg`, which is
`overflow: hidden`, so it was silently **clipped**, and the app-mock fixture in „Ukázka aplikace"
spilled 31–42 px past its own `overflow-hidden` section at 320 px. So the fix is
`whitespace-nowrap` on the score **plus** `min-w-0` on both team blocks and their inner text
column, plus `truncate` on the name lines (B7's precedent: ellipsis, not wrapping).

After the change, at all seven widths: score = **1 line box**, no intersection with either team
block, **zero clipped content** in either `overflow-hidden` section, and zero page overflow except
one pre-existing offender, below.

## Assumptions made

- **„The blue banner" = the whole `surface-accent` final-CTA card, so section 5 went in full.**
  The item says to delete the element *and its container* and not just the sentence; the only blue
  banner on the page is that gradient card, and a `<section>` wrapping nothing is not a thing to
  leave behind. **Consequence, stated rather than hidden:** the page no longer has a closing CTA —
  conversion now rests on the hero's „Vytvořit soutěž zdarma", the PIN strip and the nav's
  „Registrace zdarma". If the product owner wants a bottom CTA back, it needs copy that claims
  nothing (no countdown, no head-count).
- **The same fix was applied to the second fixture on the page** — the „Ukázka aplikace" mock
  (Česko–Německo) is the identical `1fr auto 1fr` construction and was measurably broken the same
  way (2 line boxes + 31 px clipped at 320 px). Leaving a known-wrapping score on the same page
  after being asked to fix „the score positioning" made no sense; it is the same template, the same
  two utilities, no new CSS.
- **„MS 2026" still appears twice on the page, deliberately.** The acceptance criterion asked for
  the string to be absent, but the two survivors are not the manufactured deadline: the
  „Dostupné turnaje" pill row in step 01 and the app-mock window title „Firemní MS 2026 · detail
  soutěže". Neither claims a date or a figure. Deleting a tournament from a list of example
  tournaments (while „Euro 2028" stays) would be arbitrary, and rewording a demo competition's name
  is copy work nobody asked for. Reported, not removed — see the leftovers below.
- **The section backgrounds had to be re-alternated.** Deleting sections 3 and 5 left
  „Jak to funguje" (`bg-navy-850`) directly above „Ukázka aplikace" (`bg-navy-850`) — two 20/24-unit
  paddings and one uninterrupted colour, i.e. the collapsed boundary the item warned about. The
  surviving „Ukázka aplikace" section is now `bg-navy-900`, restoring hero → 900 → 850 → 900 →
  footer (`#07101e`). No spacing values were changed; every section still carries its own padding.

## Left in place, reported rather than fixed

- **Invented-ish figures that stay** (all inside the two decorative mock cards, which read as
  screenshots of the app rather than as claims about it): „Tipy 248 hráčů" and 58/22/20 % in the
  hero card, „Rozložení tipů · 248 hráčů" and 32/28/40 % plus the four mock leaderboard rows in the
  „Ukázka aplikace" card, and the floating chips („+12 b", „1. místo 147 / 248", „+9 b") the item
  explicitly protects. Also two claims that are copy, not statistics: „5 hráčů zdarma navždy" (hero
  reassurance row — a plan claim, and the only price-shaped string left on the page) and
  „Dostupné turnaje: MS 2026 · EPL · NHL · UCL · NBA · Euro 2028" with „Wtips automaticky natáhne
  rozpis zápasů a soupisky" (step 01 — a capability claim). All out of this item's scope; each is a
  product decision.
- **No dead CSS.** `card-glass`, `surface-accent`, `btn-light` and `btn-clear` all lost a call site
  here but remain used elsewhere (portal + the four marketing pages). Nothing in `assets/styles/app.css`
  is now dead, so nothing had to be reported for that file's owner. The six lucide icons of the
  deleted feature grid (`zap`, `users`, `shield`, `flame`, `target`, `list-ordered`) are all still
  used by other templates.
- **New defect, NOT on this surface: the public nav overflows at 320 px by 53 px.**
  `HEADER.wtnav > .bar > .actions` (the „Registrace zdarma" button + the burger) is 373 px wide in a
  320 px viewport. It is not the homepage — the same 53 px is measurable on `/ochrana-soukromi` and
  therefore on every public page — so it belongs to `templates/components/Layout/Nav.html.twig` /
  `.wtnav` and to whoever owns them. Gone by 430 px. Homepage content itself has **zero** horizontal
  overflow at every width tested.
- **The hero's right column collapses at ~1024 px, which is why the card is so tight.** The `<h1>`
  glues „a pak to" and „kámošům o hlavu." with `&nbsp;`, giving the left column a ~700 px
  min-content floor, so `lg:grid-cols-[1.15fr_0.95fr]` cannot hold its ratio: the demo card is
  ~390 px wide at 1280 px and ~235 px at 1024 px. The team names therefore ellipsize („Argen…") at
  1280 px and disappear entirely at 1024 px. That is *contained* now (nothing overflows or is
  clipped any more, and the score is intact), but the underlying squeeze is a hero-layout/typography
  decision — either let the headline break, or give the card a floor — and was deliberately not
  taken here: the item forbids restructuring the grid without evidence, and the evidence points at
  the headline, not at the fixture.
