# Bug / hardening backlog (UI-nav stream)

Reported by the product owner during the 2026-07-29 session, alongside the page-restructure items.
These are **independent of the page redesign** — they can be fixed in any order and mostly do not
touch the shared surfaces listed in `PLAN.md`.

Legend: `TODO` · `IN PROGRESS` · `DONE` · `BLOCKED`

| # | Title | Status | Commit |
|---|-------|--------|--------|
| B1 | Unverified e-mail account can still use the app | DONE | `7b3f010` |
| B2 | „Uzamknout tipy" — allow locking now **or** at a chosen time | DONE | `4e5f482` |
| B3 | tom-select dropdown clipped on „Správa tipů členů" | DONE | `6b1ee75` |
| B4 | Match detail omits a competition the user is a member of | DONE | `09770f4` |
| B5 | Locked/past-deadline state is not reflected in the UI after locking | TODO | — |
| B6 | Boost can be bought for a competition that is already over | DONE | `436841f` |
| B7 | Match rows: overlapping elements, overflowing team names, dead „Zadat tip" | TODO | — |

---

## B1 — Unverified e-mail account can still use the app

**Report.** „When my account is not confirmed I am still able to go through and operate — when
logged in and not confirmed the email it is weird — harden it."

Today a user can register, land in the portal without clicking the verification link, and use the
product. `app_verify_email_pending` (`/overeni-ceka`) exists but nothing forces the user there.

**Expected.** A logged-in but unverified user is confined to a verification airlock:
- Allowed: `/overeni-ceka`, resend-verification, `/overit-email`, `/odhlaseni`, account deletion,
  and the public/marketing pages.
- Everything else under the portal firewall redirects to `/overeni-ceka` with an explanatory message.
- The airlock page states the address the mail went to and offers „Poslat znovu".

**Implementation note.** Prefer one central guard (a kernel `RequestEvent` subscriber, or a Security
voter/`AccessListener`) over sprinkling checks in controllers — the `User` entity already carries the
verified flag. Be careful not to lock the user out of logout or of the verification links themselves,
and not to break the invitation-acceptance landing pages.

**Also check the write side:** the guard must cover POST actions (joining a competition, submitting a
tip, buying credits), not only GET pages.

### What was actually wrong (root cause)

`App\Service\Security\RequireVerifiedEmailSubscriber` already existed — it just never ran. It
subscribed to `kernel.request` at **priority 8, the same priority as the security firewall
listener**, and was registered first, so `Security::getUser()` read an empty token storage on
every real request and the guard silently no-opped. (It appeared to work in a `WebTestCase`
because `KernelBrowser::loginUser()` primes the token storage directly — only for the *first*
request after login, which is why nobody caught it.) Fixed by moving it to priority 7, strictly
below the firewall.

On top of that its deny-list of gated path prefixes (`/nastenka`, `/portal`, `/pripojit`,
`/admin`) missed `/zapasy` and — the important one — `/_components/…`, the single route every
Live Component shares, through which tips, the create-competition wizard and notification
preferences are written.

### Implementation

- The guard is now an **allow-list**: anything that is not explicitly public, part of the
  verification/escape flow, or an allow-listed `Auth:*` Live Component bounces to
  `/overeni-ceka` with a Czech warning flash. Method-agnostic, so POST is covered by
  construction. A future portal page is gated the day it exists; the failure mode of the
  allow-list is a public page being over-gated, which is annoying rather than unsafe.
- For a live-component request the UX bundle rewrites the redirect into `204` +
  `X-Live-Redirect: 1` + `Location`, i.e. the JS client navigates to the airlock.
- The airlock page now names the address the mail went to, says the rest of the app stays
  locked, offers „Poslat znovu" and „Odhlásit se".
- Covered by `tests/Integration/Auth/UnverifiedEmailAirlockTest.php`.

### Assumptions made

- **Invitation links.** Kept the behaviour `InvitationAcceptanceService::handleAuthenticated`
  already implements, and allow-listed both landing routes so the guard cannot pre-empt it:
  an **e-mail** invitation addressed to the account's own mailbox proves ownership, so it is
  accepted *and* verifies the account (no bounce); a **shareable link** proves nothing, so the
  landing page stores the join intent and sends the user to the airlock itself —
  `LoginSubscriber` completes the join after verification.
- **Account deletion** (`/ucet/smazat`) stays reachable while unverified, as the item
  asks — someone who cannot receive the mail must still be able to remove the account.
- **Password reset** routes stay reachable: an unverified account must remain recoverable.
- **Only the four `Auth:*` Live Components** stay reachable (registration, invitation,
  request-reset, reset). Every other component is a portal write surface and is gated.
- **`/` and `/prihlaseni` are unreachable while logged in + unverified** — not because of the
  guard (both are allow-listed) but because their controllers redirect a logged-in user to
  `/nastenka`, which then bounces to the airlock. Left as is: the destination is correct and
  changing it would touch surfaces owned by other items.
- **Admin is gated too.** An unverified `ROLE_ADMIN` gets no free pass.

---

## B2 — „Uzamknout tipy": now, or at a chosen time

**Report.** With screenshot of the „Uzamknout tipy" confirm modal: „I want to be able optionally to
check date in the future so choose 'Now' or 'At a time' (datetime picker)."

Today `…/uzamknout-tipy` is an immediate POST behind a `confirm` modal
(`.docs/features/confirm-modal.md`), copy: „Tipy všech členů se okamžitě uzamknou, jako by soutěž
právě odstartovala. Odemknout je půjde jen do výkopu prvního zápasu."

**Expected.** The modal offers two mutually exclusive choices:
- **Ihned** (default) — current behaviour.
- **V určený čas** — reveals a datetime picker; the lock takes effect at that moment.

The chosen time must be in the future and is stored/displayed in Europe/Prague while persisted as UTC
(see the project's datetime convention). The competition detail page must show the scheduled lock
time and let the organizer cancel or change it before it fires.

**Design decision needed from the product owner** (do not guess): a scheduled lock has to be *applied*
by something. Options: (a) treat it purely as a deadline — the effective tip deadline resolver already
does extend-only `max()` maths, so a stored „locked from" timestamp needs no job; (b) an actual job
that flips the flag. **(a) is strongly preferred** — it reuses `EffectiveTipDeadlineResolver` and
needs no new cron. Confirm before building (b).

Reuse the existing `datepicker` Stimulus controller rather than introducing a new picker.

### Decision (settled by the product owner, 2026-07-29): **(a)**

Store a „locked from" timestamp and let the existing deadline maths reach it. **No cron, no
scheduler entry, no flag to flip** — same user-visible behaviour, no new moving part that can
fall over at 03:00.

### As built

**The timestamp IS the state.** `Competition.tipsLockedAt` is what
`EffectiveTipDeadlineResolver::lockMomentFor()` already reads, so a value in the future simply
makes every match deadline `min(lockAt, kickoff)` the second it is stored; the lock „fires" by
time passing. **No schema change, no migration** — a past/now value means locked (as before), a
future value means scheduled. `LockCompetitionTipsCommand` grew one optional `?lockAt`
(null = „Ihned"), and the resolver itself was not touched beyond a docblock.

**Domain rules** (`Competition::scheduleTipsLock`, exception
`App\Exception\CompetitionTipsLockTimeInvalid`, HTTP 409, Czech messages surfaced as flashes):
- the moment must be **in the future** — locking now is `lockTips()`;
- it must be **before the competition start** (first included kickoff). A later moment would push
  the lock BEYOND the automatic one and reopen tips the start had already closed — the one thing
  the „a lock moment is only ever reached once" invariant forbids;
- **cannot schedule on an already locked competition** (unlock first);
- re-scheduling a pending lock just moves it; **„Ihned" overrides a pending schedule**;
- a pending schedule records **no `CompetitionTipsLocked` event** — nothing has locked yet.

**Unlock interaction.** A pending schedule is cleared by the *same* `…/odemknout-tipy` action
(„Zrušit naplánování"), which the existing rule already allows: unlocking is possible until the
first kickoff, and a schedule is by construction earlier than that, so the cancel button can
never outlive its window. Once the moment passes the competition is an ordinary manually locked
one — „Odemknout tipy" until the first match kicks off, then nothing.

**UI.** The `confirm` controller gained an optional **`fields` target** (documented in
`.docs/features/confirm-modal.md`): an element inside the form that is moved into the dialog and
revealed there, with `form="<form id>"` stamped on its named controls so it still submits. The
lock modal uses it for the „Ihned" / „V určený čas" radio pair; the picker is revealed by CSS
`:has()` alone. Without JS the form posts „Ihned" — exactly the pre-B2 behaviour. The detail page
shows the schedule as a `soon` Pill + a header line, and offers „Změnit uzamčení" (modal
prefilled) and „Zrušit naplánování".

**Datepicker.** The existing `datepicker` controller gained a `inline` value (flatpickr `inline`)
for pickers inside a modal `<dialog>`; the flatpickr `min`/`max` bounds mirror the domain rule
(now … first kickoff) in Prague time. Two traps found by exercising it in a real browser, both
now documented in the feature doc and the controller:
- flatpickr's `static: true` **hangs the page** — it re-parents the controller's own element, so
  Stimulus unmatch→matches forever;
- a flatpickr inside a `<form>` **silently blocks submission** — its internal hour/minute number
  inputs (`step="5"`) are form controls and fail constraint validation, so the form needs
  `novalidate`.

Covered by `CompetitionEntityTest` (6 cases), `LockCompetitionTipsHandlerTest`
(„open until the moment, closed one hour later, with nothing running in between") and
`CompetitionLockTipsFlowTest` (modal shape, Prague→UTC persistence, fires by time passing,
change/cancel, and a data-provider of four server-side refusals).

### Assumptions made

- **A schedule later than the competition start is refused, not clamped.** Silently moving the
  organizer's chosen moment would be worse than an explicit „musí nastat dřív, než soutěž začne".
- **Validation is against the first kickoff as it stands at write time.** If a match with an
  EARLIER kickoff joins the source afterwards, a schedule can end up after the (new) competition
  start, and the matches that kick off later would stay tippable until it. Re-validating on every
  match change is a match-lifecycle concern (the `pinTipsLockMoment` neighbourhood), deliberately
  out of B2's scope; the window is small and the failure mode is „tips close later than they
  could have", never „closed tips reopen".
- **No notification when a lock is scheduled or fires.** Nothing listens to `CompetitionTipsLocked`
  today, and inventing a delivery for the scheduled case would need exactly the timer this item
  set out to avoid.

---

## B3 — tom-select dropdown clipped on „Správa tipů členů"

**Report.** `/souteze/{id}/spravovat-tipy` — „the input is broken, probably overflow or
z-index issue, the options hidden."

The „TIPUJÍCÍ" member picker renders as a white input whose dropdown is cut off at the bottom edge of
its card: the option list is clipped instead of overlaying the content below.

**Cause to verify.** Almost certainly the wrapping card has `overflow-hidden` (or a stacking context
from a transform/backdrop-filter) so the absolutely-positioned tom-select dropdown is cropped; the
white input also suggests the dark tom-select skin is not being applied on this page at all.

**Expected.** The picker matches every other tom-select in the app (dark skin, see
`.docs/features/team-picker.md` / `scorer-picker.md`), and its dropdown overlays the content beneath
it. Fix at the root — if other pages nest a tom-select inside a card they must not regress.

Per `PLAN.md` CSS discipline: reuse existing select/dropdown classes in `assets/styles/app.css`, add
nothing that redefines them.

### Diagnosis (confirmed in a headless browser, both symptoms reproduced)

Two unrelated causes, both in the *shared* tom-select layer — nothing page-specific:

1. **White input = vendor CSS out-specifying the dark skin.** On focus tom-select stamps
   `input-active` on the wrapper, and vendor's `.ts-wrapper.single.input-active .ts-control`
   (0,4,0) beats the skin's `.ts-wrapper.single .ts-control` (0,3,0) → `background:#fff`.
   Measured: control background flipped `rgb(12,19,33)` → `rgb(255,255,255)` on focus. The
   skin *was* loading; the controller *did* initialise. (A third, adjacent instance of the
   same specificity bug: the „Přidat hráče …" row is `.create.active`, matched only by
   vendor's light `.ts-dropdown .active` — a white strip inside the dark dropdown.)
2. **Clipping = `.card-glass { overflow: hidden }`.** The dropdown is absolutely positioned
   inside `.ts-wrapper`, so any clipping ancestor crops it; `backdrop-filter` additionally
   makes the card its own stacking context, so raising z-index alone would not have helped.

### Fix

- `assets/styles/app.css`, new `/* --- B3: tom-select in cards --- */` block at the end of the
  tom-select section: re-assert the dark control at the vendor's specificity, dark-highlight
  `.create.active`, and give body-level dropdowns `z-index: 300`.
- `dropdownParent: 'body'` on **every** TomSelect construction site (`tom_select`,
  `team_picker`, `team_filter`, `scorer_picker`, `score_entry`) — the dropdown leaves the card
  entirely, so no ancestor's overflow or stacking context can ever crop it again.

### Assumptions made

- **Scope widened from the reported page to all five tom-select controllers.** The item asks
  for a root fix; `team-filter` on `/souteze/{id}/zapasy-vyber` and in the create wizard
  sits in the same `.card-glass` and had the identical bug, so a shared-controller-only fix
  would have left known instances broken.
- **The `.create.active` white row was fixed too** although the report only mentions the input.
  It is the same specificity bug, and un-clipping the dropdown made it fully visible.
- **`z-index: 300`** puts body-level dropdowns above `.modal-backdrop` (200). No tom-select
  lives in a modal today; the choice is so that one could, since a dropdown belongs on top of
  the control that owns it.
- Verified by driving Chrome headless: member picker, team-picker (match edit), score-entry
  player picker, team-filter inside the wizard's `data-live-ignore` island (including payload
  sync after picking) and the scorer picker on match detail — all render the dark skin and
  drop over the content below, with zero clipping ancestors.

---

## B4 — Match detail omits a competition the user is a member of

**Report.** After locking tips: on `/zapasy/019fa008-7232-7284-aa49-b7e50684c0bc` the „Vaše
tipy" section shows only **one** competition, while another match of the same source shows **two**.

Observed (screenshots):
- *Frýdek-Místek vs Hranice* → „Vaše tipy **2**": „Fotbal F-M (Lipina)" (locked — „Tipování uzavřeno
  — uzávěrka proběhla 27. 7. 2026 00:11. Netipováno.") **and** „3. MSFL sezóna 26/27" (open).
- *Hlubina vs Zbrojovka Brno B* → „Vaše tipy **1**": only „3. MSFL sezóna 26/27".

**Investigate before fixing — two very different explanations:**
1. **Correct behaviour, bad communication.** „Fotbal F-M (Lipina)" may legitimately not include the
   Hlubina match (competition match scope `teams` or `subset` — see `CompetitionMatchProvider`). Then
   the count is right and the fix is only to explain it.
2. **A real leak.** The per-match competition list may be dropping locked/pastdeadline competitions
   in one code path but not the other. Compare how the „Vaše tipy" list is built against
   `CompetitionMatchProvider::applyRowLevelCompetitionMatchFilter` — the cross-competition variant is
   the documented place where surfaces „leak/drop rows" when a mode is not taught to both filters.

Determine which it is, state it in the commit message, then fix accordingly. If it is (1), the match
detail should still make clear *why* a competition the user belongs to is absent.

### Diagnosis: **(1) — correct behaviour, badly communicated.** No leak, no drop.

Reproduced deliberately on `DevFixtures`, logged in as `user@tipovacka.test`, who is in both
„Tipovačka MS 2026" (mode `all`) and „Fandíme Česku" (mode `teams`, scoped to Česko +
Slovensko) over the **same** source „Mistrovství světa 2026":

- `/zapasy/…fa010` (Česko vs Brazílie) → „Vaše tipy **2**"
- `/zapasy/…fa011` (Argentina vs Mexiko) → „Vaše tipy **1**"

— the exact reported shape. The count is right: the teams filter genuinely excludes the second
fixture. The reporter's „Fotbal F-M (Lipina)" is the same case: a competition whose match scope
covers Frýdek-Místek but not Hlubina vs Zbrojovka. Locking is a red herring — the list is built
from `CompetitionMatchProvider::includes()`, which knows nothing about deadlines or lock state
(which is why the locked competition rendered its explanatory panel on the *first* screenshot).

Audit of the two filters, since the item points at them: `includes()`,
`applyCompetitionMatchFilter` and `applyRowLevelCompetitionMatchFilter` agreed on every fixture
competition × match, so no live surface was leaking or dropping rows. They did, however, differ
in *shape*: the row-level variant OR-ed its three mode branches instead of guarding each by the
row's own `selectionMode`. Not reachable today (`selectionMode` is create-only — the form field
is added only under `with_source_selection`), but it means a leftover `CompetitionMatchSelection`
/ `CompetitionTeamFilter` row of an unused mode would widen a competition's scope on
cross-competition surfaces while the match detail kept it out — i.e. it would manufacture
exactly this bug for real. Hardened, since „both filters must agree" is the documented trap.

### Implementation

- `SportMatchDetailController` now also collects the memberships whose competition sits on
  **this match's source** but whose scope excludes the match, with a reason (`subset` / `teams` /
  `playoff` / catch-all) and, for `teams`, the filter teams via `teamViewsFor()`. Competitions
  over another source are still not listed — they are not surprising and would be noise.
- `templates/portal/sport_match/detail.html.twig` renders them under the „Vaše tipy" section:
  „Proč tu nejsou všechny vaše soutěže" (or „Tenhle zápas se ve vašich soutěžích netipuje" when
  no competition takes a tip here), one row per competition with the reason in one sentence and
  the filter teams spelled out as `TeamFlag` pills. No new CSS — `card-glass` / `bg-inset` and
  the team-pill markup are reused verbatim from the competition detail header.
- `CompetitionMatchProvider::applyRowLevelCompetitionMatchFilter` now guards each branch by the
  row's `selectionMode`, mirroring `applyCompetitionMatchFilter` / `includesIgnoringDeletion`.
- Tests: `tests/Integration/Portal/SportMatch/MatchDetailCompetitionScopeTest.php` (all three
  reasons + the „covered, so no explanation needed" and „nothing on this source at all" cases)
  and `tests/Integration/Service/CompetitionMatchScopeAgreementTest.php`, which pins all three
  membership implementations to each other for every mode and fails on the old un-guarded OR.

### Assumptions made

- **The count stays the count.** „Vaše tipy N" keeps counting only competitions that actually
  take a tip here; the excluded ones are explained *below* it rather than padded into it.
- **Only same-source competitions are explained.** A competition over a different `MatchSource`
  can never contain this match and listing it would be noise, so it is silently omitted — the
  conservative reading of „a user should never have to guess", scoped to the absence that is
  actually surprising.
- **The panel replaces nothing.** When every membership includes the match the page is byte-for-byte
  what it was, so items 05/08 and the other match-listing surfaces are untouched.
- **The `teams` reason lists the filter teams** (one extra query per Teams-mode competition of the
  viewer on this one source — typically zero or one, so no N+1); without them the sentence
  „zahrnuje jen zápasy vybraných týmů" would just move the guessing one step further.
- **`DevFixtures`' „Fandíme Česku" was used to reproduce and verify by hand**, but not in tests —
  `DevFixtures` is group `dev` only and is never loaded by `tests/bootstrap.php`. The automated
  coverage rebuilds the same shape from `AppFixtures` (`SUBSET_COMPETITION` plus a Teams-mode and
  a playoff-excluding competition created through `CreateCompetitionCommand`).

---

## B5 — Locked state is not reflected in the UI after locking

**Report.** „As a user I must understand that the competition guessing is already locked/past due.
Now after locking there is only a flash message but seems like no further feedback to the user and it
is hard to understand what is going on — state of some components should be updated accordingly."

**Expected.** Locking is a visible, persistent state, not a one-shot flash:
- The competition detail header shows a lock state (existing `Pill` variant `locked` is the natural
  fit) with the effective deadline.
- Match rows / tip forms for locked matches render their locked variant everywhere they appear
  (competition detail, `/zapasy`, match detail, Nástěnka) rather than an editable form.
- The „Uzamknout tipy" action itself flips to „Odemknout tipy" while unlocking is still possible
  (i.e. until the first match kicks off), and disappears afterwards.
- Where a tip was never filled, say so („Netipováno") instead of showing an empty editable form.

The second screenshot in B4 shows the *good* case (locked competition renders an explanatory locked
panel); make every surface behave like that.

---

## B6 — Boost can be bought for a finished competition

**Report.** „I should not be able to buy a booster when the competition is already fully over."

Buying a boost for a competition whose matches are all played buys nothing — the entitlement it
unlocks (seeing others' tips / distribution) no longer has any future value, and it burns credits.

**Expected.** `Boost:Panel` hides or disables the purchase CTA once the competition is over, with a
short explanation. The command handler must refuse too (do not rely on the UI alone) — a domain
exception in `src/Exception/` following the no-`Exception`-suffix convention, surfaced with the right
HTTP status via `#[WithHttpStatus]`.

**Define „fully over" precisely** and write it into `.docs/DOMAIN.md`: the natural reading is *every
match in the competition's scope has a final result*, which is not the same as „past the last
kickoff". Confirm with the product owner if ambiguous.

### As built (with item 08)

**Definition** (now in `.docs/DOMAIN.md` §Monetization + a dated decision-log row): a
competition is **fully over** when it includes **at least one** match and **none** of its
included matches is `Scheduled`, `Live` or `Postponed`. `Finished` carries a final result,
`Cancelled` never will — both count as settled; an **empty scope is not over**. Deliberately
not „past the last kickoff": a kicked-off match whose result has not been entered can still
move the standings. It is the same settled-ness test `competition_ended` uses.

**Implementation**
- `CompetitionMatchProvider::isFullyOver()` (+ `matchCount()`) — scope always comes from the
  one authority, so every selection mode answers consistently.
- `PurchaseBoostHandler` throws `App\Exception\CompetitionAlreadyOver`
  (`#[WithHttpStatus(409)]`, no `Exception` suffix) **before** any wallet movement;
  `PurchaseBoostController` surfaces it as a flash + return to the origin page, like its
  `BoostNotAvailable` sibling.
- `Boost:Panel` exposes `competitionIsOver` and, in **both** shapes (the „Tvoje vylepšení"
  sidebar and the inline paywall), replaces the price + CTA with
  „Soutěž už skončila — vylepšení už nemá co odemknout."
- Covered by `BoostFlowTest::testFinishedCompetitionOffersNoPurchaseCtaAndSaysWhy` and
  `::testBuyingInAFinishedCompetitionIsRefusedAndChargesNothing` (the second one grabs a valid
  CSRF token while the competition is still running, then settles every match — a stale page
  must not be able to burn credits).

---

## B7 — Match rows: overlapping elements, overflowing team names, dead „Zadat tip"

**Report (product owner, 2026-07-29, competition detail).** *„Competition detail there are overlay
things and the 'chybí tip' and 'zadat tip' should take me to the match guessing. The names of teams
are overflowing."*

Screenshot: `.docs/ui-nav/screenshots/bug-b7-matchrow-overlap.png`.

### What the screenshot shows

Three distinct defects in the match row, all visible on `/souteze/{id}`:

1. **Elements are painted on top of each other.** The state pill („TIP ODESLÁN" green / „CHYBÍ TIP"
   amber) is rendered **over the home team's name** („Hlubina", „Frýdek-Místek", „Uherský Brod",
   „Vrchovina", „Hodonín" are all partly covered). The tip box („MŮJ TIP 1 : 1" / the dashed
   „TIPNOUT + Zadat tip") is rendered **over the away team's name and its „HOSTÉ" label**
   („Zbrojovka Brno B", „Hranice", „Vítkovice", „Blansko", „Slovácko B"). Both overlays also sit on
   top of the team monogram coins.
2. **Team names overflow** their column instead of wrapping or truncating.
3. **„CHYBÍ TIP" and „+ Zadat tip" are not links.** They must take the user to the match guessing
   surface — the same destination as the row's „Tipovat →" button.

### Where to look

`.tip-row` and its children live in `assets/styles/app.css` (§ „Horizontal match row", ~l. 624-668):
`.tip-row-when` / `.tip-row-teams` (+ `.home`, `.name`, `.role`) / `.tip-row-score` / `.my-tip`
(+ `.set`, `.empty`) / `.my-tip-lbl` / `.my-tip-val` / `.tip-row-actions`, plus a stacking media
query at ~l. 659-665. The component is `templates/components/Match/MatchRow.html.twig`.

**Likely cause — verify, do not assume.** Item 08 added an optional `tipPrompt` prop to `MatchRow`
to render the previously-unused `.my-tip.empty` style, and the competition detail page also renders
a state pill per row. The overlap pattern (two extra elements landing exactly on the teams column)
is what happens when new children are added to a `grid`/`flex` row that has **fixed column
definitions** — the extras fall into an existing track instead of getting their own. Read the rule
before changing it; the fix is almost certainly the row's track definition, not a `position` hack.

### Requirements

- No element overlaps another at any viewport width. Check the narrow breakpoint too — the stacking
  media query at the bottom of that section is where a naive fix usually breaks.
- Long team names must be handled deliberately (wrap or ellipsis), not allowed to overflow. Test
  with the longest real names in `DevFixtures` („Zbrojovka Brno B", „Uherský Brod", „Slovácko B").
- „CHYBÍ TIP" and „+ Zadat tip" both navigate to the match guessing surface, same target as the
  row's „Tipovat →" action. Keep them keyboard-reachable and give them an accessible name — if the
  whole row becomes clickable, do not nest interactive elements inside a link.
- **`MatchRow` is shared.** It is rendered on competition detail, `/zapasy`, the Nástěnka and match
  detail. Fix it once in the component/CSS and verify **every** surface — a per-page patch is not
  acceptable.

### Definition of done

Per `PLAN.md`, plus: render every surface that uses `MatchRow` at desktop **and** narrow widths, in
each row state (tip submitted, tip missing, locked, finished with points). CSS discipline — reuse
first, new rules at the END of the „Horizontal match row" section under a
`/* --- B7: match row layout --- */` comment, never reorder existing rules.
