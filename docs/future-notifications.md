# Future notifications roadmap

This repo emits rich domain events on every state change (see `src/Event/`).
Only a small subset currently have listeners — the rest are deliberately
buffered for future listeners per the product spec.

## Transactional emails (shipped)

| Event | Listener | Template |
|-------|----------|----------|
| `UserRegistered` | `SendVerificationEmailHandler` | `emails/verify_email.html.twig` |
| `EmailVerified` | `SendWelcomeEmailHandler` | `emails/welcome.html.twig` |
| `PasswordResetRequested` | `SendPasswordResetEmailHandler` | `emails/password_reset.html.twig` |
| `GroupInvitationSent` | `SendGroupInvitationEmailHandler` | `emails/group_invitation.html.twig` |
| `JoinRequestApproved` | `SendJoinRequestApprovedEmailHandler` | `emails/join_request_approved.html.twig` |

All are routed via `symfony/mailer` → `SendEmailMessage` → `async` messenger
transport. In dev, mail lands in Mailpit at http://localhost:8025 (SMTP 1025).

## Deferred listeners

These events fire today but have no listeners. The listener column is a
proposal — adjust as product priorities evolve.

### Account / security

| Event | Proposed listener | Intent |
|-------|-------------------|--------|
| `PasswordChanged` | `SendSecurityAuditEmailHandler` | Optional "your password was changed" notice for account security. |
| `UserDeleted` | `AnonymizeUserDataHandler` | GDPR erasure pipeline — anonymize nickname, strip PII while keeping aggregate leaderboard integrity. |
| `UserBlocked` / `UserUnblocked` | `NotifyAdminAuditLogHandler` | Audit log to a dedicated table / Slack channel. |

### Tournament / scoring

| Event | Proposed listener | Intent |
|-------|-------------------|--------|
| `TournamentCreated` | `AnnounceTournamentHandler` | Push a digest to followers / feature on the landing page. |
| `TournamentFinished` | `SendTournamentClosedDigestHandler` | Email the final leaderboard to every group in the tournament. |
| `TournamentRulesChanged` | `NotifyMembersRulesChangedHandler` | Inform members about scoring change, especially when recalc kicks in. |
| `TournamentPointsRecalculated` | `NotifyMembersPointsRecalculatedHandler` | Heads-up that points moved since last they checked. |
| `SportMatchPointsRecomputed` | `NotifyInterestedGuessersHandler` | Push-style per-match breakdown after a result lands. |

### Matches / guesses

| Event | Proposed listener | Intent |
|-------|-------------------|--------|
| `SportMatchPostponed` | `NotifyGuessersMatchPostponedHandler` | Tell guessers the kickoff moved; open-for-guesses window extended. |
| `SportMatchCancelled` | `NotifyGuessersMatchCancelledHandler` | Tell guessers their guesses were voided. |
| `GuessSubmitted` / `GuessUpdated` | `PushLiveBadgeCountHandler` | Live dashboard badge refresh via Mercure. |
| `GuessEvaluated` | `NotifyGuesserPointsEarnedHandler` | Opt-in email after each scored guess. |

### Discovery / engagement (not yet emitted)

| Event | Proposed trigger | Intent |
|-------|------------------|--------|
| `GuessDeadlineApproaching` | Scheduled job — 1h before kickoff | Nudge members who haven't tipped yet. Needs a cron or symfony/scheduler job. |
| `WeeklyDigestRequested` | Weekly cron | Summary of last week's points per group. |

### Groups / membership

| Event | Proposed listener | Intent |
|-------|-------------------|--------|
| `MemberJoinedGroup` | `NotifyOwnerMemberJoinedHandler` | Heads-up to group owner when someone joins via PIN / link. |
| `MemberLeftGroup` / `MemberRemoved` | `NotifyPartiesMembershipChangedHandler` | Dual-side notice (owner + removed member). |
| `GroupInvitationRevoked` | `NotifyInviteeInvitationRevokedHandler` | Close the loop with the invitee if the invitation is pulled. |
| `JoinRequestRejected` | `NotifyRequesterRejectedHandler` | Friendly rejection email. |
| `LeaderboardTiesResolved` | `NotifyTiedMembersResolvedHandler` | Tell tied users the final rank was set. |

## Implementation notes

- Every listener will be `final readonly` + `#[AsMessageHandler]` on the
  `event.bus`.
- Templated emails route via `SendEmailMessage` → `async` transport; rich
  notifications (Mercure, Push) plug into the same event stream.
- Scheduled triggers (`GuessDeadlineApproaching`, digests) need
  `symfony/scheduler` or cron wiring — not yet present.
- A single `NotificationPreferencesService` is the natural home for
  per-user opt-outs once the roadmap starts to land.
