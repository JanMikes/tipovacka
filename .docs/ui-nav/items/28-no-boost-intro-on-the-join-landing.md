# Item 28 — the boost intro does not greet you in the same breath as the join

**Status:** TODO — **queued**, not blocked. See „File ownership" below.
**Filed:** 2026-07-30, from the product owner.

## The instruction (verbatim)

> „There is modal showing with boosters on the competition page, this is perfect, but we must not
> show it immediately after registration.
>
> flow now: i click invitation link, register or sign in -> end up on the competition detail ->
> immediately see the modal
>
> expected: i click invitation link, register or sign in -> end up on the competition detail -> no
> modal displayed, then i go to dashboard and back to the competition detail, see the modal
>
> we might add some query parameter after registration which you will get so it does not show"

## The alternative the product owner considered and declined (2026-07-30)

They also offered: *„…OR this page `/souteze/{id}/moje-tipy` (all my guesses)"* — i.e. redirect the
freshly-joined player to the batch tipping page instead, so they never touch competition detail and
the modal cannot fire at all. Presented with both, **the product owner chose suppression** and
keeping competition detail as the landing.

Recorded so it is not re-litigated. The reason it was a real option: after joining, the useful next
action is entering tips. The reason it was not free: a soutěž with nothing tippable (all locked or
played) would have welcomed a brand-new member with an empty „Tipování uzavřeno" page, so it needed a
fallback to competition detail — which then needs this item's suppression anyway.

## What is there today (established from the code — do not re-derive)

**The decision is entirely in the controller**, so this item needs **no template change**:
`src/Controller/Portal/Competition/CompetitionDetailController.php:99–102`

```php
$showBoostIntro = $isMember
    && null === $membership->boostIntroSeenAt
    && CompetitionMonetization::Boosts === $competition->monetization
    && !$isFullyOver;
```

passed out as `'show_boost_intro'` (line ~178) and consumed at
`templates/portal/competition/detail.html.twig:438` as
`{% if show_boost_intro %}{{ include('portal/competition/_boost_intro_modal.html.twig') }}{% endif %}`.

**The stamp is written only on dismissal**, never on render: `Membership.boostIntroSeenAt`
(`src/Entity/Membership.php:33`) is set by `DismissBoostIntroController` (POST
`…/vylepseni/uvod/skryt`). **This is what makes the item safe** — suppressing a render cannot consume
the „first visit", so the modal simply appears on the next one.

**Exactly three places redirect to `competition_detail` as the direct result of a join:**

| Where | Which flow |
|---|---|
| `src/Service/Security/LoginSubscriber.php:99–103` | the pending-join intent consumed at login — **this is the reported flow** (invitation link → register or sign in → verify → land on the soutěž) |
| `src/Service/Invitation/InvitationAcceptanceService.php:120–123` | an already-authenticated visitor accepting an invitation |
| `src/Controller/Portal/Competition/JoinGlobalCompetitionController.php:93` (and `:57`, `:100`) | joining a public/global competition from `/souteze` |

Every other `redirectToRoute('competition_detail', …)` in the codebase (lock/unlock tips, leave,
delete, purchase boost, dismiss intro) is **not** a join and must not be touched.

## What to do

Take the product owner's suggested mechanism: **a query parameter on the join redirect**, which the
controller reads and treats as „not this time".

1. **Add `?pripojeno=1`** to the redirect in the three places above. Czech, like every other query
   parameter in this app (`?soutez=`, `?hledat=`, `?strana=`, `?filtr=`). It states the **fact**
   („you have just joined") rather than the UI effect, so a later feature can reuse it.
2. **Read it in `CompetitionDetailController`** and add it as one more `&&` to `$showBoostIntro`:
   the intro is skipped when the request carries it. Extend the existing comment block above that
   condition, which already documents the other four reasons the modal is withheld — keep its style.
3. **Do not stamp anything.** `boostIntroSeenAt` stays `null` on a suppressed visit. If you find
   yourself writing to the membership, you have taken a wrong turn.

That is the whole change. It is deliberately stateless: no session flag, no counter column, **no
migration**.

### Judgement calls — flag them, do not expand them

- **All three join paths get the parameter, not just the invitation one.** The owner described the
  invitation flow; „we just added you, so we will not upsell you in the same breath" is equally true
  of a PIN join and of joining a global competition. **Orchestrator's judgement, cheap to reverse** —
  it is one argument at each redirect. If you think the global-competition case should still show it
  (that user deliberately clicked „join" on a monetized competition), say so in your report rather
  than deciding differently.
- **A forged or shared `?pripojeno=1` costs nothing.** It suppresses one render of a promotional
  modal that reappears on the next visit. Do **not** add signing, a token or a session check for it —
  that would be more machinery than the thing it protects.
- **Do not make the parameter do anything else** — no welcome flash, no scroll target, no analytics.
  If a „vítej v soutěži" message would help, that is a separate decision for the owner.

## What must NOT change

- **The modal itself** (`templates/portal/competition/_boost_intro_modal.html.twig`, the
  `boost_intro` Stimulus controller, `DismissBoostIntroController`, the POST route) — untouched.
- **The four existing suppression reasons** stay exactly as they are: not a member; already
  dismissed; `monetization ≠ boosts` (Premium XOR boosts — on a premium competition the player
  cannot buy boosts at all); fully over (B6).
- **`Membership.boostIntroSeenAt` semantics** — „the player has dismissed this", nothing else. No new
  column, no migration.
- The other `competition_detail` redirects listed above.
- Czech in the UI, English in code, identifiers and comments. No „sázka" in any form.

## Acceptance criteria

1. Joining via an invitation link (register **and** sign-in variants) lands on competition detail
   with **no** boost modal.
2. Navigating away and returning to the same competition **shows** the modal — i.e. the suppressed
   visit did not consume it.
3. Dismissing it still stamps `boostIntroSeenAt`, and it never returns afterwards.
4. A member who arrives at competition detail by any ordinary route (nav, `/souteze`, a link) still
   sees it on their first visit, exactly as today.
5. The four pre-existing suppression reasons still hold.
6. `composer quality` clean; no migration produced.

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Walk the reported flow end to end in a browser**, not just in tests: open a shareable-link
  invitation logged out → register → verify the e-mail (Mailpit is in the compose stack) → land on
  the soutěž → **no modal** → go to `/nastenka` → back to the soutěž → **modal**. `DevFixtures` seeds
  a shareable link and a pending e-mail invitation on World B („Sousedský pohár") — see
  `.docs/FIXTURES.md`. Note World B must be a `boosts` competition for the modal to be in play at
  all; if it is not, find one that is and say which you used.
- **`composer quality` does not catch a Twig error** and cannot see a modal. Load the page.
- `docker compose exec web vendor/bin/phpunit tests/Integration/Portal/Competition`,
  `tests/Integration/Invitation` and `tests/Integration/Auth`. **Never run `phpunit tests/` whole —
  it OOMs (exit 137).** Strip ANSI before grepping.
- **Add coverage for criterion 2** — „the suppressed visit did not consume the first visit" is the
  one that would rot silently, and it is the whole point of the item.
  ⚠️ Put it in a **new** test file (e.g. `tests/Integration/Portal/Competition/BoostIntroJoinLandingTest.php`).
  **Do not add it to `BoostFlowTest`** — another agent is editing that file for the boost price and
  copy change, and two agents in one file is the documented way work gets swept here.
- Check the browser console.
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## File ownership (2026-07-30 round)

Yours: `CompetitionDetailController`, `LoginSubscriber`, `InvitationAcceptanceService`,
`JoinGlobalCompetitionController`, and the tests above.

**Item 26 also edits `CompetitionDetailController`** (it removes the `filter_teams` block and adds
the rules modal). Whichever of the two runs second rebases on the first — they touch different parts
of the file, but they must not run at the same time. The orchestrator sequences this; do not start if
item 26 is in flight.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. Push to `main`. Do not update the status board; report your sha.
