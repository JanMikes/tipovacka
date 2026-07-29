# Bug / hardening backlog (UI-nav stream)

Reported by the product owner during the 2026-07-29 session, alongside the page-restructure items.
These are **independent of the page redesign** — they can be fixed in any order and mostly do not
touch the shared surfaces listed in `PLAN.md`.

Legend: `TODO` · `IN PROGRESS` · `DONE` · `BLOCKED`

| # | Title | Status | Commit |
|---|-------|--------|--------|
| B1 | Unverified e-mail account can still use the app | DONE | `7b3f010` |
| B2 | „Uzamknout tipy" — allow locking now **or** at a chosen time | TODO | — |
| B3 | tom-select dropdown clipped on „Správa tipů členů" | TODO | — |
| B4 | Match detail omits a competition the user is a member of | TODO | — |
| B5 | Locked/past-deadline state is not reflected in the UI after locking | TODO | — |
| B6 | Boost can be bought for a competition that is already over | TODO | — |

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
- **Account deletion** (`/portal/ucet/smazat`) stays reachable while unverified, as the item
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

---

## B3 — tom-select dropdown clipped on „Správa tipů členů"

**Report.** `/portal/souteze/{id}/spravovat-tipy` — „the input is broken, probably overflow or
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

---

## B4 — Match detail omits a competition the user is a member of

**Report.** After locking tips: on `/portal/zapasy/019fa008-7232-7284-aa49-b7e50684c0bc` the „Vaše
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
