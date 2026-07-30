# Pending join intent — „I am about to join this soutěž"

**Problem it solves (B15).** Somebody arrives with a way into a competition — a shareable
link, a PIN, or an e-mail invitation — but has no account yet, or has one that is not
verified. The join therefore cannot happen *now*. Between „now" and „allowed" the person
signs up, walks out of the app into a mailbox, and comes back through a verification link
that may well be opened on **another device**. Anything remembered only in the PHP session
is gone by then: the session cookie is a browser-session cookie, and the mail click is out
of band. That is exactly how every link-invited sign-up silently lost its competition.

## The pieces

| Piece | Where | Job |
|---|---|---|
| `InvitationKind` | `src/Enum/InvitationKind.php` | `Email` · `ShareableLink` · `Pin` — how the visitor arrived. Only `Email` proves mailbox ownership. |
| `InvitationContext(Resolver)` | `src/Service/Invitation/` | Turns (kind, token) into „which soutěž, in what state" — the thing the landing page names. |
| `PendingJoin` | `src/Service/Competition/PendingJoin.php` | Immutable (kind, token). Excluded from autowiring in `config/services.php`. |
| `PendingJoinStore` | `src/Service/Competition/PendingJoinStore.php` | **The only place intents are written or read.** Session for a visitor with no account, `User.pendingJoinKind/pendingJoinToken` the moment one exists. |
| `RememberPendingJoinCommand` / `ForgetPendingJoinCommand` | `src/Command/` | The durable half — mutating the `User` goes through the command bus like every other write. |
| `LoginSubscriber` | `src/Service/Security/LoginSubscriber.php` | Consumes the intent at the first login the account is *allowed* to join on, and redirects to the **competition detail**. |

## The rule

**A shareable link or a PIN proves nothing about identity, so it never verifies an
account** — it is remembered and honoured after verification. An **e-mail** invitation
addressed to the account's own mailbox does prove ownership, so it is accepted immediately
and verifies the account (`AcceptCompetitionInvitationHandler`). Do not "fix" a lost join
by verifying accounts that clicked a link.

## Reading an intent

Only ever through `PendingJoinStore`:

- `remember(PendingJoin, ?User)` — always writes the session; **pass the user** as soon as
  one exists, or the intent will not survive the mail round trip.
- `consume(User)` — reads and clears both layers; session wins (it is the fresher one).
- `peekAnonymous()` — non-clearing read, so a landing page can be reloaded.
- `forget(User)` — the join just happened inline; drop the intent so the next login does
  not replay it as „V soutěži již jsi.".

## The three entry points

All three are **public** controllers in `src/Controller/Invitation/`, all allow-listed in
`RequireVerifiedEmailSubscriber` (they handle the unverified case themselves):

- `/pozvanka/{token}` — e-mail invitation
- `/souteze/pozvanka/{token}` — shareable link
- `/pripojit` (+ the 8-box bar's `POST /pripojit/rychle`) — PIN

They all render `templates/invitation/landing.html.twig`, which **names the soutěž before
any account exists** and hosts the one `Auth:InvitationForm` that both signs up and signs
in. The PIN never travels in the URL — it lives in the session, so the landing survives a
reload and the browser history carries no secret.
