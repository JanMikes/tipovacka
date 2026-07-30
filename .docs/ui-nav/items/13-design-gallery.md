# 13 — `/_design` becomes the live component gallery (plus a deferred section)

> **Status:** DONE
> **Depends on:** nothing. Runs concurrently with item 12 and B9 — read „Files another agent owns".
> **Owner decision date:** 2026-07-30

## Why (the requirement, in the product owner's terms)

`/_design` has had a **purpose conflict** since it was written, and that is why it keeps falling out
of date:

- **The template says it is for deferred work.** `templates/design/styleguide.html.twig:3-14`:
  *„DEV/ADMIN-ONLY reference styleguide for **DEFERRED** (🔮) design-system elements. […] CUT items
  […] must NOT appear here. Each section is labeled „Připravujeme / reference"."*
- **`PLAN.md` says it is the shop window for everything shared:** *„The styleguide page `/_design` is
  the shop window. If you add or change a shared component, render it there."*

Those cannot both be true, and the page has already drifted into being both: the `SoutezSwitcher`
section carries a **„Hotovo"** pill, not „Připravujeme". The visible symptom the product owner named:
**the competition card and filter bar — the components items 07 and 11 built — were never added.**
(Item 07 recorded why: a parallel agent was mid-flight on that exact file, so the stream's
one-owner-per-shared-surface rule kept it out. Item 11 skipped `MatchRow` for a different reason,
see below.)

**Decision (product owner, 2026-07-30): it becomes the live gallery of shipped shared components,
with the deferred items kept in their own clearly-marked section.** One page, two clearly separated
halves. That way „render it there" is an instruction an implementer can actually follow, and a
reviewer can see the whole component vocabulary on one screen.

## What changes

### 1. The page is restructured into two halves

| Half | Contents | Label |
|---|---|---|
| **A — Sdílené komponenty** (new, first) | Every shipped shared component from `UI-MAP.md` §3, rendered with sample data | `<twig:Pill label="Hotovo" variant="done" />` per section |
| **B — Připravujeme / reference** (the existing five sections, moved down) | premium/contribution tiers, scorers editor, notification bell + feed, Δ rank column, and whatever else is genuinely not built | `<twig:Pill label="Připravujeme" variant="soon" icon="hourglass" />` per section, unchanged |

The intro copy and the page docblock must be rewritten to describe **both** halves. Keep the two
invariants that still hold:

- **Everything on the page is INERT.** No working JS, no Live wiring, no `wtips:open-premium`, no
  dead handlers, no form that submits, no POST. Buttons are `type="button"` without
  `data-action`; anything that would navigate or submit gets `disabled` / `aria-disabled` or has its
  action neutralised. This applies to the *new* half too — `Competition:FilterBar` is a real GET
  `<form>` and `Competition:Card`/`MatchRow` contain real `<a href>`s (see „Implementation notes").
- **CUT items must not appear** (OAuth, in-product live match, Tweaks panel, payouts).

Also still true and must be preserved: **admin-only** via the in-controller
`denyAccessUnlessGranted('ROLE_ADMIN')` (the route is not under an `access_control` prefix), and
**not linked from the production nav** — URL-only.

### 2. What half A must render

At minimum, everything below. Each gets a short Czech caption naming the component
(`<twig:Competition:Card>`) and, in one sentence, when to use it.

| Component | Variants to show |
|---|---|
| `Competition:Card` | `context="public"` **and** `context="organizer"`; a free one and one with an entry fee; a live one, an upcoming one and a finished one |
| `Competition:FilterBar` | one bar with sport + stav chips, a search value and a filtered count; show it once (the `prefix`/`anchor` mechanism explained in the caption, not duplicated) |
| `Competition:PlayingCard` | one with a rank + round gain + a pending tip, one for a finished competition |
| `Match:MatchRow` | the five `state` values — `open`, `tipped`, `live`, `locked`, `finished` — including one with `points` (the „+5" badge), one with `tipPrompt`, one with `tipMissingLabel`, one playoff row, and one with a long team name so the ellipsis is visible |
| `Match:TipStats` | `compact=true` (the strip inside a card) **and** `compact=false` (the full card); for each: entitled/visible, the **premium** paywall, the **boosts** paywall, and the „nothing to sell yet" (`Zobrazí se po uzávěrce`) variant |
| `Pill` | every variant in use: `done`, `locked`, `warn`, `soon`, `accent`, `organizer` |
| `Badge`, `StatCard`, `EmptyState`, `Breadcrumbs`, `Avatar` (incl. rank 1–3), `TeamFlag` (incl. a team with no logo → monogram), `Leaderboard:Podium`, `Leaderboard:Delta` (up / down / zero / `isNew`, variant `chip`) | one of each, enough to show the variants |
| `SoutezSwitcher` | **already on the page — keep both existing variants and their test assertions working.** |

If a component genuinely cannot be rendered inertly, say so in `## Assumptions made` and skip it —
do not fake it with copied markup. **Never hand-copy a component's markup into the styleguide**: the
whole point is that the page renders the real component, so it cannot drift.

### 3. `templates/design/styleguide.html.twig:80,88` — the boost rename

Those two lines currently say „**Lišta tipů ostatních**" / „Lišta tipů plus konkrétní tipy…".
**Item 12 renames that boost to „Rozložení tipů ostatních" everywhere** and explicitly excludes this
file because you own it. So: while you are in there, change those two strings to
„**Rozložení tipů ostatních**" and „**Rozložení tipů** plus konkrétní tipy soutěžících v partičce."

Anything else you write on this page must use „**Rozložení tipů**" — never „Distribuce tipů", never
„Lišta tipů".

### 4. `PLAN.md`'s „shop window" rule

Do **not** edit `PLAN.md` (see „Files another agent owns"). Instead, in your final report, give the
orchestrator the exact replacement wording for the rule, now that the decision makes it real —
something to the effect of: *the page has two halves; a new or changed shared component goes in half
A, a deferred design-system element in half B.*

## Out of scope

- **No change to any shared component itself.** You render them; you do not restyle, refactor or
  re-prop them. If rendering one reveals a bug, **write it down in your report** and leave it — it
  becomes its own `BUGS.md` row. The one exception is if a component cannot be rendered without a
  purely additive, backwards-compatible prop, and even then: prefer skipping and reporting.
- **No new shared component.**
- **No route change.** `/_design` keeps its path, name (`app_design_styleguide`), controller class
  and admin gate. It stays out of the nav. Its row in
  `tests/Integration/Security/AnonymousReachabilityTest` is already correct — do not change the
  security posture. (If you touch that test at all, you have gone wrong.)
- **No real data.** The page must not query the database for competitions, matches, users or teams.
  Sample DTOs only (see below). It must render identically on an empty database.
- **Do not add the page to the nav or link to it from anywhere.**

## Implementation notes

### Feeding the components — the precedent already exists

`src/Controller/DesignStyleguideController.php` already hand-builds
`list<CompetitionListItem>` in a private `sampleCompetitions()` method with literal UUIDs and dates,
precisely because „the styleguide has no backend". **Follow that pattern** — one private
`sample…()` method per component family, returning literal DTOs. Keep them private, typed and
documented with the same one-paragraph rationale style.

All the DTOs you need are plain value objects you can construct with literals:

| Component | DTO | Notes |
|---|---|---|
| `Competition:Card` | `App\Query\ListBrowsableCompetitions\BrowsableCompetitionItem` | ~18 constructor args, all scalars/UUIDs/dates; `progressPercent`, `isFinished` and `state` are **hooked virtual properties** — they compute themselves from the counts, so pick counts that produce the state you want to show rather than trying to set it |
| `Competition:PlayingCard` | `App\Query\ListMyPlayingCompetitions\PlayingCompetitionItem` | `final readonly`, all scalars |
| `Match:MatchRow` | `homeTeam`/`awayTeam` accept **`TeamView|Team`** | `App\Value\TeamView` is constructible with literals (`id, name, shortName, country, brandColor, logo`) and computes its `monogram` hook itself |
| `Match:TipStats` | `App\Value\TipStats` | `visible`, `entitled`, `purchasable`, `price`, `balance`, `monetization` are the flags that select which of the four visual states renders; `hasAnythingToShow` / `canAfford` are hooks |
| `Competition:FilterBar` | plain arrays | `sportOptions`, `stateOptions`, `visibilityOptions` — look at how `/souteze`'s controller builds them (`CompetitionStateFilter::forScope()`) and mirror the *shape* with literals |

**Item 11 recorded that `MatchRow` „needs real `Team`/`TeamView` objects, and the styleguide has no
fixture plumbing" and skipped it. That assumption is now superseded** — `TeamView` takes six scalars
and the plumbing pattern is `sampleCompetitions()`. Render it.

**Prices in `TipStats` samples must come from `Credits\PricingConfig`, not literals** — `CLAUDE.md`
is binding on that even for a styleguide. Inject it into the controller.

### Keeping the new half inert

This is the part that needs care, because half A renders *production* components that contain real
links and a real form:

- `Competition:FilterBar` is a `<form method="get">`. Rendering it live would give the admin a filter
  bar that navigates away from `/_design` (or worse, to a page with the styleguide's fake query
  params). Neutralise it — the cheapest honest options are wrapping the section in something that
  disables its controls, or pointing the form at `/_design` itself so submitting is a harmless
  reload. **Pick one, state which in `## Assumptions made`, and make sure no control 404s.**
- `Competition:Card`, `MatchRow` and `TipStats` contain `<a href>`s built from the sample UUIDs —
  those routes exist but the ids do not, so clicking one is a 404 **inside the app**, which
  `PLAN.md` forbids. Deal with it deliberately: neutralise the anchors, or point every sample at a
  real, always-present target. Say what you chose and why.
- `TipStats`'s paywall CTA is a `confirm`-modal form that POSTs a boost purchase. It **must not be
  able to fire.** Verify by inspecting the rendered markup, not by trusting the section wrapper.

### CSS

You own `assets/styles/app.css` this round. Follow `PLAN.md`'s CSS discipline: reuse first, read the
`@layer components` block before adding anything, put new rules **at the end of the section they
belong to** under `/* --- item 13: design gallery --- */`, never reorder or reformat existing rules.
Gallery scaffolding (section frames, a swatch grid, „variant" captions) is exactly the kind of thing
that should be Tailwind utilities in the template, not new classes — add a class only if the pattern
repeats.

### Tests

`tests/Integration/DesignStyleguideFlowTest.php` exists and asserts the h1, a „Připravujeme" label
and both `SoutezSwitcher` variants (including `<optgroup>` order, `data-meta`, `data-sub` and the
`<noscript>` affordance). **Those must keep passing** — do not renumber or reword what they assert.
Add coverage for the new half:

- half A renders and is labelled „Hotovo";
- `Competition:Card` renders in both contexts;
- `MatchRow` renders all five states;
- `TipStats` renders both a visible split and a paywall;
- **the inertness guarantee**: no element on the page can POST a boost purchase, and the filter bar
  cannot navigate to a foreign route. Assert it on the markup.

Icons: any new `lucide:` icon must be imported first —
`docker compose exec web bin/console ux:icons:import lucide:<name>` — and the SVG committed under
`assets/icons/lucide/`. A missing icon is a **render-time exception** in dev
(`ignore_not_found: false`), which `composer quality` will not catch.

## Acceptance criteria

- [ ] `/_design` returns **200 for an admin**, **403 for a logged-in non-admin**, and redirects an anonymous visitor to login — unchanged from today.
- [ ] The page has two visually distinct halves, „Sdílené komponenty" (first) and „Připravujeme / reference" (second), each section carrying the right pill („Hotovo" / „Připravujeme").
- [ ] Every component in the table under „What half A must render" appears, rendered by the **real component tag** (grep the template: no hand-copied component markup).
- [ ] `Competition:Card` appears in both `public` and `organizer` context; `MatchRow` in all five states; `TipStats` in compact and full, entitled and both paywalls.
- [ ] Every sample price shown comes from `PricingConfig` (grep the controller: no literal credit amounts).
- [ ] **Nothing on the page can act:** no form can POST, no anchor leads to a 404, no Live Component is wired, no `wtips:open-premium`.
- [ ] The page renders with **no database rows** — it must not query for competitions, matches, teams or users.
- [ ] The two „Lišta tipů" strings are gone from the file; the page says „Rozložení tipů ostatních".
- [ ] The existing `DesignStyleguideFlowTest` assertions still pass, unmodified.
- [ ] No shared component's own template or props changed.
- [ ] `/_design` is still absent from `Layout/Nav.html.twig` and from every other template.

## Verification

```bash
docker compose exec web composer cs:fix
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/DesignStyleguideFlowTest.php
docker compose exec web vendor/bin/phpunit tests/Integration/Security
docker compose exec web vendor/bin/phpunit tests/Integration/Portal
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk by subdirectory. Strip ANSI codes before
grepping PHPUnit output.

`composer quality` **does not render templates** — a broken Twig tag, a missing icon or a wrong prop
name only shows at render time. So:

- Load `/_design` as an admin and confirm 200 and that **every** section painted.
- **Measure the layout, don't eyeball it.** This page is a grid of many components at once, which is
  exactly where overlap bugs hide. At **1600 / 1440 / 1280 / 1024 / 768 / 430 px**, check
  pairwise bounding-box intersection across painted leaves and zero horizontal overflow — the same
  harness B7/B8 used (see `BUGS.md` B7 „Verified by driving Chrome headless…"). Report the numbers.
  Note `MatchRow` is **container-relative by design** (B7): its zones wrap to the width of the column
  it sits in, so give it a realistic narrow container in the gallery, not just full width.
- Confirm as a non-admin (403) and anonymous (redirect).
- After `composer db:reset` you **must** `docker compose restart web` or every page 500s on stale
  FrankenPHP worker connections. Never run `asset-map:compile`; if assets look frozen,
  `rm -rf public/assets` then restart `web`.

## Git discipline

- **Never `git add -A`, `git add .` or `git commit -a`.** Two other agents are working in this same
  checkout right now, and a third session has been committing to this repo. Stage your own files by
  explicit path only, and verify with `git diff --cached --stat` before committing that the index
  holds nothing but them.
- `assets/styles/app.css` is the highest-risk file in the repo and **you are its only owner this
  round** — but still stage it by path, never wholesale.
- One commit: `UI: /_design becomes the live component gallery`, push to `main`.
- Do **not** edit `.docs/ui-nav/PLAN.md` or `UI-MAP.md` (orchestrator-owned this round). Report your
  sha plus the replacement „shop window" wording and the `UI-MAP.md` §3 delta in your final message.

## Files another agent owns right now — do not touch

- **`templates/components/Match/TipStats.html.twig`, `templates/components/Boost/Panel.html.twig`,
  `src/Enum/BoostType.php`, `templates/home.html.twig`,
  `templates/admin/competition/_monetization_choices.html.twig`, `.docs/DOMAIN.md`, `docs/stripe.md`**
  — owned by **item 12** (`12-naming-rozlozeni-tipu.md`), the boost/feature rename. You *render*
  `Match:TipStats` and `Boost:Panel`; you never edit them. If item 12 lands first, the boost label in
  your rendered output changes by itself — that is expected, and it is why your own strings must
  already say „Rozložení tipů ostatních".
- **`assets/controllers/*` and `.docs/features/team-picker.md`** — owned by the **B9** agent.
- **`.docs/ui-nav/PLAN.md`, `.docs/ui-nav/UI-MAP.md`** — orchestrator.

## Assumptions made

1. **Half A is neutralised on its RENDERED MARKUP, by one `inert()` macro**, not per component.
   Each half-A section is captured with `{% set %}` and piped through a single Twig `replace()`
   map: `href="` → `data-inert-link="` + `aria-disabled`, `<form>`/`</form>` → `<div>`/`</div>`,
   `action=`/`method=` → `data-inert-target`/`-verb`, `type="submit"` → `type="button"`,
   `data-controller=` → `data-inert-stimulus=`. Every replacement is deliberately named so it does
   **not** contain the string it replaces, which is what lets the test assert on the raw markup.
   Two cheaper options were rejected: a `<fieldset disabled>` wrapper disables controls but leaves
   ~53 real `<a href>`s live (and half A's links are the 404 risk, not its buttons), and pointing
   the FilterBar's form at `/_design` would both keep a submittable form on the page and break the
   existing assertion `substr_count($body, 'action="/_design"') === 1`. Result on the rendered
   page: exactly **one** `<form>`, **zero** `method="post"`, one `type="submit"` — all three the
   switcher's. Asserted in `testNothingOnThePageCanAct`.
2. **The „Přepínač soutěže" section deliberately stays OUTSIDE the macro** and remains the one live
   control on the page: its GET form targets `/_design` itself, so submitting is a harmless reload
   of the styleguide, and the existing test asserts that real `action="/_design"`.
3. **`Pill` has no `organizer` variant** — `organizer` is a **`Badge`** variant (`.badge-organizer`);
   `UI-MAP.md` §3 says otherwise and this item's table inherited the error. The gallery renders the
   nine Pill variants that actually exist (`done · tipped · success · soon · warn · accent ·
   neutral · locked · live`) and all seven Badge variants including `organizer`.
4. **Half B ended up with three sections, not five.** „Δ — změna pořadí" and „Přepínač soutěže"
   already carried a **„Hotovo"** pill because both features shipped; keeping them under a
   „Připravujeme" divider would have contradicted the acceptance criterion („each section carrying
   the right pill"), and both components are in half A's own table. So they moved up. The Δ section
   was also hand-written delta markup — it now renders the real `<twig:Leaderboard:Delta>` in both
   variants, which is what „no hand-copied component markup" asks for.
5. **`Leaderboard:Delta variant="chip"` has no „beze změny" chip** — by design it renders nothing
   for delta 0 and for „no history". The gallery feeds all five rows to both variants; the cell
   variant is where „0" and the neutral dot show up.
6. **An entry fee has no home in `PricingConfig`** (it is organizer-set per competition), so the
   card samples borrow real constants (`PREMIUM_PER_PLAYER`, `LOW_BALANCE_WARNING_THRESHOLD`)
   rather than invent a literal credit amount. `PricingConfig` is referenced **statically** like
   every other consumer in the codebase — it is a constants-only class, so injecting an instance
   would buy nothing.
7. **Half B's „Příspěvkové úrovně" now read their prices from the `pricing` Twig global.** They
   were hard-coded 10 / 50 / 100 / 200 Kč, which contradicted `PricingConfig` (10 / 10 / 20 / 40)
   — a styleguide showing three wrong prices next to a renamed boost is worse than no styleguide.
8. **`Boost:Panel` is NOT in the gallery.** It is a Live Component whose `$competition` prop is a
   `Competition` **entity** and which runs `GetBoostPanel` + `CompetitionMatchProvider` against the
   database; there is no way to render it without fixture data, which this page forbids. Same
   reason no other Live Component (`Guess:*`, `Notification:*`, `CreditBalance`, `CreateWizard`) is
   there. `Match:TipStats` covers the boost paywall a player actually meets.
9. **`compact=true` `TipStats` is only ever rendered inside a `MatchRow`**, never on its own —
   `UI-MAP.md` §3 says the strip „must not be placed anywhere else", and the gallery obeys its own
   documentation. The five states therefore show as „strip inside a card | full card" pairs, which
   also gives `MatchRow` a realistic 439 px column (B7 is container-relative).
10. **No `assets/styles/app.css` change at all.** Everything the gallery needed (section frames,
    swatch rows, the 380 px narrow column) is Tailwind utilities in the template, so the
    highest-risk file in the repo was left untouched this round.

### Verification recorded

- `composer cs:fix` clean · `composer quality` (phpstan lvl 8 + 497 unit tests) green ·
  `DesignStyleguideFlowTest` 7 tests / 76 assertions · `tests/Integration/Security` ·
  `tests/Integration/Portal` (242 tests) green.
- Geometry measured in headless Chrome at **1600 / 1440 / 1280 / 1024 / 768 / 430 px**: pairwise
  intersection over **568 painted leaves** (using `getClientRects()` fragments, not union boxes —
  a wrapped inline element's bounding box is a lie) → **0 overlaps, 0 px horizontal overflow** at
  every width. The only intersections found were the designed absolute decorations: the country
  flag on the team coin, the „+5" badge in the tip box corner, and the lock veil over the blurred
  skeleton. `.tip-row` painted at **894 / 654 / 439 / 380 / 348 px** — five container widths, not
  five viewport widths.
- Access unchanged: admin 200, verified non-admin 403, anonymous 302.
