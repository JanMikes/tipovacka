# „Pozvat přítele" — inviting is a player's action

**The bug this closes.** The organizer of a *global* soutěž had no way to invite anybody:
not on the competition page, not in „Nastavení". All three invitation permissions carried a
blanket `!isGlobal`, so the whole „Pozvánky" section simply never rendered. And in a private
soutěž a plain member could pass the PIN on but could not send an e-mail invitation, which
was an arbitrary place to stop — `SHARE_JOIN_LINK` already said *a partička grows because
the players invite their friends, not because the organizer is the only one holding the
code*.

## Who may invite

| Attribute | Granted to | Bounded by |
|---|---|---|
| `SHARE_JOIN_LINK` | admin **or any active member** | not deleted, schedule not complete |
| `INVITE_MEMBER` | admin, owner, **or a member of a competition that `isOpenToInvites`** | same |
| `MANAGE_JOIN_MECHANICS` | admin or owner, **private only** | same |

`Competition::$isOpenToInvites` is the whole subtlety: a **global** competition always is
(it is publicly joinable anyway), a **private** one is exactly while the organizer keeps a
PIN or a shareable link alive. Revoking both is how an organizer closes the doors, and a
member's e-mail must not reopen them — the owner and an admin still invite either way,
because they are the ones holding that switch.

`MANAGE_JOIN_MECHANICS` did not move. Regenerating/revoking the PIN and the link, the bulk
paste-an-address-book form and the pending-invitation list stay the organizer's, all behind
that one attribute (`SendBulkInvitationsController` was moved onto it, and the settings
controller stopped gating its section on `INVITE_MEMBER`, which no longer means „owns this").

## The one surface

`templates/portal/competition/_invite_modal.html.twig`, opened by the „Pozvat přítele" card
(`#pozvat`) on the competition detail — the card is the **only** trigger, so the page carries
exactly one `modal` controller and one dialog for it; the action bar's „Pozvat přítele" is a
plain `#pozvat` anchor. What is inside differs by competition, and the difference is factual,
not cosmetic:

- **private** — the e-mail form, the shareable link and the PIN;
- **global** — the e-mail form and the competition's own public invitation page, plus the
  entry fee spelled out. No PIN and no link exist to show.

The e-mail form posts to `competition_invitation_send` with a hidden `navrat=detail`, so a
plain member is redirected back to the competition instead of to a „Nastavení" they may not
open. Anything other than `detail` falls back to the settings page — the parameter can never
point somewhere of the submitter's choosing.

## Two invitations wearing one button

`SendInvitationController` branches on `$competition->isGlobal`:

| | private | global |
|---|---|---|
| Command | `SendCompetitionInvitationCommand` | `InviteToGlobalCompetitionCommand` |
| Writes | stub `User` + active `Membership` + `CompetitionInvitation` (7-day token) | **nothing** |
| The link | `/pozvanka/{token}` | `/souteze/{id}/pozvanka` |
| Joining | free, and the seat is already held | the ordinary entry-fee join |
| Revocable / expires | yes / yes | no / no |

The seat held up front is what lets an organizer tip on an invitee's behalf before they
accept. Handing that out in a paid competition would be a free membership, so **both**
private-path entries refuse a global competition outright
(`CompetitionIsGlobal::seatCannotBePreProvisioned`, thrown by `CompetitionInviter` and by
`SendCompetitionInvitationHandler`). Do not "simplify" that into one check — the wizard
reaches the inviter without going through the handler.

## The global landing

`/souteze/{id}/pozvanka` → `JoinGlobalCompetitionInviteController`, public (allow-listed in
`RequireVerifiedEmailSubscriber`, inventoried in `AnonymousReachabilityTest`), and the fourth
member of the family in [join-intent](join-intent.md). It is the only landing carrying **no
secret**: its „token" is the competition's own UUID, and the competition is on the public
„Soutěže" list anyway. It exists for the one thing that list cannot do — name *this*
competition to somebody with no account, and carry that intent through sign-up and the
verification-mail round trip.

Anything that is not a joinable global competition — a private one's id, a deleted one, a
nonexistent one — resolves to the same 404 „Pozvánka nenalezena". Deliberately
indistinguishable: otherwise the page confirms that a given UUID names a real partička.

Everything after authentication is the ordinary paid join, `JoinGlobalCompetitionCommand`.
Being invited buys no discount: an insufficient balance lands on `/kredity` with the
shortfall named and the competition remembered (`GlobalJoinReturnIntentSession`), exactly as
pressing „Připojit se" on the competition page does. A pending intent replayed at login
(`LoginSubscriber`) treats `InsufficientCredits` as its own explainable outcome — nothing is
broken, the wallet is just short.
