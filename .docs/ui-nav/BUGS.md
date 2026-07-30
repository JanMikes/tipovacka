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
| B5 | Locked/past-deadline state is not reflected in the UI after locking | DONE | `9e81f31` |
| B6 | Boost can be bought for a competition that is already over | DONE | `436841f` |
| B7 | Match rows: overlapping elements, overflowing team names, dead „Zadat tip" | DONE | `9e81f31` |
| B8 | tom-select jumps on focus — search input wraps to a second line | DONE | `224a16f` |
| B9 | Team picker's create row shows English „Add …" | DONE | `d0b8bd4` |
| B10 | A player choosing „Volná volba Premium" is never told what the boosts are or cost | **BLOCKED** — needs a product decision | — |
| B11 | The premium fixture world renders no „Rozložení tipů" surface, so the premium paywall cannot be verified | TODO | — |
| B12 | The five pickers' „nothing found" rows are styled two different ways | TODO | — |

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

### What was already true (checked first, changed nothing)

- **The tip form** (`Guess:GuessSubmitForm`) already renders the reference locked panel —
  „Tipování uzavřeno — uzávěrka proběhla … / Netipováno." — on both the competition-scoped
  guess page and the generic match detail. It is the good case B4 screenshotted.
- **The competition detail header** already carries the Live / Ukončeno / **Tipy uzamčeny**
  pilulky (item 08) and, since B2, the „Uzamčení naplánováno na …" schedule line plus
  „Změnit uzamčení" / „Zrušit naplánování" — so the *action* already reflects reality.
- **Match rows** already render the locked variant (`state = locked` ⇒ grey left border,
  `Uzamčeno` / `Netipováno` pilulka) on competition detail, `/zapasy` and the Nástěnka.

So B5 was NOT about adding a lock pill; it was about the surfaces that kept *inviting* after
the lock, and about the places where an absent tip still read as a to-do.

### What changed

- **Detail header** — under the pilulka, the effective moment: „Tipy uzamčeny 15. 6. 2025 14:00".
  A locked competition may legitimately keep rows open (matches added AFTER the lock run to
  their own kickoff; the „Měnit tip" entitlement extends this viewer's window), so when the
  viewer still has something to tip the line adds „— u některých zápasů je tipování stále
  otevřené" instead of lying.
- **The „Tipněte si všechny zápasy najednou" banner** only invites while at least one row is
  open. With everything locked the same slot states the fact („Tipování uzavřeno · Tipy se
  uzamkly … · Měnit je už nejde — níže vidíte, co jste tipnul(a)").
- **`/moje-tipy` (batch) and `/spravovat-tipy` (on behalf)** said „Žádné nadcházející zápasy
  k tipování." — true-ish and useless. Both now branch on the lock and name it.
- **Match detail** — the „Nevyplněno" badge is a call to action; it now renders only while the
  tip can still be filled. Once locked the same absence reads **„Netipováno"** in the neutral
  locked skin, and the card drops the „you still owe a tip" accent ring. Distinct from B4's
  scope panel: locked competitions stay in „Vaše tipy", excluded ones stay in „Proč tu nejsou
  všechny vaše soutěže", so a user is never told both.
- **„Tipy členů" on the competition-scoped guess page** — the disabled inputs now carry one
  line saying why, and a member's missing tip reads „Netipováno" (locked) instead of
  „Nevyplněno" (soon) once the deadline has passed.
- Covered by `tests/Integration/Portal/Competition/LockedStateSurfacesTest.php` (open →
  invites and says „Nevyplněno"; locked → header moment, banner replaced, both batch pages
  explain, match detail says „Netipováno" with no accent ring and the locked form panel).

### Assumptions made

- **„Uzamčeno" stays the wording on the cross-competition rows** (`/zapasy`, Nástěnka). Those
  rows aggregate several soutěže, so the honest statement is „the window is shut"; the
  per-competition „Netipováno" belongs to competition detail and to the match-detail card,
  which know exactly one soutěž. (`DashboardFlowTest` / `MatchesFlowTest` pin that wording.)
- **A locked competition whose rows are still open keeps the batch banner.** There genuinely
  is something to tip; hiding the shortcut would be worse than the mixed message, which the
  header caveat and the per-row „Uzávěrka …" lines already resolve.
- **No lock state was added to the `/souteze` cards.** The item asks for the competition
  *header*; a lock pill on every card of a list would compete with the Live/Ukončeno state
  those cards already carry.
- **Nothing about a lock is notified.** Same reasoning as B2 — nothing listens to
  `CompetitionTipsLocked` today, and inventing a delivery is out of scope.

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

### Diagnosis: **not** stray children falling into an existing track — measured in a browser

The item's hypothesis (extra children landing in a fixed track) is wrong: the row has exactly
seven children and the grid had exactly seven tracks. Measured on `/souteze/{id}` at a 1440 px
viewport (`getComputedStyle` + `getBoundingClientRect`):

```
row width 717 px  →  cols: 88px 105.98px 33.67px 96px 33.68px 100px 132px
.tip-row-teams.home  x=245 w=34      .info inside it  x=195 w=42   ← spills 50 px LEFT
.tip-row-teams       x=403 w=34      .info inside it  x=445 w=53   ← spills right
```

The fixed tracks + gaps cost 500 px and the pilulka's `auto` track another ~106, leaving ~111 px
for **two** `minmax(0,1.2fr)` team tracks. The tracks collapsed correctly; what overflowed was
their content: `.tip-row-teams` has `min-width: 0`, but its flex child `.info` does not, so it
kept its min-content width (a `white-space: nowrap` name never shrinks) and painted over the
pilulka on the left and over the „můj tip" box on the right. Same mechanism, both defects 1 and 2.

**Why no media query can fix it.** The same component renders in columns of 632 – 1088 px, and
competition detail is *narrower at 1440 px (717) than at 1024 px (632→852 once the aside stacks)*.
Row width is not a function of viewport width, so the breakpoint must be the row's own.

### Implementation

- `.tip-row` is now a **wrapping flex row of four zones** — `[čas/kolo] [pilulka]
  [domácí–skóre–hosté] [můj tip · akce]`. The fixture zone (`.tip-row-match`, a nested
  `minmax(0,1fr) auto minmax(0,1fr)` grid) is the only flexing one; its `flex-basis: 360px` is
  the wrap hint, and the end zone (`.tip-row-end`) wraps as ONE piece so „můj tip" and the
  button never separate. Container-relative for free, no container queries, no per-page CSS.
- `min-width: 0` on `.tip-row-teams .info` (+ ellipsis on `.role`) is the actual overflow fix —
  long names now ellipsize inside their track.
- The old 7-track definition and the `@media (max-width: 900px)` collapse are left untouched
  (they are inert on a flex row); the B7 block re-asserts `justify-content: flex-end` for the
  home side inside that media query so the fixture reads „domácí – skóre – hosté" at every width.
- New prop **`tipUrl`** on `MatchRow`: when set, the state pilulka and the „můj tip" box become
  links to the guessing surface (`<a class="tip-row-pill-link">` / `<a class="my-tip">`), with
  their own `aria-label`, hover and `:focus-visible` ring. All three call sites pass it exactly
  when tipping is open, so „CHYBÍ TIP", „+ Zadat tip" and „Tipovat →" share one destination and
  a locked row has no dead-looking links at all. The row itself is NOT a link — no nesting.
- The two empty `<span></span>` placeholders are gone (they only added flex gaps).

Verified by driving Chrome headless over `/nastenka`, `/zapasy` and three competition details at
1600 / 1440 / 1280 / 1100 / 1024 / 900 / 768 / 600 / 430 / 360 px — a pairwise
bounding-box intersection check across every painted leaf of every row reports **zero overlaps**
and zero horizontal overflow, including a stress pass that rewrites every team name to
„FK Slovácko B (Uherské Hradiště)" / „Zbrojovka Brno B — Uherský Brod". Row states checked
visually: tip submitted, tip missing, live, locked, finished with points, playoff.
`tests/Integration/Portal/MatchRowTipLinksTest.php` pins the link targets on all three surfaces
and their absence once locked.

### Assumptions made

- **Ellipsis, not wrapping**, for long team names — it keeps every row the same height, and the
  existing `.tip-row-teams .name` rule already asked for it (it simply never got the chance).
- **The end zone wrapping to a second line is the intended narrow layout**, not a defect. At
  717 px the seven zones cannot share one line without squeezing the fixture below readability;
  two lines (fixture on top, „můj tip" + akce right-aligned below) is what the widths allow.
- **The pilulka is linked in every state, not only „Chybí tip"** (whenever the row is tippable):
  „Tip odeslán" → go change it is just as useful, and one rule is easier to keep honest than a
  per-label exception.
- **`MatchRow` was not added to `/_design`** — it needs real `Team`/`TeamView` objects, and the
  styleguide has no fixture plumbing; it stays covered by the three real surfaces.

---

## B8 — tom-select „jumps" on focus (all instances)

**Report (product owner, 2026-07-29).** *„When opening (focusing) the tomselect, it 'jumps' because
the cursor (focus) is on newline wrapped. Applies for all tomselect instances — fix."*

Screenshots: `.docs/ui-nav/screenshots/bug-b8-tomselect-rest.png` (at rest — one line, control ~76 px
tall) and `bug-b8-tomselect-focused.png` (focused — the caret sits on a **second line** below
„Lipina 26/27" and the control has grown taller, shoving the page down).

### The defect

In single-select mode the `.ts-control` holds two children: the selected `.item` and tom-select's
search `<input>`. On focus the input becomes visible and **wraps onto its own line** instead of
overlaying the item, so the control's height changes and everything below it shifts. The user sees
the whole page jump at the moment they click.

This is **not** specific to the competition switcher — it is the shared control, so every picker in
the app has it: `tom_select`, `team_picker`, `team_filter`, `scorer_picker`, `score_entry`.

### Prime suspect — verify before fixing

`assets/styles/app.css:3` imports `../vendor/tom-select/dist/css/tom-select.min.css`. A previous
agent (item 04) already established that **this stylesheet does not carry the rules our skin assumes**
— it found `app.css`'s `.ts-wrapper.single .ts-control::after` caret rules are inert for *every*
picker in the app, because the imported build never generates that pseudo-element. The single-select
input-positioning rules are very likely missing for the same reason.

So: **work out which tom-select stylesheet is actually imported and what it does and does not
provide.** The two candidate fixes are (a) import the complete stylesheet instead of the partial one
and reconcile the dark skin on top, or (b) keep the current import and add the missing single-select
layout rules to our own skin. **(b) is lower-risk** — swapping the vendor stylesheet would restyle
five pickers at once — but check the size of the gap before choosing; if a lot is missing, (a) may be
the honest answer. Record the reasoning either way.

The mechanical fix in single mode is that the search input must **overlay** the selected item rather
than participate in flex flow (the item hides or is covered while typing), and `.ts-control` must not
wrap. Do not fix it by hard-coding a height — the control holds one line of text at a font size the
design tokens own, and a fixed height would break the multi-select pickers (`team_filter`,
`scorer_picker`) which legitimately grow as chips are added.

### Also fold in: the inert caret

Since this item is already in that stylesheet, settle the caret finding item 04 deferred: today only
`.soutez-switcher` has a dropdown caret, because the generic rules never apply. Give **all** pickers
a consistent caret, or remove the dead rules — do not leave a third state.

### Requirements

1. Focusing any tom-select does **not** change the control's height and does **not** move anything on
   the page. Verify by measuring the control's and the page's geometry before and after focus, not by
   eye.
2. Multi-select pickers still grow correctly as chips are added and removed.
3. Typing filters as it does today; the placeholder, the „no results" text and the create option all
   still behave.
4. All five pickers verified: the competition switcher (`/nastenka`), the member picker
   (`/souteze/{id}/spravovat-tipy`), the team picker (match edit), the team filter (`…/zapasy-vyber`
   and the create wizard) and the scorer picker (match detail).
5. B3 must not regress — dropdowns are rendered into `<body>` via `dropdownParent: 'body'` and must
   still escape their card, and the dark skin must still win over vendor CSS on focus
   (`.ts-wrapper.single.input-active .ts-control` out-specifies our rule; see the B3 block).

### Definition of done

Per `PLAN.md`, plus a real browser at desktop and narrow widths. CSS discipline — new rules at the END
of the tom-select section under `/* --- B8: tom-select focus layout --- */`, never reorder existing
rules.

### What the imported vendor stylesheet actually provides (the diagnosis)

`assets/styles/app.css:3` imports **`tom-select/dist/css/tom-select.min.css`** — tom-select 2.6.0's
*core* build. Diffing it against `tom-select.default.min.css` (both ship in
`assets/vendor/tom-select/dist/css/`), the default build adds exactly one thing the skin cared
about, and it is **not** the layout:

| Assumed by our skin | In core? | In `.default`? |
|---|---|---|
| `.ts-wrapper.single .ts-control::after` caret box (`content`) | no | **yes** |
| `--ts-pr-caret: 2rem` (the gutter vendor's `padding-right: max(…) !important` reserves) | no (stays `0px`) | **yes** |
| anything that keeps the single-select search input out of the flex flow | **no** | **no** |

So item 04's finding was right and incomplete: the caret really is missing, but the reported jump is
*not* a missing-stylesheet problem — **neither** vendor build positions the single-select input. Core
`.ts-control` is `display: flex; flex-wrap: wrap`, and `.ts-control > input` carries
`flex: 1 1 auto; min-width: 7rem` on top of an `<input>`'s ~180 px intrinsic width. At rest tom-select
parks the input off-screen (`.input-hidden { left: -10000px }`); on focus that class goes away, the
input becomes a real flex child next to the selected `.item`, and in a 320 px control the pair no
longer fits on one line. Measured on `/nastenka`: control `44 px → 57.5 px`, 9 page nodes displaced.

**Fix (b) was chosen** — keep the core import, add the three missing declarations to our own skin.
Fix (a) was rejected on evidence, not on risk appetite alone: swapping in `tom-select.default.min.css`
would repaint all five pickers with a light Bootstrap-era theme (white gradients, blue chips, grey
borders) that the dark skin would then have to un-style declaration by declaration — **and it would
not fix the bug**, because the default build has no single-select input layout either.

### Verification (measured, not eyeballed)

Headless Chrome, before/after each control's own `getBoundingClientRect()` plus the rect of every
other element on the page (`body *`, excluding the `.ts-wrapper`/`.ts-dropdown` subtrees) at rest,
on focus and while typing. Pass = control `Δheight == 0` **and** zero displaced nodes.

| Picker | Page | 1440 px | 390 px |
|---|---|---|---|
| competition switcher | `/nastenka`, `/_design` | Δh 0, moved 0 | Δh 0, moved 0 |
| member picker | `…/spravovat-tipy` | Δh 0, moved 0 | Δh 0, moved 0 |
| team picker (×2) | `/zapasy/{id}/upravit` | Δh 0, moved 0 | Δh 0, moved 0 |
| team filter (multi) | `…/zapasy-vyber` + create wizard | Δh 0, moved 0 | — |
| scorer picker (multi) | match detail | Δh 0, moved 0 | — |
| score-entry player picker | `/zapasy/{id}/skore` | Δh 0, moved 0 | — |

Same harness on the unpatched CSS reproduces the bug (`Δh +13.5 px`, 21 displaced nodes on `/_design`),
so the measurement is sensitive to what it claims to measure. Also verified: multi pickers still grow
and shrink with chips (44 → 82 → 44 px over five chips; team filter 56 → 110 → 56); a synthetic
two-line `.item` keeps the control at its own taller height in all three states (no fixed height
anywhere); B3 intact (dropdown in `<body>`, `z-index: 300`, escapes the card, control background
stays `rgb(12,19,33)` on focus); the no-JS path still renders a visible native `<select>` plus its
„Zobrazit soutěž" submit (JS disabled in the browser, not simulated).

### Assumptions made

- **„All pickers get a caret" means all *single*-select pickers.** The multi-select ones (team
  filter, scorer picker) grow with their chips, so a caret pinned to `top: 50%` would float in the
  middle of a stack of chips pointing at nothing — tom-select's own default theme scopes the caret to
  `.single` for the same reason. The dead switcher-scoped rule is deleted; there is no third state.
- **The selected item stays visible on focus and hides only once the user types.** The brief allowed
  either („the item hides or is covered while typing"); keeping the label until there is something to
  read instead of it is the smaller behavioural change. The test is `:placeholder-shown` on the search
  input — tom-select sets `placeholder=""` exactly when a single-select has an item and the input is
  live, so it is a reliable „nothing typed yet" signal. A browser that refuses to match an empty
  placeholder degrades to hiding the item one step earlier, on focus; nothing breaks either way.
- **Not fixed here (out of scope):** the team picker's create row renders tom-select's stock English
  „Add <b>…</b>…" because `team_picker_controller.js` overrides `no_results` but not `option_create`
  — a Czech-copy bug that predates B8 and belongs to the picker, not to its layout.

---

## B9 — team picker's „create" row is in English

Found while fixing B8, out of that item's scope.

`assets/controllers/team_picker_controller.js` overrides tom-select's `no_results` renderer but not
`option_create`, so the „create a new team" row falls back to tom-select's stock English string —
„Add **…**…" — in an otherwise fully Czech UI.

**Fix:** override `option_create` with Czech copy („Přidat tým **…**" or similar, matching the
vocabulary in `.docs/features/team-picker.md`). While there, check the other TomSelect construction
sites for the same gap — `scorer_picker` also offers creation, and `tom_select`, `team_filter` and
`score_entry` may expose other un-translated stock renderers (`loading`, `optgroup_header`,
`no_results`).

Small and self-contained; good to bundle with any other copy pass.

### Survey of all five pickers (orchestrator, 2026-07-30) — the gap is exactly one renderer

Done so the implementer does not have to rediscover it. `grep -n "render\|create\|no_results\|option_create"`
over the five construction sites in `assets/controllers/`:

| Controller | `create` | `option_create` | `no_results` | Verdict |
|---|---|---|---|---|
| `team_picker` | `(name) => ({name})` | **MISSING** ⇒ stock English „Add **…**" | Czech | **the bug** |
| `scorer_picker` | `(input) => …` | Czech — „Přidat hráče „**X**"…" | Czech | fine |
| `score_entry` | `true` | Czech — „Přidat hráče **X**…" | Czech | fine |
| `tom_select` | `false` | n/a — no create row exists | Czech | fine |
| `team_filter` | `false` | n/a — no create row exists | Czech | fine |

So **only `team_picker_controller.js` is broken**, and only `option_create`. The two pickers that do
offer creation already have Czech copy, and the two that don't can never show the row. No other stock
renderer (`loading`, `optgroup_header`) is currently reachable with English text — but check that
claim rather than trusting it, and say what you found.

### Scope for this fix — product owner approved a copy sweep alongside it (2026-07-30)

1. **The actual bug:** give `team_picker` a Czech `option_create`. „Přidat tým" is the vocabulary
   `.docs/features/team-picker.md` uses; match it.
2. **Make the three create/no-results strings consistent.** The two existing Czech `option_create`
   strings already disagree with each other about quoting the typed value — „Přidat hráče „**X**"…"
   (`scorer_picker`) vs „Přidat hráče **X**…" (`score_entry`). Pick one form, apply it to all three
   (including the new team one), and keep the `escape(data.input)` call — **the input is
   user-supplied and must stay escaped in every one of them.**
3. **Check the `no_results` strings read consistently** too. They currently range from „Nic nenalezeno
   — napište jméno nového hráče" to „Žádný tým nenalezen" to „Napište název — vytvoří se nový tým".
   A picker that *can* create should invite creating; one that cannot should just say nothing was
   found. Align them on that rule; do not invent new wording beyond it.
4. **Update the feature docs** — `.docs/features/team-picker.md` and `.docs/features/scorer-picker.md`
   — if they quote any string you change.

**Out of scope:** no visual/CSS change, no behaviour change, no new picker option, and **nothing that
touches B3's `dropdownParent: 'body'` or B8's focus-layout rules** — those are load-bearing (see B3
and B8 above). This is copy only. Do not touch `assets/styles/app.css` at all.

**Verify in a real browser, not just by reading the diff.** Type a name that matches nothing in the
team picker (match edit, `/zapasy/{id}/upravit`) and in both player pickers (match detail scorer
picker; `/zapasy/{id}/skore`) and confirm the create row is Czech, that picking it still creates the
team/player, and that B3/B8 have not regressed (dropdown escapes its card; the control does not
change height on focus). `composer quality` cannot see any of this.

### What was actually wrong — the survey held, but it was one string short

Re-derived from the vendored bundle rather than trusted: `assets/vendor/tom-select/tom-select.index.js`
(2.6.0 `tom-select.complete`) defines exactly **eight** default renderers — `optgroup_header`, `option`,
`item`, `option_create`, `no_results`, `loading` (a bare spinner), `not_loading` (returns nothing) and
`dropdown` (an empty div). Only **two** of them carry English text: `option_create`
(`<div class="create">Add <strong>…</strong>&hellip;</div>`) and `no_results` („No results found").
All five construction sites override `no_results`; only `team_picker` was missing `option_create`.
`loading_more` / `no_more_results` exist only in the `virtual_scroll` plugin, which nothing uses
(the only plugin in the app is `remove_button`). So the table above was right.

Two things it did not have:

1. **A fourth `no_results` string.** The scope note lists three; `score_entry` had its own („Žádný
   hráč — napište jméno"), so the create-capable pickers had three different ways of saying it.
2. **`remove_button` renders an English tooltip.** The plugin hard-codes `title: "Remove"` on every
   chip's `×` (visible on hover, and the control's accessible name), in `scorer_picker` and
   `team_filter`. Not a renderer, so the grep in the survey could not have found it.

### What changed (copy only — no CSS, no behaviour, no new picker option)

| Site | `option_create` | `no_results` |
|---|---|---|
| `team_picker` | **new** — „Přidat tým „**X**"…" | „Nic nenalezeno — napište název nového týmu" |
| `scorer_picker` | „Přidat hráče „**X**"…" (unchanged — it set the pattern) | „Nic nenalezeno — napište jméno nového hráče" (unchanged) |
| `score_entry` | „Přidat hráče „**X**"…" (quotes added) | „Nic nenalezeno — napište jméno nového hráče" |
| `team_filter` | n/a (`create: false`) | „Žádný tým nenalezen" (unchanged) |
| `tom_select` | n/a (`create: false`) | „Nic nenalezeno" (unchanged default value) |

Plus `plugins: ['remove_button']` → `plugins: { remove_button: { title: 'Odebrat' } }` in the two
multi pickers. `escape(data.input)` is kept in every renderer — the typed value is user input.

**The Czech quotes are the house style**, not a coin flip: twelve places in `templates/` already wrap a
dynamic name in „…" (`Opravdu chcete soutěž „{{ name }}" smazat?`, `Koupit „{{ type.label }}"`), so the
quoted variant is the one that matches the rest of the UI. The create-capable pickers now share one
shape — „Nic nenalezeno — napište <jméno/název> nového <hráče/týmu>" — and the two that cannot create
just report that nothing was found, per the rule above.

`class="create"` is load-bearing and is kept: `render()` only stamps `data-selectable` on the returned
element, so the class comes from our template — and B3's dark-highlight rule is
`.ts-dropdown .create.active`. The `py-1` alongside it is inert (vendor's unlayered
`.ts-dropdown .create { padding: 5px 8px }` out-cascades a Tailwind utility layer); it is kept only so
the three renderers read identically. Measured create-row padding is `5px 8px` in all three.

### Verification (headless Chrome, measured — `composer quality` sees none of this)

Per picker: `getBoundingClientRect()` on the control plus the rect of every other node on the page
(`body *`, minus the `.ts-wrapper`/`.ts-dropdown` subtrees) at rest → on focus → while typing.

| Picker | Page | control Δh (focus / typing) | displaced nodes | create row |
|---|---|---|---|---|
| team picker ×2 | `/zapasy/{id}/upravit` @1440 & @390 | 44 → 44 → 44 px, Δ 0 | 0 | „Přidat tým „Zzzqqq Nováček"…" |
| score-entry player picker | `/zapasy/{id}/skore` @1440 | 44 → 44 → 44 px, Δ 0 | 0 | „Přidat hráče „Zzzqqq Střelec"…" |
| scorer picker (multi) | match detail @1440 | 44 → 44 → 44 px, Δ 0 | 0 | „Přidat hráče „Zzzqqq Střelec"…" |
| team filter (multi) | `…/zapasy-vyber` @1440 | 56 → 56 → 56 px, Δ 0 | 0 | none (`create: false`) — correct |

- **B3 intact**: on all four pages the open dropdown's parent is `<body>`, `z-index: 300`, zero
  clipping ancestors; the control's background stays `rgb(12,19,33)` on focus; the create row is
  `.create.active` with `rgba(70,153,208,0.18)`, i.e. dark-highlighted, not the vendor's white strip.
- **Creating still works end-to-end**: typed „Zzzqqq Nováček" in the team picker, clicked the Czech
  create row with a real mouse click, submitted the form → „Zápas byl uložen" and a `teams` row with
  `match_source_id` = the private source (local scope, per `TeamResolver`). Reverted afterwards.
- **`remove_button` still registers with the object config**: `ts.plugins.names === ['remove_button']`,
  the wrapper keeps `plugin-remove_button`, every chip renders `<a class="remove" title="Odebrat">×</a>`,
  and five chips were added (create row) and removed one by one with real mouse clicks.

### Assumptions made

- **The `remove_button` tooltip was translated too**, although the scope list does not name it. It is
  the same defect (stock English string in a Czech picker), it is copy-only, and it changes no layout;
  the object plugin form is tom-select's documented config shape and was verified to still load the
  plugin. Revert = two lines if the product owner disagrees.
- **`team_filter`'s and `tom_select`'s „nothing found" wording was left alone.** They cannot create, so
  they already satisfy the rule („just say nothing was found"); rewording them would be invention.
- **The `no-results` element's classes were not touched.** `team_picker` / `team_filter` style theirs
  with utilities while the others use the skin's `.no-results` — aligning them would change padding and
  color, i.e. a visual change, which this item excludes. Noted for a future pass.
- One page (`/souteze/{id}/zapasy-vyber`) refuses synthetic mouse input in headless Chrome — a click on
  empty background produces no event at document level either, so it is a harness quirk, not the app.
  Chip add/remove was therefore proven with real clicks on the scorer picker (same plugin, same config)
  and with a dispatched click on the team filter itself.

---

## B10 — a player choosing „Volná volba Premium" is never told what the boosts are or cost

Found by the item 12 agent while renaming the boost, 2026-07-30. **Not a regression — this is a
direct consequence of a decision the product owner already approved**, which is why it is `BLOCKED`
rather than `TODO`: it needs the product owner to say whether they still want it that way.

**What happens.** On step 4 of the create-competition wizard the organizer picks between „Férová
soutěž" (`CompetitionMonetization::Premium`) and „Volná volba Premium" (`…::Boosts`). Choosing the
latter means *every player decides for themselves whether to buy boosts*. But the private branch of
that step names **no boost and no price at all** — `CreateWizard::boostPrices` is rendered **only**
in the admin/global branch. So the organizer commits their whole competition to the per-player
funding model without being shown what the players will be asked to buy or for how much.

**Why it is like that.** `CREATE-WIZARD.md` W4, „Assumptions made" #3: the replacement copy the
product owner supplied („Pozvete nás na pivo?") *was* the whole of step 4 and mentions no prices, so
the old credit-balance strip, the per-boost price list and the „Teď se nic nestrhává…" footnote were
dropped from the private branch and kept only in the global one. That was the correct reading of the
instruction. The side effect only becomes visible now that someone looked at the two branches side by
side.

**The decision needed.** Either:
- (a) leave it — the organizer is choosing a *policy*, not making a purchase, and the prices are one
  click away on `/cenik`; or
- (b) add a compact, price-carrying line to the „Volná volba Premium" card (amounts from
  `Credits/PricingConfig`, never literals — and now that the boost is „Rozložení tipů ostatních", the
  name interpolates cleanly, see item 12).

Do not guess between them. If (b), the copy must not reintroduce the strip W4 deliberately removed.

## B11 — the premium fixture world renders no „Rozložení tipů" surface

Found by the item 12 agent, 2026-07-30, while trying to eyeball the premium paywall.

`Prémiová firemní liga` (the `AppFixtures` premium-monetized competition) produces **zero**
„Rozložení tipů" strips — no match in its scope yields one. So the one fixture world that exists to
demonstrate premium cannot demonstrate the feature premium unlocks. Verifying the premium paywall by
hand currently requires flipping World B's premium toggles off, looking, and flipping them back — the
item 12 agent did exactly that, and restored them (verified back to `t/t`).

**Fix:** give the premium world matches that produce a distribution (members with tips on an
in-scope, pre-deadline match), so the locked *and* unlocked premium states are both reachable from a
plain `composer db:reset`. `.docs/FIXTURES.md` documents which world demonstrates what and must be
updated in the same commit. Note the constraint recorded in item 03's assumptions: this belongs in
`DevFixtures` (group `dev`) unless the integration suite genuinely needs it — `AppFixtures` is the
shared *test* baseline and many tests assert exact counts over whole tables.

## B12 — the pickers' „nothing found" rows are styled two different ways

Flagged by the B9 agent as explicitly out of its (copy-only) scope, 2026-07-30.

`team_picker` and `team_filter` style their `no_results` element with Tailwind utilities
(`px-3 py-2 text-sm text-white/40`); `tom_select`, `scorer_picker` and `score_entry` use the dark
skin's `.no-results` class. The two render with different padding and colour, so the same „nothing
found" state looks like two different components depending on which picker you opened.

**Fix:** pick one — almost certainly the skin's `.no-results`, since that is the shared vocabulary —
and align all five. This *is* a visual change, so it needs the measured verification the other picker
items used (B8's before/after geometry harness) and must not regress B3 (`dropdownParent: 'body'`,
`z-index: 300`, the `.create.active` dark highlight) or B8 (zero height change on focus).

Small, but touches `assets/styles/app.css` — the highest-risk file in the repo — so it needs sole
ownership of that file for its round.
