# 20 — Tabulka tipů: reachable by members, revealed per match

> **Status:** TODO
> **Depends on:** item 05 (which built `/zebricek` and its voter split), S10's monetization gates.
> **Owner decision date:** 2026-07-30

## Why (the requirement, in the product owner's terms)

`ROUND2.md` batch 5, verbatim, about `/zebricek/matice`:

> at se tam klidně člověk dostane, ale ať nevidí tipy, respektive mělo by tam být vidět jen to, co je už
> odehráno zbytek na CTA odemknout -> odehrané zápasy vidí každý vždy, budoucí a probíhající jen pokud
> mám booster

Today the page is refused wholesale unless the viewer passes `leaderboard_details`. The product owner
wants the opposite shape: **anyone who belongs may open it, and the page itself decides what is readable,
column by column.**

## The decision, settled

Asked what „odehraný" and „každý" mean (`ROUND2.md` decision 4):

- **„odehraný" = a final result has been entered** — i.e. the match is `Finished`. Not „past its
  deadline".
- **„každý" = any member** of the competition. **Not** anonymous visitors.
- Therefore **`Scheduled` and `Live` matches are both hidden** behind the unlock CTA.

⚠ **This is deliberately STRICTER than today for live matches.** `TipVisibilityGate` currently reveals a
match's tips when the viewer is entitled **OR** the deadline has passed — and a live match's deadline has
by definition passed, so its tips are readable now. After this item they are not, unless the viewer is
entitled. That is a real behaviour change, it is intended, and it must be written into `.docs/DOMAIN.md`.

## What changes

| Before | After |
|---|---|
| The page requires `leaderboard_details`; a member without it is refused entirely | The page requires **membership** (`leaderboard_view`-level for a member); it always renders |
| Tips are revealed when *entitled OR past deadline*, per match | Revealed when *entitled OR the match is `Finished`*, per match |
| A viewer with no entitlement sees nothing at all | Finished matches are readable; scheduled and live ones show the **unlock CTA** in place of the tips |

- **Do NOT widen `leaderboard_details`.** Item 05 recorded that widening the public board must never
  widen the tip-revealing sub-pages, and `/zebricek/clen/{userId}` still depends on that attribute. The
  per-match gate is the new mechanism; the voter attribute is not the thing to loosen. If the matrix
  needs a *different* attribute, add one rather than relaxing the existing one, and say so.
- **The unlock CTA reuses `Boost:Panel`** (`feature="others"`), exactly as item 10 did for the locked
  „Pořadí za zápas" — it already handles pricing from `PricingConfig`, affordability, the superset rule
  (owning „Konkrétní tipy kolegů" covers the distribution), the premium case, and **B6**'s „soutěž už
  skončila — vylepšení už nemá co odemknout". Do not re-implement any of that.
- **Managers and admins get no free pass** (`CompetitionEntitlements`, and the 2026-07-23 decision that
  removed their auto-entitlement). An organizer plays too; they buy like anyone else.
- **Anonymous visitors still cannot reach the page at all** — this widens it to members, not to the web.
  `tests/Integration/Security/AnonymousReachabilityTest` is the authority on that and must keep saying so.

## Where the logic lives

`src/Service/Competition/TipVisibilityGate.php` composes `CompetitionEntitlements` (deadline-independent)
with the userless deadline. **Read it before changing anything**, and work out whether this item wants a
second method on it or a changed one:

- Other surfaces depend on the current „entitled OR past deadline" rule — `/zapasy`, the Nástěnka,
  competition detail, match detail's „Pořadí za zápas". **Changing the shared method changes all of
  them**, which is not what was asked. The product owner spoke about the **matrix**.
- So the conservative reading is a **second, stricter question** („may this viewer read tips for this
  match *in the matrix*?") rather than a redefinition of the existing one. Decide, implement, and state
  which you did and why. If you do change the shared rule, you must justify why every other surface
  should also stop revealing live matches.

## Out of scope

- `/zebricek` itself (item 15 just stripped it), `/zebricek/clen/{userId}` and `/zebricek/shoda`.
- Match detail's „Pořadí za zápas" — same *kind* of gate, not this item.
- Any change to what a boost costs or unlocks.
- Competition detail (item 19 is on it right now).

## Acceptance criteria

- [ ] A **member with no entitlement** opens `/zebricek/matice?soutez=<id>` and gets **200**, sees the tips of `Finished` matches, and sees the unlock CTA instead of tips for `Scheduled` and `Live` matches.
- [ ] An **entitled** member (boost or premium toggle) sees every match's tips, as before.
- [ ] A **non-member** is refused, and an **anonymous visitor** is refused — unchanged.
- [ ] A **live** match's tips are hidden from an unentitled member **even though its deadline has passed** (this is the behaviour change — assert it explicitly).
- [ ] A manager/owner with no purchase gets **no** free pass.
- [ ] Every other surface that reveals tips (`/zapasy`, Nástěnka, competition detail, match detail) behaves **exactly as before** — assert at least one of them, so a shared-rule change cannot pass unnoticed.
- [ ] On a fully-over competition the CTA does not offer a purchase (B6) — the page still renders.
- [ ] `.docs/DOMAIN.md` carries the rule **and** a dated decision-log row naming the live-match tightening and its rationale.

## Verification

```bash
docker compose exec web composer cs:fix
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/Portal
docker compose exec web vendor/bin/phpunit tests/Integration/Security
docker compose exec web vendor/bin/phpunit tests/Integration/Service
docker compose exec web vendor/bin/phpunit tests/Integration/Query
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk; strip ANSI codes before grepping.

`composer quality` cannot see a template, so load the page in each of the five viewer states above and
confirm what is readable. **A leak here is a paid feature given away**, so verify by reading the rendered
HTML rather than by looking at the page: assert that a hidden match's actual scores appear **nowhere** in
the markup — not in an attribute, not in a `data-` payload, not in a blurred element that CSS merely
hides. Item 11's distribution paywall makes the same distinction: the real bars are `.dist-bar`/`.dist-fill`
and the decoration is `.dist-ghost-fill`, and „is it visible?" is asserted on the real ones.

After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Domain guard rails (binding)

- **Premium XOR boosts** — one `monetization` column.
- **Never „sázka"** or its verb forms; no payouts.
- **Prices from `Credits/PricingConfig`.**
- Czech in the UI, English in code and comments.
- No migration should be needed. If you think one is, stop and report.

## Git

**Commit with `git commit -o <path> …`** (`-o` = `--only`) — index-independent. Do not use `git add` +
`git commit`: an agent staged explicit paths, verified `git diff --cached --stat`, and still committed
another agent's file, because a sibling wrote to the index between the two commands. Never `git add -A` /
`git add .` / `git commit -a`. `git pull --rebase` if a push is rejected; never force-push. **An API
outage killed five agents mid-task today — commit early, and if you are cut off say what is unverified.**

## Files other agents own right now — do not touch

- `templates/portal/competition/detail.html.twig`, the `Competition` entity, the create wizard, the admin
  competition forms, `Membership`, **`assets/styles/app.css`** — item 19. **You should need no CSS**; if
  the CTA cannot be laid out with existing classes, report it rather than editing that file.
- `fixtures/DevFixtures.php`, `.docs/FIXTURES.md` — the fixtures agent (B11 + B23).
- `.docs/ui-nav/PLAN.md`, `UI-MAP.md`, `BUGS.md`, `ROUND2.md` — the orchestrator's. Report deltas as exact
  text. **You own `.docs/DOMAIN.md` this round** — write the rule and the decision-log row yourself.

## Assumptions made

_(Implementer appends here if the item did not answer a question it had to answer.)_
