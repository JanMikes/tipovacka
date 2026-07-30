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
| B10 | A player choosing „Volná volba Premium" is never told what the boosts are or cost | **WONTFIX** — by design (product owner, 2026-07-30) | — |
| B11 | The premium fixture world renders no „Rozložení tipů" surface, so the premium paywall cannot be verified | DONE | `6ee485a` |
| B12 | The five pickers' „nothing found" rows are styled two different ways | DONE | `8aced30` — JS only, no CSS needed |
| B13 | PIN and shareable-link boxes overflow their card on mobile | DONE | `77023fc` |
| B14 | The verification airlock does not confine the user | DONE | `26462b4` — was **cosmetic**, the guard held |
| B15 | An invite-link sign-up loses the competition it was invited to | DONE | `26462b4` — never robust, not regressed |
| B16 | „Uzamknout tipy" shows no date option, though B2 shipped one | DONE | `05f0f67` — feature was reachable; a latent bug with the same symptom was fixed |
| B17 | Browser offers to save the password before it is confirmed | DONE | `26462b4` |
| B18 | Notification dropdown overflows the viewport on mobile | DONE | `09c9d21` — right-alignment to the trigger, **not** B3's clipping |
| B20 | Nav overflows the viewport at 320 px (every page, both variants) | DONE | `09c9d21` — three-tier degradation; 0 overflow 320→1920 px |
| B22 | `/kredity` renders a 523 px table at 320 px | DONE | `48ed427` — the ledger now stacks below 640 px; the report's premise was wrong (see below) |
| B23 | After `db:reset` no competition can reach the „Uzamknout tipy" modal | DONE | `6ee485a` — was 0 of 7 competitions, now 1 (World E) |
| B24 | flatpickr's year renders near-black on the dark lock modal | DONE | `049989a` (+ `98a0ce6`) — B3's mechanism, one vendor over |
| B25 | With JavaScript off, „Zobrazit další" hides matches unreachably | DONE | `8d63ee7` — `reveal` now collapses instead of hiding |
| B21 | Hero `<h1>` nbsp starves the demo card; team names vanish at 1024 px | DONE | `6bcd689` — one glue was 740 px, exactly the column width |
| B26 | Homepage hero: the „1. MÍSTO" floating chip sits on the away team name | DONE | `db7311b` — moved to the bottom-right padding band |
| B28 | The hero's OTHER two floating chips also sit on content | DONE | `af8eb32` — both moved to horizontal edge bands |
| B29 | Homepage demo card: team names collapse to ~15 px at 320 px | DONE | `3f61e77` - three shapes; also fixed the second mock on the same page |
| B30 | The lock dialog calendar overflows its own panel at 320 px | DONE | `e0e4c4a` - four vendor widths made relative; exposed and fixed a second header defect |
| B31 | After `db:reset` no competition can reach the scorer picker | DONE | `5e524ff` - World B extended; third gap of this shape after B11 and B23 |
| B27 | Match detail: the two paywall cards do not match; whole card clickable with confirm | DONE | `397c5bd` — the confirm **was** firing all along |
| B19 | Stray border with no padding around the tip form on match detail | DONE | `b6dacf2` |

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

- **`min-width: 0` is necessary but NOT always sufficient** (added 2026-07-30 from B13). If the
  offending child has its own *intrinsic* width — a UA-sized `<input>`, an image, a replaced element —
  `min-width: 0` stops it overflowing the flex line but its min-content contribution still sizes the
  track. B13 needed an explicit `width: 0` (with `flex: 1` growing it back) to zero that contribution.
  Measure the track, not just the child.
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

**Closed WONTFIX by the product owner, 2026-07-30: „B10 - not needed to show anything".**

So option (a) below stands: the organizer picking a monetization model on step 4 of the wizard is choosing
a **policy**, not making a purchase, and does not need the price list in front of them to make that
choice. W4's copy — which deliberately dropped the price strip from the private branch — was right as
written, and nothing is added back.

Note this is **not** the same gap as „the player never learns the prices": item 19's first-visit credits
modal shows the boost prices (from `PricingConfig`) to every member the first time they open a `boosts`
competition, which is the moment the information is actually actionable. The organizer's wizard step and
the player's first visit are different audiences at different moments, and only the second one needed it.

The original report is kept below for the record.

---

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

---

## B13 — PIN and shareable-link boxes overflow their card on mobile

Reported with a screenshot (inline, no file), 2026-07-30. Full transcription in
[`ROUND2.md`](ROUND2.md) batch 7.

On `/souteze/{id}/nastaveni#pozvanky` at phone width, the **PIN** value box (`7 5 3 2 6 6 2 1`) and the
**ODKAZ** box (`https://wtips.cz/souteze/pozvanka/8dbe…`) both run past the right edge of their card
and are clipped by the viewport; the URL is cut mid-string with no ellipsis. „Obnovit" / „Zrušit"
below each render correctly.

**Diagnose, don't guess.** A plausible cause is a fixed-width or `min-width` input inside a flex/grid
cell without `min-width: 0` — the same mechanism as B7, where a flex child kept its min-content width
and overflowed. Verify by **measuring** (bounding boxes vs. the card's content box), not by eye.
Whatever the cause, the fix must hold at 320–430 px and must not regress the „copy" affordance.

## B14 — the verification airlock does not confine the user

Reported 2026-07-30: *„Tohle je to ověření, kde bych neměl mít možnost nikam kliknout … now i am able
to click anywhere and go through"*. Detail in [`ROUND2.md`](ROUND2.md) batch 9.

`/overeni-ceka` renders the **full navigation** and the product owner reports actually reaching other
pages. **Establish which of two very different explanations is true** before fixing: (1) cosmetic —
the guard bounces every click but the chrome makes the app look navigable; or (2) the B1 guard has
regressed and pages are genuinely reachable. B1 fixed `RequireVerifiedEmailSubscriber` (priority 8 → 7,
deny-list → allow-list); if (2), find out how it broke and add the regression test that would have
caught it. Either way the airlock renders without nav links or CTA.

## B15 — an invite-link sign-up loses the competition it was invited to

**The most serious defect of round 2.** Reported 2026-07-30 with a screenshot; detail in
[`ROUND2.md`](ROUND2.md) batch 8.

A user opened a shareable invite link to „Lipina", registered (the page **promised** the join),
verified the e-mail — and landed on „Zatím nehraješ v žádné soutěži". **The join silently did not
happen.** Every link-invited sign-up is currently losing its competition.

B1's assumptions say this path is supposed to work already („the landing page stores the join intent
and sends the user to the airlock itself — `LoginSubscriber` completes the join after verification"),
so **find out whether it regressed or was never wired for this entry point**, and say which.

Scope also covers what the same report asks for:
- After verification the user lands on the **competition detail**, not the Nástěnka.
- **Stateful join intent before any account exists** — an anonymous visitor following an invite link
  *or entering a PIN* is told which competition they are about to join, that intent survives sign-up
  **and** sign-in, and they land in the competition afterwards. The PIN half is new work:
  `competition_join_by_pin` is `🔒` today, so an anonymous visitor cannot enter a PIN at all.
- **Empty-state priority on `/nastenka`** for a user in no competition: **1. PIN join (primary) ·
  2. Procházet soutěže · 3. Vytvořit soutěž (third, smaller)**. Today there is no PIN affordance there
  and „Vytvořit soutěž" is primary.
- **Remove the useless PIN inputs from `/prihlaseni` and `/registrace`** (batch 12) — the same flow,
  so the same agent.

## B16 — „Uzamknout tipy" shows no date option, though B2 shipped one

Reported 2026-07-30 with a screenshot; detail in [`ROUND2.md`](ROUND2.md) batch 10.

The modal shows only the old immediate-lock message and „Ano, uzamknout" — **no „Ihned" / „V určený
čas" pair and no datepicker**, although B2 (`4e5f482`) built exactly that and production is current.

**Do not rebuild B2. Diagnose why its UI is not reaching the screen.** The mechanism is the `confirm`
controller's optional **`fields` target**, which JS moves into the dialog; a silent JS failure is
indistinguishable from B2's documented no-JS fallback („Without JS the form posts „Ihned""). Read B2's
„As built" and `.docs/features/confirm-modal.md` first, then report which explanation was true.

## B17 — the browser offers to save the password before it is confirmed

Reported 2026-07-30; detail in [`ROUND2.md`](ROUND2.md) batch 11.

On `/registrace`, filling the first password field triggers the browser's save-password prompt before
the confirmation field has been typed. Likely `autocomplete` semantics on the pair — verify in a real
browser, and check `app_reset_password` and the profile password change for the same gap.

## B18 — the notification dropdown overflows the viewport on mobile

Reported 2026-07-30 with a screenshot (inline, no file); transcription in [`ROUND2.md`](ROUND2.md)
batch 14.

Opening the bell on a phone renders the panel **past the left edge of the screen** — the heading
„Oznámení" is clipped to „ení" — while its right edge stops mid-screen. The content renders correctly;
only the position is wrong. It reproduces in the **admin shell** too, so the fix belongs to the shared
`Notification:Bell` component (or its CSS), not to one layout.

**Diagnose, do not assume.** The likely mechanism is a panel right-aligned to a trigger that sits near
the middle of a narrow bar, so it overflows the opposite edge. That is a *different* mechanism from
**B3** (a dropdown cropped by a clipping ancestor, fixed with `dropdownParent: 'body'`) even though the
symptom rhymes — read B3 so you can tell them apart, and measure rather than pattern-match.

Verify at 320 / 360 / 390 / 430 px, in **both** shells, with the panel empty and with several
notifications. Zero horizontal page overflow.

**Blocked on file ownership, not on a decision**: `templates/components/Layout/Nav.html.twig` is held
by the invite-funnel agent (B14/B15) while it strips the chrome from the airlock. Dispatch after that
lands, together with batch 13 (credits in the header), which touches the same bar.

## B19 — stray border with no padding around the tip form on match detail

Reported 2026-07-30 on `/zapasy/{id}`: *„weird border design, no spacing, probably the border should
not be there on match detail."*

Screenshot: [`screenshots/bug-b19-tip-card-border.png`](screenshots/bug-b19-tip-card-border.png) — the
„VÁŠ TIP" card contains a second, sharp-cornered bordered rectangle hugging the score steppers and the
„Upravit tip" button, with no padding between that border and its contents.

### Cause — found, not guessed (verify, then fix)

`templates/components/Guess/GuessSubmitForm.html.twig:14` gives the component root a card's chrome by
default:

```twig
<div{{ attributes.defaults({class: 'rounded-xl border border-white/10 bg-white/[0.03] p-4'}) }}>
```

The match-detail call site (`templates/portal/sport_match/detail.html.twig:161-167`) already knows the
component must render bare inside the page's own card, and overrides it:

```twig
class="!rounded-none !bg-transparent !p-0 !shadow-none !ring-0"
```

That list neutralises rounding, background, padding, shadow and ring — **but not `border`**. So the
border survives while `!p-0` removes the space inside it, which is precisely „border, no spacing", and
`!rounded-none` is why the leftover border has sharp corners.

### Fix

The one-token fix is to add `!border-0` to the override. **Prefer the structural fix if it is cheap**:
a component that ships card chrome by default and expects every embedded call site to remember a
five-utility incantation will drift again — the next call site will forget a different token. Consider
a `bare` prop (or a variant) on `Guess:GuessSubmitForm` that turns the chrome off in one word, and use
it here. Whichever you choose, say why.

**Check the other call sites** of `Guess:GuessSubmitForm` for the same half-neutralised chrome before
deciding — if more than one repeats the incantation, that settles it.

Out of scope: the form's internal layout, the steppers, and the button label (that is being changed to
„Uložit tip" by the same round's copy pass).

## B20 — the public nav overflows 53 px at 320 px

Found by the item-14 agent while measuring the homepage, 2026-07-30 — **not its surface, so it was
reported rather than fixed.**

At a 320 px viewport `HEADER.wtnav > .bar > .actions` measures **373 px**, overflowing the page by
53 px. It reproduces on `/ochrana-soukromi` as well as `/`, which places it in
`templates/components/Layout/Nav.html.twig` / the `.wtnav` rules rather than in any one page. Fine
again by 430 px.

Measure it rather than eyeballing; 320 px is the narrowest width this stream checks and several other
bugs this round were only visible there. Fix must hold for **both** nav variants (logged in and out) —
the logged-in bar is the crowded one (brand · bell · „+" · avatar · hamburger).

**Bundle with the queued chrome work** — `ROUND2.md` batch 13 (credits in the header) adds a *sixth*
element to this same bar, and **B18** (the notification dropdown positioned off the left edge) is the
same component. Doing them separately would mean measuring the same bar three times.

## B21 — the hero headline starves the demo card; team names vanish at 1024 px

Found by the item-14 agent while fixing the homepage score, 2026-07-30. **Contained, not fixed** — the
fix is a typography decision, not a bug fix.

The hero `<h1>` glues „a pak to" / „kámošům o hlavu." with `&nbsp;`, giving the left column a
**~700 px min-content floor**. So `lg:grid-cols-[1.15fr_0.95fr]` cannot hold its declared ratio and the
demo card collapses to ~390 px at 1280 px and ~235 px at 1024 px. Consequence, now visible because the
score no longer wraps: team names ellipsize to „Argen…" at 1280 px and disappear entirely at 1024 px.

The item-14 fix (`min-w-0` + `truncate`) makes this **degrade gracefully instead of overflowing**,
which is why it is a separate row and not a regression. The real fix is to stop the headline claiming
700 px — remove or move the non-breaking spaces, or allow the line to break — and that changes how the
headline reads, so it needs the product owner's eye.

---

## B22 — `/kredity` renders a 523 px-wide table at 320 px

Found by the B13/B16 agent while measuring, 2026-07-30 — outside its surface, so reported not fixed.

The credits page’s table is 523 px wide in a 320 px viewport. Same family as B13 and B7: a table whose
columns keep a min-content floor inside a container that cannot give them the room.

Read **B13’s** finding before reaching for `min-width: 0` — it establishes that `min-width: 0` alone is
**not sufficient** when the offending child has an intrinsic width (there, a UA `size=20` input). Measure,
then decide between an explicit zero-width contribution, horizontal scroll confined to the table’s own
container, or a stacked card layout at narrow widths.

## B23 — after `db:reset` no competition can reach the „Uzamknout tipy" modal

Found by the B13/B16 agent, 2026-07-30. **This is probably part of why B16 looked unreproducible, so it
is worth fixing before the next lock-related report.**

Every `DevFixtures` world is anchored to the **real calendar** (`today ± n days`, item 03 assumption 2),
so after a `composer db:reset` every competition has already started ⇒ „Tipy uzamčeny" ⇒ the
„Uzamknout tipy" button never renders. To exercise B2’s scheduled lock the agent had to push a fixture
match’s kickoff into the future by hand and restore it afterwards.

**Fix:** give `DevFixtures` at least one competition that has **not** started — an unlocked, not-yet-begun
soutěž the organizer can still lock — and document it in `.docs/FIXTURES.md` alongside the other worlds.
Keep it in `DevFixtures` (group `dev`), **not** `AppFixtures`: item 03 assumption 1 records that the
shared test baseline has many tests asserting exact counts over whole tables.

Related: **B11** is the same shape of gap (the premium world renders no „Rozložení tipů" surface). Both
are „the fixtures cannot demonstrate the feature" — worth doing together.

## B24 — flatpickr’s year renders near-black in the lock modal

Cosmetic, pre-existing since B2, found while verifying B16 (2026-07-30) and left alone as out of scope.

Inside the „Uzamknout tipy" dialog the flatpickr calendar’s year („2026") is drawn in near-black on the
dark panel — effectively invisible. The datepicker is a vendor widget being skinned by our dark theme;
expect the fix to be a specificity problem in the same family as **B3**, where vendor CSS out-specified
the skin on focus. Check the month name, the weekday row and the disabled/out-of-range days too, not
only the year, and verify inside the `<dialog>` rather than on a normal page.

## B25 — with JavaScript off, „Zobrazit další" hides matches unreachably

Found by the item-18 agent while verifying the Nástěnka's filters without JavaScript, 2026-07-30.
**Pre-existing since item 06**, outside that item's scope, so it was reported rather than fixed.

„Následující zápasy" renders the first 5 matches and hides the rest behind a „Zobrazit další (N)" button
driven by the `reveal` Stimulus controller. With scripting disabled the hidden rows carry `hidden` and the
button does nothing, so **the 6th and later matches cannot be reached at all** — not by scrolling, not by
any URL. Competition detail now uses the same 5-plus-„Načíst všechny zápasy" pattern (`ROUND2.md` batch 2),
so check both.

This matters more than a normal progressive-enhancement nit because **this stream has deliberately kept
every other control JS-free**: the soutěž switcher is a real GET form (item 04), the leaderboard search and
the Nástěnka filters are real GET forms (items 05, 15, 18), and the create wizard degrades. A reveal button
is the one place where turning JS off removes *content* rather than convenience.

**Options, cheapest first:** render all rows and let the button only collapse (so „hidden" is the enhanced
state, not the default); or make it a real link (`?vse=1`-style) that renders the full list server-side, the
shape item 05 used before its expand control was retired. Do **not** fix it by removing the truncation —
the product owner asked for exactly 5 with a load-more.

## B26 — the homepage hero's „1. MÍSTO" chip sits on the away team name

Found by the B21 agent while measuring, 2026-07-30. **Left alone deliberately** — items 14 and 16 both
declare the hero's floating chips intentional decoration and forbid moving them.

The „1. MÍSTO 147 / 248" chip (`-right-6 top-[22%]`) overlaps the demo card's **away** team block. B21
made it better, not worse — before, at ≥1280 px it covered „Francie" **63 of 63 px (100 %) plus the whole
FRA coin**; now it covers **60 of 74 px with the coin clear**. At 1024 px it covers „Francie" entirely,
where previously the name had zero width anyway.

So the name is no longer *truncated* — a translucent `.glass` panel is simply sitting on it. That is a
design question, not a layout defect: the chips are supposed to overlap the card's edges, and whoever
placed them did not anticipate the card being ~130 px wider (which is what B21 gave back).

**Fix, if the product owner wants the away team unobstructed at 1024–1280 px:** move the chip's anchor
(`-right-6 top-[22%]`). Do not remove it — it is part of the hero's designed look. Verify the other two
chips („+12 b Marek vystihl skóre", „+9 b Ana trefila výsledek") do not acquire the same problem, and
measure at 1024 / 1280 / 1440 / 1600 px, where the two-column layout exists at all.

Note also, unchanged by B21 and not part of this: „Argentina" still ellipsizes at 430 px (70 of 82 px) in
the single-column layout.

### Decision (product owner, 2026-07-30): fix it, orchestrator's call on how

> B26 - up to you, just make it not look buggy, the stats can be mock here, totally ok

So the chip's **content stays** — invented figures inside a product mockup are explicitly fine
(`ROUND2.md` batch 20 draws that line: fake numbers asserting things about the *business* are out,
fake data inside a *picture of the product* is fine). Only the **position** is in scope.

**„Not buggy" defined, so this is measurable rather than a matter of taste:**

- A chip overlapping the card's **edge, corner or empty background** is the **intended** look — the other
  two („+12 b Marek vystihl skóre" top-left, „+9 b Ana trefila výsledek" bottom-left) do exactly that and
  read as designed. Do not remove or un-overlap them.
- A chip overlapping **text, a team name, a team monogram coin, a score or a distribution bar** reads as
  broken. That is the defect: `-right-6 top-[22%]` lands „1. MÍSTO 147 / 248" on the away team block.
- So: **reposition the chip so it overlaps only card edge/background at every width where the two-column
  layout exists** (≥ 1024 px). Below that the hero is one column and the chips behave differently — check
  it, but the bug is the wide case.

**Find the position by measuring, not by eye.** Candidate anchors will collide with different things —
the card's top-right already carries „SKUPINA A · MD3" meta text, so straddling that corner trades one
overlap for another. Test the options, pick the one with zero content overlap at 1024 / 1280 / 1440 /
1600 px, and report the rects.

**Check all three chips against the rule, not just this one.** B21 widened the card by ~130 px, which is
what moved this chip onto content; the same widening may have brought the other two closer to something.
Report their measurements too even if you change nothing.

Keep it a `.glass` chip in the same visual language — this is a position change, not a redesign, and the
hero's floating-chip look is deliberate (items 14 and 16 both protected it).


## B27 — the two paywall cards on match detail do not match, and the purchase confirm may not be firing

Reported 2026-07-30 against `/zapasy/019fa008-7233-7603-b414-e0fb581541ef`:

> these 2 are misaligned, first card is correct, the other one align to look more like the first card.
> there as well should be confirmation before paying any credits

Screenshot: [`screenshots/bug-b27-paywall-misalignment.png`](screenshots/bug-b27-paywall-misalignment.png).

### Part 1 — the alignment (the actual ask)

Both cards are paywalls for the same kind of thing, and the product owner has named the **first** as
correct. Measured from the screenshot:

| | „ROZLOŽENÍ TIPŮ" (correct) | „POŘADÍ ZA ZÁPAS" (to fix) |
|---|---|---|
| gold „VYLEPŠENÍ" pill + eyebrow | yes | yes |
| headline | „1 hráč tipoval" | „Uvidíš konkrétní tipy kolegů" |
| top-right slot | **the CTA**, „Odemknout za 10 kr. →", gold outline | „1 hráč s tipem" (a count) |
| the lock treatment | a **centred gold coin** over the blurred skeleton, with the pitch under it | a **striped inner panel**, left-aligned, lock in a grey circle |
| the CTA button | gold, top-right | **blue**, inside the striped panel |

So the second card carries a second, differently-styled paywall *inside* itself. **Item 10 §6 intended
the opposite** — it reused `Boost:Panel` for the CTA specifically *„so both paywalls read as one
treatment"*. What actually happened is that `Boost:Panel`'s **inline** shape brings its own striped
container and its own blue button, so nesting it inside the item-10 shell produced two treatments, not
one. That is the defect: the intent was right and the composition defeated it.

**Fix direction:** make the „Pořadí za zápas" lock read like the distribution lock — centred gold coin
over the blurred skeleton, the pitch beneath it, and the CTA in the card's **top-right**, gold. Whether
that means `Boost:Panel` gains a shape that renders bare (no striped container, no button colour of its
own) or the item-10 shell stops nesting it, **decide from the code and say which** — but do not duplicate
`Boost:Panel`'s pricing, affordability, superset and B6 „soutěž už skončila" logic, which is why item 10
reused it in the first place. Note item 11 already established gold as „paid feature" for both funding
models, so gold is the target, not blue.

The count („1 hráč s tipem") should not simply be deleted to free the top-right corner — decide where it
goes and say so.

### Part 1b — the whole paywall card is the click target

Product owner, 2026-07-30:

> as well those cards whole clickable with the confirmation -> make the whole card clickable instead of
> just the portion of it

**Ship the two as ONE unit** (product owner, 2026-07-30: *"you should implement the modal together with whole clickable which is okay"*). The whole-card target and a **working, verified** confirm dialog land in the same commit. If Part 2 finds the confirm is not firing, **fix that first** - do not land the enlarged target and leave the dialog for a follow-up, because the enlarged target is only safe *because* of the dialog.

So on **both** full paywall cards the entire card is the purchase trigger, and it opens the same confirm
dialog the button opens today. Scope: the two **full** paywall cards on match detail. Use item 18's
established pattern — the stretched target painted over the card (`.card-stretch` / `.card-raise`) — except
here the stretched element is a **form submit**, not a link. Same rule applies and it is not negotiable:
**nothing interactive may end up nested inside the stretched control**; anything that must stay clickable
is raised above it.

**⚠ This makes the missing-confirm failure mode materially worse, and the fix must account for it.**
A small button that spends credits without a dialog is a bad click. A whole card that does it is an
*accidental* click — the target is now hundreds of times larger, and the blurred skeleton behind it looks
like content rather than a button. Combined with Part 2's finding (the confirm is wired but its firing is
unverified) and the **B16** precedent (a `disconnect()` silently reduced the same controller to a plain
submit, looking exactly like the no-JS path), the whole-card target must **not** be live unless the
confirm is:

- **Make the stretched target depend on the `confirm` controller having connected** — e.g. the controller
  itself enables/attaches it, so a page where the JS never ran keeps only the explicit button. This
  inverts the usual enhancement direction on purpose: the *big* target is the enhancement, the *small*
  one is the floor. JavaScript-off support is deferred (`PLAN.md` decision 0), but a controller that
  fails to connect is a different thing and this stream has hit it twice.
- **Verify server-side that a purchase is guarded and charges exactly once** regardless of the dialog.
  `BoostFlowTest` already pins that a stale page cannot burn credits — do not weaken it; add to it.
- Report what an accidental click actually does end-to-end, and what happens on a double click.

**Deliberately NOT in scope: the compact „Rozložení tipů" strip inside a match card.** That strip is a
sibling of the card's own wrapping link (items 18/21), which navigates to the match. Making the strip a
second large target with a *different* action, immediately adjacent to the first, would be worse than the
current small button — two big neighbouring targets where one buys credits and the other navigates. Leave
it as it is and say if you disagree; the product owner said „those cards", meaning the two full ones.

**`/_design` must stay inert.** The gallery renders these paywalls, and a stretched submit is exactly the
kind of thing its `inert()` macro exists to neutralise — `DesignStyleguideFlowTest::testNothingOnThePageCanAct`
asserts the page holds no `method="post"` and exactly one form. Keep it green.

### Part 2 — the confirm is already wired; find out why it was not seen

**Do not „add confirmation". It exists on every purchase path.** Verified in the templates:

- `Match/TipStats.html.twig` lines ~62-71 and ~186-203 — both the compact strip and the full card carry
  `data-controller="confirm"` with `…title-value="Odemknout „Rozložení tipů ostatních""`, a message naming
  the price **and** the viewer's balance, and `…confirm-label-value="Koupit za N kr."`.
- `Boost/Panel.html.twig` lines ~105-109 and ~165-175 — the same on both of its shapes.

So either the product owner reported the requirement without exercising it, or **the confirm controller is
not firing on this page** — which has a precedent worth reading first: **B16**, where the `confirm`
controller's dialog *was* present and correct but a `disconnect()` destroyed its only `fields` target, so
every later open silently fell back to a plain submit that looks identical to the no-JS path. A silent JS
failure and „no confirm was implemented" are indistinguishable from a screenshot.

**Establish which it is, in a real browser, and report it.** Click both CTAs and confirm a dialog appears,
that cancelling really cancels (no credits move), and that confirming charges exactly once. Check the
console. If it does fire, say so plainly — „the product owner did not see it because it works" is a
legitimate outcome and better than a speculative fix.

**The no-JS path is out of scope** — the product owner deferred JavaScript-off support on 2026-07-30
(`PLAN.md` decision 0). But the reason that question was worth asking still applies for a different
reason: if the `confirm` controller fails to *connect* (a JS error, a failed asset fetch, a `disconnect()`
bug as in B16), the form posts a credit spend with no dialog and the page looks completely normal. So
**check the console while you drive it**, and confirm the spend is guarded server-side (CSRF, and charging
exactly once) rather than only by the dialog. `BoostFlowTest` already pins that a stale page cannot burn
credits — do not weaken it.

### Constraints

- Prices from `Credits/PricingConfig` — never a literal. Both cards already do this; keep it.
- **Premium XOR boosts**; do not introduce a third funding state.
- `Boost:Panel` is shared — it renders on competition detail (`#vylepseni`), inside the item-10 match-detail
  shell, and in `/_design` half A. **Verify all three** after changing its shape; `/_design` must keep
  passing `DesignStyleguideFlowTest::testNothingOnThePageCanAct` (the gallery is inert, so its CTA must
  stay neutralised).
- Measure at 1600 / 1440 / 1024 / 430 / 320 px — zero overlaps, zero horizontal overflow.

**Queued behind item 21**, which owns `assets/styles/app.css`.

---

## Addenda from the fixes (2026-07-30)

**B22 — the report's premise was wrong, and the correction is the useful part.** Horizontal scroll was
**already** confined to the table's own `.overflow-x-auto` wrapper, so the page never overflowed at any
width. What „a 523 px table at 320 px" actually described was that only **238 of 482 px** was visible,
with **both number columns — the point of a ledger — parked off-screen behind a scroll with no
affordance**. And `min-width: 0` had nothing to give: the floor *is* the sum of five column min-contents
(„Datum" alone pins 140 px via `white-space: nowrap` over „25. 07. 2026 13:02", „Popis" 135 px) inside a
240 px content box — B13's lesson one step further, where the track itself is the floor. Fixed by
**stacking** below 640 px: header row dropped, each cell carrying its own `data-label`, nothing scrolling
at all. Also learned: a Tailwind **utility always beats the component layer**, so the narrow shape needed
those declarations moved into `.tx-table` rather than fought with `!important`.

**B27 — the confirm dialog was never missing.** Driven in a real browser it fires on both CTAs with the
right Czech copy, cancel moves no credits, confirm charges exactly once, and a double click (card body or
the dialog's own button) still posts once. So the product owner's „there as well should be confirmation"
was a requirement stated without exercising it — **not** another B16. Worth recording, because B16 made
the opposite outcome entirely plausible and the diagnosis was the only way to tell.

The unification went via a new **`shape="bare"`** on `Boost:Panel` — the gold `.dist-unlock` control only,
no container and no button colour of its own — so every pricing / affordability / superset / B6 rule
stayed exactly where it already lived. The count („N hráčů s tipem") was **not** deleted: it moved into
the headline slot, which is what the correct card puts there, and the pitch moved onto the skeleton.

The whole-card target is a submit **stretched over the card** (item 18's `.card-stretch`), a **sibling** of
the small CTA inside the same form — one CSRF token, one price, one dialog, nothing interactive nested
inside it. It ships `hidden` and the `confirm` controller unhides it on connect via a new `stretch`
target, so **the big target is the enhancement and the small button is the floor**: a page whose JS never
ran keeps only the button, and an accidental card-sized click cannot spend credits unconfirmed.

## B28 — the hero's other two floating chips also sit on content

Found while fixing **B26**, 2026-07-30, by measuring all three chips against the *full* content inventory
rather than only team names and coins. **Left alone deliberately** — the grant to move an anchor was scoped
to one chip, and B26's own row told the agent not to un-overlap the others.

**B26's row contained a false premise, which is mine.** It asserted that the other two chips „do exactly
that and read as designed", i.e. overlap only the card's edge or background. Measurement says otherwise:

- **„+9 b Ana trefila výsledek"** (`-left-14 bottom-[14%]`) — **flagrant.** At 1280/1440/1600 px it covers
  **„Tipy 248 hráčů" over 80 of its 87 px at the text's full height**, the **`.dist-bar` at its full 8 px
  height**, and the legend's first item. At 1024 px it covers the bar plus **both** legend lines at full
  height. The distribution bar visibly runs behind the glass.
- **„+12 b Marek vystihl skóre"** (`-left-10 -top-5`) — **marginal.** Its bottom edge dips **6.4 px** into
  the „Živě · 67'" pill's 25 px box and grazes the pill's text ink by **0.4 px** (0.3 px at 1024) — across
  the pill's whole 85 px width.

So by the rule B26 established — *edge and background overlap is the designed look, text/coin/score/bar
overlap reads as broken* — two of the three chips are still broken, and the product owner's instruction
was „just make it not look buggy".

**Fix:** move both anchors into a band that holds a chip without content, exactly as B26 did. The B26 agent
suggests pushing „Ana" into the bottom-padding band (`bottom-8`-ish, or `-left-20`) and „Marek" to `-top-6`.
Both are two-token changes in `templates/home.html.twig`. **Measure, do not adopt those suggestions on
trust** — B26 tested four anchors at ten widths and rejected the obvious mirror position because it left
only 3.9 px of clearance to „SKUPINA A · MD3", one copy change away from the same bug.

Constraints, all as B26: keep them `.glass` chips with the same content and visual language (invented
figures inside a product mockup are fine — `ROUND2.md` batch 20); do not remove them; **do not touch
`assets/styles/app.css`**; do not undo items 14, 16 or B21 (no „MS 2026", the accent-card CTA stays last,
the hero `<h1>`'s two remaining `&nbsp;` glues stay). All three chips are `hidden lg:flex`, so 768 px and
below is `display: none` and unaffected.

Also noted by that agent and **chip-independent** (proved by deleting all three chips and re-measuring):
„Argentina" still ellipsizes by 12 px at **1024 px only** (`w=69.8` vs `scrollWidth=82`) — B21 residue at
that one width, not caused by any chip.

## B29 — the homepage demo card's team names collapse at narrow widths

Found by the B26 agent and confirmed independently by the B28 agent, 2026-07-30. **Proved
chip-independent twice**, by deleting all three floating chips and re-measuring: byte-identical with and
without them, so it is not B26/B28 residue.

In the hero's demo match card, the team names ellipsize in the single-column layout:

| width | „Argentina" | note |
|---|---|---|
| 1024 px | 69.8 px of 82 — **12 px clipped** | two-column layout, the one width where it survives the column split badly |
| 430 px | **12 px clipped** | single column |
| 320 px | **~15 px total** — both names | effectively unreadable |

This is **B21 residue**: B21 gave the demo card ~130 px back at the wide widths and added `min-w-0` +
`truncate` so long names degrade gracefully instead of overflowing — which was the right call then, and is
why this shows as an ellipsis rather than a layout break. But at 320 px a name reduced to ~15 px is not
graceful degradation; on a phone it reads as broken, which is the standard the product owner set for
**B26** („just make it not look buggy").

**Worth noting what this is and is not.** It is a *marketing mockup* on the landing page, not a real match
list — the production card (`Match:MatchRow`, item 21) was measured down to **238 px** with zero wrapped
names. So this is confined to `templates/home.html.twig`'s hero illustration.

**Fix direction (measure, do not assume):** the hero card's fixture block keeps a three-column
`[1fr auto 1fr]` shape at every width, so at 320 px the score and coins take the room. Options include
stacking the fixture below ~430 px, shortening to the teams' short names / monograms at narrow widths, or
reducing the score's type scale there. Do **not** remove `min-w-0`/`truncate` — that is what stops it
overflowing outright.

Constraints as B26/B28: `templates/home.html.twig` only, no `assets/styles/app.css`, do not undo items 14,
16, B21, B26 or B28 (no „MS 2026", the accent-card CTA stays last, the `<h1>`'s two `&nbsp;` glues stay,
and all three chips keep their measured clearances). All three chips are `hidden lg:flex`, so they are not
in play at the widths this bug lives at.

## B30 — the „Uzamknout tipy" dialog's calendar overflows its own panel at 320 px

Found by the B24 agent while measuring contrast inside the dialog, 2026-07-30. **Pre-existing, and not
B24's colour bug** — reported rather than fixed because it is a different family and a different risk.

flatpickr **hard-codes `width: 307.875px`** on `.flatpickr-calendar`, `.flatpickr-rContainer`,
`.flatpickr-days` and `.dayContainer`. Inside a 320 px dialog the calendar box measures x=81 w=308, so the
„So" / „Ne" columns and the minutes field are cut off. `document.scrollWidth` overflow is **0** — the dialog
itself scrolls — so the content is reachable, just awkward.

This is the **B13 / B22 family**: a fixed-width child inside a container that cannot give it the room, where
`min-width: 0` has nothing to offer because the child's width is declared outright. B22 is the closest
precedent (there the floor was the *sum* of five column min-contents, and the fix was to restructure at a
breakpoint rather than to fight the widths).

**Care required:** the fix means overriding four vendor width declarations on a **shared widget** — the
`datepicker` controller is used outside this dialog too. Verify every other flatpickr site after changing
it, and mind B2's two documented traps: `static: true` **hangs the page** (it re-parents the controller's
own element into an infinite Stimulus loop), and a flatpickr inside a `<form>` **silently blocks
submission** unless the form carries `novalidate`.

Reachable after a plain `db:reset` on „Vysočina – naše parta" (B23), which is the only competition that
renders „Uzamknout tipy".

## B31 — after `db:reset` no competition can reach the scorer picker

Found by the B12 agent, 2026-07-30. **The same shape of gap as B11 and B23**, both of which cost real
diagnostic time before they were closed.

`scorer_hit` is enabled on exactly one competition — „Vybrané zápasy party" (`019bbbbb-…033`) — whose only
two matches carry `AppFixtures`' **absolute** dates (2025-06-10, 2025-06-20). Against the real dev clock
those are long past, so no open tip form renders and **the scorer picker cannot be reached at all** after a
plain `composer db:reset`.

The B12 agent worked around it by verifying the equivalent single-select `score_entry` player picker (same
`no_results` renderer, same class) and reading `scorer_picker_controller.js` directly — a reasonable
substitute, but it means one of the five pickers was not exercised in a browser.

**Fix:** give a `scorer_hit`-enabled competition at least one match that is still open for tipping, anchored
to the **real calendar** (`today + n`) like the rest of `DevFixtures` — item 03 assumption 2 is deliberate
and must hold. Keep it in `DevFixtures` (group `dev`), **never** `AppFixtures`: item 03 assumption 1
records that many integration tests assert exact counts over whole tables. Document it in
`.docs/FIXTURES.md` alongside the „which world demonstrates which state" table that B11/B23 added — that
table exists precisely so this class of gap is visible without reading PHP.

### B30 as built (2026-07-30)

The four hard-coded vendor widths became relative **without** a breakpoint restructure, an `!important`,
or any change to the dialog: `.flatpickr-calendar` **keeps** `width: 307.875px` and merely gains
`max-width: 100%`, and the three inner containers become `flex: 1 1 0` / `width: 100%` with `min-width: 0`.
That is what makes it safe on a **shared** widget — for a floating picker `max-width: 100%` resolves
against a viewport-sized containing block and never binds (measured 307.9 px even at a 320 px viewport);
for the inline one it resolves against the dialog column and shrinks to 214 px.

**Two things the fix uncovered:**

1. **`.flatpickr-days` overhung the calendar's border box by ~2 px, clipping „Ne" at EVERY width** —
   including 1440 px. Pre-existing, unreported, fixed for free.
2. **Shrinking the calendar exposed the header**, which then wrapped „2026" onto a second line and clipped
   it against the months bar — **visually undoing B24 one commit after B24 landed**. Fixed with a
   **container query** on `.flatpickr-months`, deliberately not a media query: the picker is shared and
   sized by its box, never by the viewport. That is the B13 / B22 lesson applied before it could bite.

Verified with a **0.5 px sweep of the container from 140 → 480 px** (681 widths, both arrows painted):
nothing breaks above 163 px ≈ a 267 px viewport. **All ten flatpickr call sites across seven pages** were
re-checked at 320 / 390 / 1440 px — all keep the vendor 307.9 px box, seven weekday columns inside, seven
days per row, zero document overflow, console clean. B2's traps intact (`static: true` not reintroduced,
`novalidate` untouched); B24's year still 9.86:1 at every width; the full schedule flow re-run at 320 px
and 1440 px with Prague → UTC persistence confirmed in the database, then cancelled.
