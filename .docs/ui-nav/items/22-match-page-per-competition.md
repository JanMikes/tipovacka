# Item 22 — one match page per soutěž; `/zapasy/{id}` becomes the source-side page

**Status:** TODO
**Filed:** 2026-07-30, from a product-owner report.

## The report

> „We have 2 different routes for matches — one it is match with competition context and once it is
> only the match (for all competitions). On dashboard there is link (wrongly) to the generic match
> route, this should be the match for selected competition. Basically to the generic match route I
> should be able to get only from admin and we can gate it by admin — check where are links and make
> it 2 separate controllers if not already, one for admin and others for match per competition."

## What is actually there today (established from the code, 2026-07-30 — do not re-derive)

**They are already two controllers.** Nothing needs splitting. What they are is *near-duplicates*:

| | `/zapasy/{id}` | `/souteze/{competitionId}/zapasy/{sportMatchId}` |
|---|---|---|
| route name | `sport_match_detail` | `competition_sport_match_guesses` |
| controller | `Portal\SportMatch\SportMatchDetailController` | `Portal\Guess\SportMatchGuessesController` |
| template | `portal/sport_match/detail.html.twig` (475 l.) | `portal/guess/detail.html.twig` (301 l.) |
| soutěž comes from | `?soutez={uuid}` + `<twig:SoutezSwitcher>`, falls back to the first including one | the path |
| access | `#[IsGranted('ROLE_USER')]` + `SportMatchVoter::VIEW` | `+ CompetitionVoter::VIEW`, `GuessVoter::VIEW`, and `MatchNotInCompetition` if the scope excludes it |

**Neither is a superset of the other.** Sections unique to each:

- only `/zapasy/{id}`: the team form („ARG · V2 R0 P0", `GetTeamForm`), the `SoutezSwitcher`,
  **B4's „Proč tu nejsou všechny vaše soutěže"** panel, and **„Správa zápasu"**
  (upravit / odložit / přesunout / zrušit / smazat).
- only the soutěž-scoped one: **„Jak tipovali ostatní"** (`Guess:MatchGuessesList`), the organizer's
  **per-match uzávěrka** form (`CompetitionMatchDeadlineFormType` → `competition_sport_match_set_deadline`),
  **„Tipy členů"** (on-behalf rows, privacy-gated), plus `Boost:Panel feature="others"` and
  `PremiumTeaser`.
- duplicated in both: hero + status pill, `_timeline.html.twig` („Průběh zápasu"),
  `Match:TipStats` („Rozložení tipů"), „Pořadí za zápas" (`GetMatchRanking`).

**`portal/guess/detail.html.twig` is NOT a partial.** `UI-MAP.md` §2 currently says it „is a partial
rendered inside the match/tip surfaces — no route of its own". That is wrong — it is that route's
whole template, `{% extends 'base.html.twig' %}`, and nothing includes it. Fix the line.

### Who links where (complete, `grep`ed)

To `sport_match_detail` — **the wrong ones are the first three**:

| Call site | What it is |
|---|---|
| `templates/portal/_dashboard_match_row.html.twig:68` (`detailUrl`) and `:70` (`tipUrl`) | Nástěnka match cards — **the reported bug** |
| `templates/portal/dashboard.html.twig:165` | Nástěnka „Poslední Tvoje tipy" result rows — **same bug** |
| `templates/portal/matches/index.html.twig:107` (`detailUrl`) and `:109` (`tipUrl`) | the `/zapasy` feed |
| `templates/design/styleguide.html.twig:65` | `/_design` sample URL (inert — `href`s are stripped) |
| `templates/portal/match_source/detail.html.twig:107` | the **source owner's** own zdroj page — correct, stays |
| `templates/portal/sport_match/form.html.twig:18`, `set_score.html.twig:29` + `:198` | breadcrumbs / „zpět" of the management forms — correct, stays |
| **12 `redirectToRoute('sport_match_detail')` calls** in `SetFinalScoreController`, `RescheduleController`, `PostponeController`, `CancelController`, `SoftDeleteController`, `UpdateSportMatchController`, `CreateSportMatchController` | every match-management action lands here — correct, stays |

To `competition_sport_match_guesses`: `templates/portal/competition/detail.html.twig:307` (already
correct), plus redirects in `SubmitGuessOnBehalfController:87` and
`SetCompetitionMatchDeadlineController:56`.

### Why „gate it by ROLE_ADMIN" is the wrong gate — and what the product owner chose instead

`/zapasy/{id}` is the landing page of every match-management action (the 12 redirects above), and
`SportMatchVoter` grants those actions to `$isAdmin || ($isOwner && $matchSource->isActive)` — where
the owner is the owner of the **match source**. A `private` source belongs to whatever ordinary user
created a from-scratch competition. A literal `#[IsGranted('ROLE_ADMIN')]` would 403 that organizer
the instant they saved a score.

**Product-owner decision (2026-07-30): gate it on „admin OR the match source's owner".**

## What to build

### 1. `/souteze/{competitionId}/zapasy/{sportMatchId}` becomes THE match page

Move every player-facing section of item 10's page onto it, so a player never needs the generic
route. End state, in this order (it is item 10's order with the soutěž-scoped extras folded in):

1. **Hero** — status `Pill` (Naplánován / Živě / Ukončeno / Odložen / Zrušen), meta line
   „kolo · venue · datum", teams + `TeamFlag` + the big score (kickoff before it), **the team form
   sub-label** („ARG · V2 R0 P0", `GetTeamForm` scoped to what THIS soutěž includes — one query for
   both teams; absent, never zeroed), and „Zapsat výsledek" for whoever passes
   `SportMatchVoter::SET_SCORE`.
2. **`<twig:SoutezSwitcher>`** beside the tip form — see §3.
3. **Tip form** (`Guess:GuessSubmitForm`) + **B4's „Proč tu nejsou všechny vaše soutěže"** panel.
   Keep B4's invariant: the switcher lists the soutěže that INCLUDE this match, the panel explains
   the ones that EXCLUDE it, and the two sets stay disjoint, so no soutěž is ever described by both.
   The panel's data comes from the same membership sweep item 10's controller does — port it,
   including `exclusionReason()` and the `teams` filter-team lookup.
4. **„Rozložení tipů"** — `<twig:Match:TipStats :compact="false">` from `TipStatsProvider`.
5. **„Průběh zápasu"** — `_timeline.html.twig`.
6. **„Pořadí za zápas"** — `GetMatchRanking`, gated through `TipVisibilityGate`.
7. **„Jak tipovali ostatní"** — `Guess:MatchGuessesList`.
8. Organizer-only: the **uzávěrka** form and **„Tipy členů"**, exactly as they are today.

**Resolve the §6-vs-§7 overlap rather than stacking both blindly.** Both reveal other members'
tips for this match. Read them, decide whether they say the same thing in the same states, and
either keep both with a clear division of labour or fold one into the other. Item 10 chose „Pořadí
za zápas" (rank · tip · přesnost · body, and it drops the rank/points columns before the match is
scored rather than filling them with dashes) as the richer surface. **Write down which you did and
why** — a page with two lists of the same tips is worse than either alone.

**Rename**, since the page is no longer „the guesses" (no back-compat is owed — see `PLAN.md`):

- route `competition_sport_match_guesses` → `competition_sport_match_detail`
- `App\Controller\Portal\Guess\SportMatchGuessesController` →
  `App\Controller\Portal\Competition\CompetitionMatchDetailController`
- `templates/portal/guess/detail.html.twig` → `templates/portal/competition/match_detail.html.twig`

Only three non-test callers reference the old route name; `grep` for all of them (tests included)
and fix every one in the same commit. **`/zapasy/{id}`'s route name `sport_match_detail` does NOT
change** — 12 redirects point at it and they all still want that page; only its content and its gate
change. Make its docblock say plainly that it is the source-side page, not the player's.

### 2. `/zapasy/{id}` becomes the source-side match page

Strip it to: **hero + „Průběh zápasu" + „Správa zápasu"**. Delete from it the switcher, the tip
form, the B4 panel, „Rozložení tipů", „Pořadí za zápas" and the team form — and with them the
controller's membership sweep, `TipStatsProvider`, `TipVisibilityGate`, `GetMatchRanking`,
`GetTeamForm` and `EffectiveTipDeadlineResolver` wiring. It should end up a small controller.

**Gate:** add `SportMatchVoter::MANAGE = 'sport_match_manage'` = `$isAdmin || $isOwner`, and use it
on the page. Note the deliberate difference from the action attributes: **not** `&& $matchSource->isActive`
and **not** blocked on a cancelled/`deletedAt` match, because the page must stay reachable for a
completed source and after a cancel/postpone — those actions redirect here. The per-action buttons
inside „Správa zápasu" keep their own stricter attributes (`EDIT`, `SET_SCORE`, `CANCEL`, `DELETE`),
so an owner of a completed source sees the page and no dead buttons.

Verify this against every redirect: after cancel, after soft-delete, after postpone, after
reschedule, after set-score, after edit, after create — each must land on a page the actor can still
see. (Soft-delete is the one to think about: check what the page does with a deleted match today and
keep that behaviour, whatever it is.)

### 3. The switcher, on a route whose path carries the competition

`.docs/features/competition-switcher.md` documents a hard constraint: `SoutezSwitcher` is a plain GET
`<form>` that can only append `?<param>=<id>`, so **`route` must be reachable with no path parameter
carrying the COMPETITION**. `/souteze/{competitionId}/zapasy/{sportMatchId}` breaks that.

**Recommended mechanism — verify it before building it:** leave the component alone and make the
*page* accept `?soutez={uuid}`: when present and different from `{competitionId}`, **302 to
`/souteze/{that}/zapasy/{sportMatchId}`**. Then the switcher is rendered with
`route="competition_sport_match_detail"` and `:routeParams="{competitionId: <current>, sportMatchId: <match>}"`,
its form action is the current page, and the query parameter wins and redirects. Canonical URLs stay
path-based, the control stays a real GET form, and **the component needs no change at all**.

Apply the same fallback rule the page needs anyway: a `?soutez=` that is unknown, foreign, or whose
soutěž does not include this match must **not** 403 — falling back silently is how the nástěnka, the
žebříček and item 10's page all prevent „guessing a UUID tells you it exists".

If you find a reason this does not work, say so and pick the smallest alternative — but do not
quietly turn the switcher into a JS-only control, and **do not undo its JS-free operation**
(`PLAN.md` decision 0: JS-off support is deferred *forward*, nothing already built gets undone).
Update `.docs/features/competition-switcher.md` with whichever pattern you land.

### 4. Re-point every player link (this is the reported bug)

- **Nástěnka** (`_dashboard_match_row.html.twig:68,70` and `dashboard.html.twig:165`) → the
  soutěž-scoped route, using the soutěž the page is already scoped to. Since item 18 the Nástěnka is
  **always** scoped to exactly one soutěž (the `?zapasy=vse` widener was deleted), so the id is
  right there — confirm that rather than assuming, and if a row can appear that is not in the scoped
  soutěž, say what you did about it.
- **`/zapasy`** (`matches/index.html.twig:107,109`) — cross-competition, so a match can sit in
  several of the viewer's soutěže. Product-owner decision: **keep the page for players and link per
  soutěž.**
  - The card's own link (`detailUrl`) → the match in the **first** of the viewer's soutěže that
    includes it, in whatever order the page's existing query already returns. Reversible, and
    unreachable soutěže stay reachable via the strips below.
  - `tipUrl` → the soutěž where the tip is actually **missing** (item 21 already gates `tipUrl` on
    a tip missing *somewhere*; now it can point at the right one). Several missing → the first.
  - **Each „Rozložení tipů" strip's heading becomes a link to that soutěž's match page.** This is
    safe: `.tip-row-extra` is a **sibling** of the card's `<a class="tip-row-link">`, not inside it,
    so nothing interactive gets nested (see the component's own docblock). `TipStats` already
    carries `competitionId`.
    - `Match/MatchRow` needs the match id to build those links. Recommended: a new `sportMatchId`
      prop; the component builds the strip links with `path()`. If you choose differently, record
      why. **The component must not query anything** — `TipStats` still arrives batched from
      `TipStatsProvider` (the documented N+1 trap: never per row).
- **`/_design`** (`styleguide.html.twig:65`) → the soutěž-scoped route, so the gallery does not
  advertise a page a player can no longer reach. The page stays inert (`href`s stripped by the
  `inert()` macro) and must keep `DesignStyleguideFlowTest::testNothingOnThePageCanAct` green.
- **Leave alone**: `match_source/detail.html.twig:107`, the two management-form breadcrumbs, and all
  12 redirects. Those actors pass the new gate by definition.

### 5. The match card and „Váš tip" become ONE card

**Product-owner report, 2026-07-30**, on
`/souteze/019fae50-f5af-70b7-a767-ff28a08b2ef1/zapasy/019fa008-7233-7603-b414-e0fb581541ef`:

> „the my guess is separate card from the match card (teams info) […] i want to merge them into one
> card and save space this way (we can deduplicate teams names this way) […] on my image there is
> not visible „Váš tip" but i believe it should be there too so it is obvious it is my guess —
> update the design so it is close to what i propose — **the most important is merging into one
> card**"

Today the page renders the fixture hero in one card and `Guess:GuessSubmitForm` in a second one
below it, so the two team names appear twice and the pair costs two card frames of vertical space.

**The mock was pasted inline in the conversation and is not on disk**, so it is described here
instead — the item file has to stand on its own. Everything below is what the mock shows, in ONE
card frame:

```
┌──────────────────────────────────────────────────────────────────────┐
│  1. kolo                                              [ • BRZY ]     │
│  3. MSFL sezóna 26/27 · 31. 7. 2026 · 18:00                          │
│                                                                      │
│              (FRÝ)              VS              (HRA)                │
│           Frýdek-Místek                        Hranice               │
│              [  ▲▼ ]             :             [  ▲▼ ]               │
│                                                                      │
│  ┌────────────────────  ◎  Uložit tip  ────────────────────────────┐  │
└──────────────────────────────────────────────────────────────────────┘
```

- one card frame, no inner card;
- header line: „1. kolo" bold, the state pilulka („BRZY") right-aligned on the same line, and the
  meta line „zdroj · datum · čas" beneath it;
- each side renders its monogram coin, its team name, and **its own score spinner underneath** —
  so the name sits above the input it belongs to and is written exactly ONCE on the page;
- „VS" between the coins, „:" between the two inputs;
- a full-width primary „Uložit tip" button as the card's footer.

**What the mock is missing, and the product owner says so themselves:** a **„Váš tip"** label. Add
it so the input row is unmistakably the viewer's own guess and not the match result. Place it where
it reads as the label of the input row.

**The hard part is the states the mock does not show.** Design the merged card for all of them and
verify each:

| State | What must be true |
|---|---|
| open, no tip | the mock exactly: empty spinners + „Uložit tip" |
| open, tip exists | prefilled spinners; the button says whatever it says today for an edit |
| **finished** | the **real result** and **„Váš tip"** are both on the card and **can never be confused for each other** — this is the case the merge puts at risk, because until now they lived in separate cards. The points badge belongs with the tip, not with the result. |
| locked, tip exists | today's locked panel („Tipování uzavřeno — uzávěrka proběhla …" + the tip, period scores, overtime, střelci) inside the merged card, with no inputs |
| locked, never tipped | „Netipováno." (B5: a fact, not a call to action) |
| error / success | the component's existing inline error and success banners still render inside the card |

**Everything `GuessSubmitForm` already does must survive**, and one part of it is fragile:

- The **scorer picker lives in a `data-live-ignore` island** so a Live Component re-render cannot
  destroy its tom-select instance, and its state reaches the component through a hidden
  `scorersJson` input (see `.docs/features/scorer-picker.md`). **Restructuring the markup around it
  is the main regression risk of this whole section.** After the change, add a scorer, save, and
  re-render — the chip must survive. B31 seeded a reachable scorer picker for exactly this
  („Sousedský pohár", World B — see `.docs/FIXTURES.md`).
- The **optional rule fields** (per-period scores, overtime, scorers) appear only when the
  competition enables them. The merged card must look right with none, one and all of them.
- `:bare="true"` (B19) exists precisely for a call site that already renders a card. It is the
  natural hook here — but note B19's rule: an embedded call site says `bare`, it does **not**
  re-neutralise the default chrome with utilities.

**`Guess:GuessSubmitForm` has exactly two call sites** — `portal/guess/detail.html.twig:122` and
`portal/sport_match/detail.html.twig:164` — and this item owns both. `/moje-tipy` and
`/spravovat-tipy` use their own batch inputs, **not** this component, so they cannot regress from
this change; confirm that rather than trusting it. Since §2 deletes the tip form from
`/zapasy/{id}` altogether, the merged card ends up with **one** call site.

Follow item 21's precedent for the fixture block: it renders the score when there is one and „vs"
when there is not, never a duplicated kickoff time, and it is container-relative — the same card
renders from 1088 px down to 238 px with no width media query. Do not regress that; the merged card
must survive a 320 px viewport (measure it — see Verification).

## What must NOT change

- **`assets/styles/app.css` is owned by another agent this round. Do not touch it.** If you need a
  style for the linked strip heading, use Tailwind utilities in the template — `PLAN.md`'s CSS
  discipline prefers that anyway for a one-off.
- **`.docs/ui-nav/PLAN.md` and `.docs/ui-nav/BUGS.md` are owned by another agent this round. Do not
  touch them.** The orchestrator updates the board from your report.
- `TipStatsProvider` stays batched per page. `CompetitionMatchProvider` stays the only answer to
  „what's in this competition". Prices only from `Credits/PricingConfig`.
- **Managers and admins get no free entitlement pass** (`CompetitionEntitlements`) — the merge must
  not accidentally widen who sees others' tips. `TipVisibilityGate` keeps deciding, and „Tipy členů"
  keeps showing a manager only *whether* a member's tip is filled unless they are entitled.
- Czech in the UI, English in code and comments. Never „sázka" or any of its forms.
- **Nothing inside the app may 404 or 500.** The gate and the re-pointing must land **together** —
  a commit that gates `/zapasy/{id}` while the Nástěnka still links there would 403 every member.
  One commit for the whole item is fine and probably right.

## Acceptance criteria

1. A plain member (no admin role, owns no source) can reach a match **only** through
   `/souteze/{cid}/zapasy/{mid}`, and gets **403** on `/zapasy/{id}`.
2. A source owner who is not an admin can reach `/zapasy/{id}` for their own source's match, and
   still lands on a page they can see after each of: set score, edit, postpone, reschedule, cancel,
   soft-delete, create.
3. An admin can reach `/zapasy/{id}` for any match.
4. Every section listed in §1 renders on the soutěž-scoped page, for: open / tipped / locked /
   finished, entitled / not entitled, member / organizer.
5. The Nástěnka's match cards, its „Poslední Tvoje tipy" rows and `/zapasy`'s cards all link into a
   soutěž. On `/zapasy`, a match in **two** of the viewer's soutěže offers a link to each.
6. The switcher on the soutěž-scoped page moves the viewer between the soutěže that include the
   match, and a `?soutez=` that is unknown/foreign/excluding falls back instead of 403-ing.
7. `tests/Integration/Security/AnonymousReachabilityTest` knows both routes (it is keyed by
   controller class, and one controller is renamed and moved — it will fail until you update it).
8. `UI-MAP.md` reflects the new split, and the „`portal/guess/detail.html.twig` is a partial" claim
   is gone.
9. The fixture hero and the tip form are **one card**, each team name written once, with a „Váš tip"
   label on the input row — and on a finished match the real result and the viewer's tip are both
   present and unconfusable.
10. The scorer picker still survives a Live Component re-render (add a scorer, save, re-render, chip
    still there) — verified in a browser on World B („Sousedský pohár"), not by reading the diff.

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Load every touched page.** `composer quality` passes on a template that throws at render time.
- These integration files all touch the two routes and will need reading, and several will need
  updating: `Portal/Guess/SportMatchGuessesFlowTest`, `Portal/SportMatch/MatchDetailPageTest`,
  `Portal/SportMatch/MatchDetailCompetitionScopeTest` (B4), `Portal/SportMatch/SportMatchTimelineFlowTest`,
  `Portal/MatchesFlowTest`, `Portal/MatchRowTipLinksTest`, `Portal/TipStatsSurfacesTest`,
  `Portal/PremiumTeaserFlowTest`, `Portal/Competition/{BoostFlowTest,CompetitionDetailPassTest,LockedStateSurfacesTest,OnBehalfTipPrivacyTest}`,
  `Portal/Leaderboard/GuessMatrixVisibilityTest`, `Portal/SportMatch/{CancelDeleteFlowTest,PostponeRescheduleFlowTest,SetFinalScoreFlowTest,UpdateSportMatchFlowTest,CreateSportMatchFlowTest,BulkImportFlowTest}`,
  `DesignStyleguideFlowTest`. **Never run `phpunit tests/` whole — it OOMs (exit 137).** Chunk by
  subdirectory; strip ANSI before grepping.
- **Add coverage for the gate itself** (criteria 1–3): a plain member 403s, a non-admin source owner
  does not, an admin does not. This is the part that would silently rot.
- Measure nothing by eye that is geometric: if the merged page changes a layout, check bounding-box
  intersection across painted leaves at several widths, and box `width` vs `scrollWidth` for
  truncation (a `Range` measures text ink, and ink is not clipped by `overflow`).
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. The index is shared mutable state and verifying it proves nothing. Push to
`main`. Do not update the status board — report your sha and the orchestrator records it.

---

## Assumptions made

Decisions the item file did not settle, taken on the most conservative reading and recorded here.

1. **§6 vs §7 genuinely duplicated each other, so „Jak tipovali ostatní" was FOLDED INTO „Pořadí
   za zápas".** Read side by side, `GetMatchRanking` and `GetGuessesForMatchInCompetition` list
   the same rows — every active guess for the same (soutěž, zápas) pair, behind the same
   `TipVisibilityGate` decision. Unscored, the ranking already *called itself* „Jak tipovali
   ostatní" (item 10's assumption 3), i.e. the two were the same block wearing two names; scored,
   the ranking is a strict superset (rank · přesnost · body). The only thing the folded-away block
   had and the ranking did not was the **optional tip detail** — per-period scores, prodloužení,
   střelci — so `MatchRankingRow` now carries those three and the table prints them under the tip
   (fetch-joined, never an N+1). `submittedAt` was **dropped**: „when was this tip filed" says
   nothing on a ranking table. `Guess:MatchGuessesList` (component + template) is deleted.
2. **`GetGuessesForMatchInCompetition` is left in place, unused.** It was the folded-away block's
   read model and now has **no production consumer**; its `BoostTipVisibilityTest` /
   `GetGuessesForMatchInCompetitionQueryTest` still pass. It was not deleted because
   `.docs/DOMAIN.md` names it as a `TipVisibilityGate` consumer and DOMAIN.md was owned by another
   agent this round — deleting the query would have left that line wrong with no way to fix it.
   **Orchestrator's call**: delete the query + its 4 DTOs and re-target the boost-visibility
   coverage at `TipVisibilityGate`, or keep it as a read model with tests. Flagged, not decided.
3. **The recommended `?soutez=` → 302 mechanism works; nothing else was needed.** Verified in a
   real browser with JavaScript on: the tom-select fires the GET form, the page 302s to
   `/souteze/<chosen>/zapasy/<match>`, and an id that is unknown, foreign OR merely EXCLUDING the
   match falls through to the soutěž in the path (200, never 403). The component is untouched.
4. **The switcher sits ABOVE the merged card, not beside the tip form.** §1 puts it „beside the tip
   form", but the tip form is now inside the card the switcher would scope, and a control that
   reloads the page has no business inside the card it changes. It is the first thing under the
   breadcrumbs, in a „Zápas v soutěži · Tipujete za soutěž X" row.
5. **The status pilulka keeps item 10's five states** (Naplánován / Živě / Ukončeno / Odložen /
   Zrušen). §5's mock shows „BRZY", which is the old guess page's vocabulary; §1 names the five
   explicitly and it is the page's specification, so it wins. Nothing is lost — the tip block
   itself still says „Tipování uzavřeno — uzávěrka proběhla …" when the window has shut.
6. **The card's fixture and the tip spinners are two grids sharing one centre track**
   (`--mc-gutter`, 96 px → 72 px under 480 px), both capped at 560 px and centred. They are
   separate components, so alignment only holds while their tracks are IDENTICAL; an `auto` centre
   track would be as wide as the score in one and as wide as „:" in the other, and every name
   would sit half the difference off its input. Measured: 0.0 px misalignment at 1440 / 1024 /
   768 / 430 / 375 / 320 px.
7. **The viewer's points badge rides the „Váš tip" eyebrow** and comes from one
   `GuessEvaluationRepository::findByGuess` on the viewer's own guess — not from `GetMatchRanking`,
   which is behind the entitlement gate. A viewer must always see their OWN points.
8. **The „Rozložení tipů" strip heading is a link only where a heading exists** — i.e. when the
   card carries MORE than one strip (`/zapasy`, the Nástěnka). On competition detail there is one
   strip and no heading, and the page already IS that soutěž.
9. **`/zapasy` cards link to the FIRST including soutěž** (`UserMatchItem::$competitionIds[0]`, the
   query's own order) and „Zadat tip" to the first soutěž where the tip is actually missing
   (`$pendingCompetitionIds[0]`). Both are new list fields on the read model; the component still
   queries nothing.
10. **B4's „Tenhle zápas se ve vašich soutěžích netipuje" headline is gone** — not regressed. On a
    soutěž-scoped page the viewer is always in a soutěž that includes the match, so „no including
    soutěž at all" is unreachable: the page itself is. The panel keeps its other headline and all
    four reasons.
11. **The organizer's „Tipy členů" now compares the deadline against the app's clock**, passed in
    as `now`, instead of Twig's `date()`. `date()` reads the SYSTEM clock, which disagrees with
    `MockClock` under test — the old template had the same bug and it surfaced the moment the
    block moved onto a page whose lock state is asserted.
12. **`/zapasy/{id}` after a soft-delete still renders** (`SportMatchRepository::find` does not
    filter `deletedAt`) — unchanged behaviour, and the item asked for whatever it was to be kept.
    Walked by hand as a NON-admin source owner: postpone → reschedule → cancel → soft-delete each
    land on a 200. An owner of a COMPLETED source sees the page and **no** „Správa zápasu" block at
    all, because every per-action attribute still requires `$matchSource->isActive`.
