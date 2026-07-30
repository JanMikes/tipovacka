# 18 — Nástěnka: quick actions, clickable cards, and the new match-card design

> **Status:** TODO
> **Depends on:** item 11 (the match card), B7 (its layout), B15 (the empty state — do not disturb it).
> **Owner decision date:** 2026-07-30

## Why (the requirement, in the product owner's terms)

A mobile pass over `/nastenka`, given verbatim (`ROUND2.md` batch 1):

> text: Tvoje pozice, zápasy k tipnutí a žebříček v soutěži Lipina 26/27. (Smazat, tady bude věta: Na
> této obrazovce můžeš přepínat mezi jednotlivými soutěžemi, které hraješ, uvidíš zde zrychlené volby
> pro konkrétní soutěž)
> Pod tabulku Tvoje pozice doplnit text zobrazit celou tabulku (design tlačítka se šipkou
> pod tabulku poslední tvoje tipy přidat tlačítko Tipovat zápasy v soutěži
> Moje soutěže zrusit odkazy otevřít soutěž, a udelat celé karty na proklik
> odkaz všechny soutěže přesunout pod seznam mých soutěží
> Následující zápasy: zrušit roletku s výběrem soutěže
> Následující zápasy: Filtry dat do řádku dropdown vyber?
> Následující zápasy, karta zápasu: zrušit tlačítko tipovat a nechat pouze zadat tip, celá karta by
> měla fungovat na poklik
> Následující zápasy, karta zápasu: design viz příloha

The through-line: the page should be **quick actions for one soutěž**, and everything on it should be
tappable without hunting for a small link.

## What changes

| # | Where | Change |
|---|---|---|
| 1 | Hero sub-text (`dashboard.html.twig`, the „Tvoje pozice, zápasy k tipnutí a žebříček v soutěži …" paragraph) | Replace with **„Na této obrazovce můžeš přepínat mezi jednotlivými soutěžemi, které hraješ, uvidíš zde zrychlené volby pro konkrétní soutěž"**. Note the current text carries a **link** to `competition_detail` and the replacement has none — acceptable, the switcher and „Moje soutěže" both still reach the soutěž. |
| 2 | Under the „Tvoje pozice" `.hero-rank` card | Add **„Zobrazit celou tabulku"** → `leaderboard` with `?soutez=<id>`, styled as an **arrow button** („design tlačítka se šipkou"). |
| 3 | Under „Poslední Tvoje tipy" | Add a **„Tipovat zápasy v soutěži"** button → `competition_my_tips_batch` (`/souteze/{id}/moje-tipy`, the „tip everything at once" page). Keep the existing „Historie →" link. |
| 4 | „Moje soutěže" cards | **Remove the „Otevřít soutěž" link** and make the **whole card clickable** → `competition_detail`. **„Zobrazit na nástěnce" must keep working inside it** (item 06 assumption 1 relies on it, and it is the only way to change which soutěž the page shows without the switcher). |
| 5 | „Všechny soutěže" link | Move it from the section **header** to **below the card grid**. |
| 6 | „Následující zápasy" | **Delete the soutěž roletka** (the `?zapasy=` control). See the consequence below. |
| 7 | „Následující zápasy" filters | **Chips on desktop, dropdown on mobile** (product-owner decision). |
| 8 | The match card | Whole card clickable, one visible tip CTA. See below. |
| 9 | The match card | New visual design. See the transcribed mock below. |

### 6 — deleting the roletka removes the cross-soutěž view

The roletka is the **only** control that widens the two match lists past the switcher's soutěž
(`?zapasy=vse`, item 06 assumption 2). Deleting it means the Nástěnka is **always scoped to one
soutěž** — which is exactly what the new hero sentence promises, so the two changes agree. Remove the
`?zapasy` handling from the controller and the query so no dead plumbing is left; the cross-competition
feed still exists at `/zapasy` (URL-only since item 01). Say in your report whether anything else read
that parameter.

### 8 — one clickable card, one tip CTA

The product owner asks for both „zrušit tlačítko tipovat a nechat pouze zadat tip" **and** „celá karta
by měla fungovat na proklik", and the mock shows a full-width CTA bar at the bottom.

**Resolution (orchestrator decision, cheap to reverse):** the whole card becomes **ONE `<a>`**, and the
footer bar is a **button-*styled* element inside it** reading „Zadat tip" — not a second interactive
element. That satisfies both instructions and is the only reading that does not nest a button inside a
link. **B7 refused whole-row linking for exactly that reason**, so this reverses that assumption
deliberately: read B7 first, then do it properly rather than nesting.

**Diagnose the report before changing anything.** Item 11 already removed the „Tipovat →" action, and
production is current, so **there is no button called „Tipovat" on the card.** What exists: the state
pill („Chybí tip") is a link (B7) and the „MŮJ TIP" box renders „+ Zadat tip". **Hypothesis to verify in
a real browser at phone width: the linked pill reads as a second tip button.** Report which explanation
was true. Do not assume the product owner mis-saw — find what they saw.

### 9 — the card design (transcribed; the mock was pasted inline, no file exists)

Product owner: *„here is the promised match card — note the CTA is malformed, it is just to show"*.

```
┌───────────────────────────────────────────────────────────┐
│           1. KOLO   31. 7.   18:00      [ ⚠ CHYBÍ TIP ]   │
│                                                            │
│      (FRÝ)                 1 : 1                 (HRA)     │
│     DOMÁCÍ                                      Hranice    │
│  Frýdek-Míst…                                    HOSTÉ     │
│                                                            │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                  Zadat tip           →               │  │
│  └──────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────┘
```

- **Header row, centred**: round („1. KOLO", small grey caps) · date („31. 7.") · time („18:00") both
  bold white; the state pill **right-aligned, amber, outlined** (not filled).
- **Middle**: team coin + role label (`DOMÁCÍ`/`HOSTÉ`) + name, big score centred. The two sides are
  **mirrored** — home shows role above name, away shows name above role.
- Long names **ellipsize** („Frýdek-Míst…") — B7's existing behaviour, keep it.
- **Footer**: a full-width CTA bar. The mock says „Tipovat"; ship **„Zadat tip"** per item 8, and only
  when the match is actually tippable.
- The mock shows „1 : 1" on an *upcoming* match, which cannot both be true — it reused a played
  fixture. Since date and time move into the header, the centre slot shows the **score when one
  exists** and otherwise the `vs` separator, **not** a duplicated kickoff time.

## ⚠ `Match:MatchRow` is shared by FOUR surfaces

It renders on **competition detail**, **`/zapasy`**, **the Nástěnka** and **match detail**. B7's
definition of done is binding here: *„It is shared. Fix it once in the component/CSS and verify **every**
surface — a per-page patch is not acceptable."*

So the redesign lands in the component and **applies everywhere**. Verify all four. Two per-surface
facts that must survive:

- **`tipMissingLabel` („Netipováno") is competition-detail only** — B5 settled that cross-competition
  rows keep „Uzamčeno", because a row aggregating several soutěže cannot honestly claim the viewer never
  tipped. Do not push it onto the Nástěnka.
- **`tipStats` must stay batched** — always from the page's `TipStatsProvider` batch, never one query per
  row. That is the documented N+1 trap in `CLAUDE.md`.

## Out of scope

- **The B15 empty state.** For a viewer in no soutěž the page renders PIN bar → „Procházet soutěže" →
  „Vytvoř si vlastní soutěž". **Leave it exactly as it is.**
- Competition detail's own restructure and the first-visit credits modal (`ROUND2.md` batch 2) — separate.
- The žebříček sidebar and „Odehrané zápasy" beyond what the shared card changes.
- **B21** (the homepage `<h1>` starving its demo card) — different page, own row.

## Acceptance criteria

- [ ] The hero shows the new sentence and no leftover „Tvoje pozice, zápasy k tipnutí…" text.
- [ ] „Zobrazit celou tabulku" sits under „Tvoje pozice" and reaches `/zebricek?soutez=<id>`.
- [ ] „Tipovat zápasy v soutěži" sits under „Poslední Tvoje tipy" and reaches `/souteze/{id}/moje-tipy`.
- [ ] A „Moje soutěže" card is clickable anywhere → competition detail; **„Zobrazit na nástěnce" still works** and there is **no nested interactive element inside a link** (check the markup, not the feel).
- [ ] „Všechny soutěže" renders **below** the grid.
- [ ] No soutěž roletka; `?zapasy=` does nothing and no dead plumbing reads it.
- [ ] Filters render as chips ≥ the desktop breakpoint and as a dropdown below it, both driving the same `?filtr=` param, **both working with JavaScript off**.
- [ ] The match card matches the mock's structure, is **one** link, and shows „Zadat tip" only when tippable.
- [ ] All four `MatchRow` surfaces render correctly in every state: open · tipped · live · locked · finished-with-points · playoff.
- [ ] Zero overlaps and zero horizontal overflow on all four surfaces at **1600 / 1440 / 1280 / 1024 / 768 / 430 / 320 px**.
- [ ] „Rozložení tipů" still resolves in ONE batch per page (assert the query count, as `DashboardFlowTest` already does).

## Verification

```bash
docker compose exec web composer cs:fix
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/Portal
docker compose exec web vendor/bin/phpunit tests/Integration/Public
docker compose exec web vendor/bin/phpunit tests/Integration/Query
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk; strip ANSI codes before grepping.

**`composer quality` cannot see any of this.** Measure geometry in a real browser:

- Pairwise bounding-box intersection across painted leaves on all four surfaces at the widths above —
  **zero overlaps, zero horizontal overflow**. This is the harness B7 and B8 used; B7 found a control
  overflowing because a flex child lacked `min-width: 0`, and B13 later established that `min-width: 0`
  is **necessary but not sufficient** when a child has an intrinsic width.
- **`MatchRow` is container-relative by design** (B7): competition detail is **narrower at 1440 px than
  at 1024 px** because of its aside, so viewport breakpoints are the wrong tool. Measure the **row's own
  width**, and give it realistic narrow containers.
- If you count wrapped lines, `getClientRects().length` on a **block** element is always 1 — use a
  `Range` over the contents, clustered by vertical centre.
- Check the filter dropdown with **JavaScript genuinely disabled** in the browser, not simulated.

After `composer db:reset` you **must** `docker compose restart web`, or every page 500s on stale
FrankenPHP worker connections. Never run `asset-map:compile`; if assets look frozen, `rm -rf public/assets`
then restart `web`.

## CSS discipline

You own **`assets/styles/app.css`** — the highest-risk file in the repo. The card lives in the
„Horizontal match row" section; B7's block is at its end and B20/item 17's nav rules are elsewhere.
Reuse first, new rules at the **end of that section** under `/* --- item 18: match card --- */`, never
reorder or reformat existing rules. **Do not touch item 17's `.bell-panel` or `.credit-chip` rules.**

## Git

**Commit with `git commit -o <path> [<path>…] -m …`** (`-o` = `--only`) — index-independent. Earlier
today an agent staged explicit paths, verified `git diff --cached --stat`, and still swept in another
agent's `app.css`, because a sibling staged into the index between the `add` and the `commit`.
(`-o` only accepts *tracked* paths; a new file needs `git add -- <file>` first.)

Never `git add -A` / `git add .` / `git commit -a`. Do not run `composer cs:fix` repo-wide if anything
else is in flight. Another session also commits here — `git pull --rebase` if a push is rejected, never
force-push. **There has been an API outage today that killed five agents mid-task: commit early and
often, and if you sense trouble commit the verified part and say what is unverified.**

## Domain guard rails

- Czech in the UI, English in code. **Never „sázka"** or its verb forms.
- **`TipStatsProvider` batched per page, never per row.**
- Prices from `Credits/PricingConfig`.
- Every route declared in `tests/Integration/Security/AnonymousReachabilityTest`; `/nastenka` stays 🔒.
- Nothing inside the app may 404 — grep any route or param you remove.

## Assumptions made

_(Implementer appends here if the item did not answer a question it had to answer.)_
