I now have a complete and detailed picture of the domain model, routes, authorization, and flows. Here is the report.

---

# Tipovačka (wtips) — Domain Model & Route Catalog

A Symfony CQRS app for **sports score-prediction pools** (Czech "tipovačka"). Users create **tournaments** (turnaje) of **matches** (zápasy); players form **groups** (skupiny) within a tournament and submit **guesses/tips** (tipy) on match scores; configurable **rules** award points; a per-group **leaderboard** (žebříček) ranks players, with manual **tie resolution**. UI strings are Czech. All UUIDs are v7. Soft-delete is pervasive.

---

## 1. ROUTE MAP (79 routes)

Roles come from `config/packages/security.php` access_control (path-prefix based) plus per-action **voters** (`denyAccessUnlessGranted`). Role hierarchy: `ROLE_ADMIN → ROLE_USER`. Firewall: form login (`app_login` → `portal_dashboard`), logout (`app_logout`), 7-day remember-me, `switch_user` for `ROLE_ADMIN`.

### PUBLIC (PUBLIC_ACCESS)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET | `/` | `app_home` | Landing/home page | public |
| GET | `/ochrana-soukromi` | `app_privacy` | Privacy policy | public |
| GET | `/turnaje` | `public_tournaments_list` | List public tournaments | public |
| GET | `/turnaje/{id}` | `public_tournament_detail` | Public tournament detail; 404 unless `TournamentVoter::VIEW` and not deleted | public + `TournamentVoter::VIEW` |
| GET | `/-/health-check/liveness` | `health_liveness` | JSON liveness probe | public |

### AUTH (PUBLIC_ACCESS prefixes)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET/POST | `/prihlaseni` | `app_login` | Login form (CSRF disabled, form_login check_path) | public |
| GET | `/registrace` | `app_register` | Registration (Live Component renders form) | public |
| — | `/odhlaseni` | `app_logout` | Logout (handled by firewall) | public |
| GET | `/overit-email` | `app_verify_email` | Confirm email-verification signed link | public |
| GET | `/overeni-ceka` | `app_verify_email_pending` | "Verification pending" page | public |
| POST | `/overeni-ceka/znovu-odeslat` | `app_resend_verification_email` | Resend verification email | public |
| GET | `/reset-hesla` | `app_forgot_password_request` | Request password reset (form) | public |
| GET | `/reset-hesla/email-odeslan` | `app_check_email` | "Reset email sent" confirmation | public |
| GET | `/reset-hesla/nove` | `app_reset_password_form` | New-password form | public |
| GET | `/reset-hesla/token/{token}` | `app_reset_password` | New-password form via token | public |

### INVITATION (PUBLIC_ACCESS — handles anonymous + authenticated)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET | `/pozvanka/{token}` (`[a-f0-9]{64}`) | `group_accept_invitation` | Land on an **email** invitation; if logged in → join (verification/email-mismatch gates), else show join/register form | public |
| GET | `/skupiny/pozvanka/{token}` (`[a-f0-9]{48}`) | `group_join_by_link` | Land on a **shareable-link** invitation; join or prompt register/login | public |

### PORTAL — Account / Profile (ROLE_USER, `^/portal`, `^/nastenka`)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET | `/nastenka` | `portal_dashboard` | Dashboard: my groups, owned tournaments, upcoming matches, recent evaluated guesses, discoverable public tournaments | ROLE_USER |
| GET | `/portal/profil` | `portal_profile_edit` | Edit profile page | `ProfileVoter::EDIT` (self) |
| GET/POST | `/portal/ucet/smazat` | `portal_account_delete` | Delete own account | `ProfileVoter::DELETE` (self) |

### PORTAL — Tournaments
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET/POST | `/portal/turnaje/vytvorit` | `portal_tournament_create` | Create a **private** tournament | ROLE_USER |
| GET | `/portal/turnaje/{id}` | `portal_tournament_detail` | Tournament detail | `TournamentVoter::VIEW` |
| GET/POST | `/portal/turnaje/{id}/upravit` | `portal_tournament_edit` | Edit tournament | `TournamentVoter::EDIT` (admin or active owner) |
| GET/POST | `/portal/turnaje/{id}/pravidla` | `portal_tournament_rule_configuration` | Configure scoring rules (enable/points) | `TournamentVoter::EDIT` |
| POST | `/portal/turnaje/{id}/ukoncit` | `portal_tournament_finish` | Mark tournament finished | `TournamentVoter::FINISH` |
| POST | `/portal/turnaje/{id}/smazat` | `portal_tournament_delete` | Soft-delete tournament | `TournamentVoter::DELETE` |

### PORTAL — Groups (pools)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET/POST | `/portal/turnaje/{tournamentId}/skupiny/novy` | `portal_group_create` | Create group in tournament (auto-join if `CREATE_GROUP` granted) | `TournamentVoter::VIEW` (+ `CREATE_GROUP` check) |
| GET | `/portal/skupiny/{id}` | `portal_group_detail` | Group detail: members, leaderboard summary, my tips, invites, join requests, rules | `GroupVoter::VIEW` |
| GET/POST | `/portal/skupiny/{id}/upravit` | `portal_group_edit` | Edit group (name, desc, hide-others-tips, tipsDeadline, PIN) | `GroupVoter::EDIT` |
| POST | `/portal/skupiny/{id}/smazat` | `portal_group_delete` | Soft-delete group | `GroupVoter::DELETE` |
| POST | `/portal/skupiny/{id}/opustit` | `portal_group_leave` | Leave group (non-owner members) | `GroupVoter::LEAVE` |
| GET/POST | `/portal/skupiny/{id}/moje-tipy` | `portal_group_my_tips_batch` | Batch-submit my tips for all open matches | `GroupVoter::VIEW` |
| GET/POST | `/portal/skupiny/{id}/spravovat-tipy` | `portal_group_manage_member_tips` | Manage tips on behalf of members | `GroupVoter::MANAGE_MEMBERS` |
| GET/POST | `/portal/skupiny/{id}/clenove/bez-emailu` | `portal_group_add_anonymous_member` | Add **anonymous** (proxy) member without email | `GroupVoter::MANAGE_MEMBERS` |
| GET/POST | `/portal/skupiny/{id}/clenove/{userId}/pridat-email` | `portal_group_promote_anonymous_member` | Promote anonymous member → invite by email | `GroupVoter::MANAGE_MEMBERS` |
| POST | `/portal/skupiny/{id}/clenove/{userId}/odebrat` | `portal_group_remove_member` | Remove member | `GroupVoter::MANAGE_MEMBERS` |
| POST | `/portal/skupiny/{id}/pin/novy` | `portal_group_pin_regenerate` | Regenerate join PIN | `GroupVoter::MANAGE_MEMBERS` |
| POST | `/portal/skupiny/{id}/pin/zrusit` | `portal_group_pin_revoke` | Revoke join PIN | `GroupVoter::MANAGE_MEMBERS` |
| POST | `/portal/skupiny/{id}/odkaz/novy` | `portal_group_link_regenerate` | Regenerate shareable link | `GroupVoter::MANAGE_MEMBERS` |
| POST | `/portal/skupiny/{id}/odkaz/zrusit` | `portal_group_link_revoke` | Revoke shareable link | `GroupVoter::MANAGE_MEMBERS` |
| POST | `/portal/skupiny/{id}/pozvanky/odeslat` | `portal_group_invitation_send` | Send single email invitation | `GroupVoter::INVITE_MEMBER` |
| POST | `/portal/skupiny/{id}/pozvanky/hromadne` | `portal_group_invitation_send_bulk` | Send bulk email invitations | `GroupVoter::MANAGE_MEMBERS` |
| POST | `/portal/skupiny/{id}/pozadat-o-pripojeni` | `portal_group_request_join` | Request to join a public group | `GroupVoter::REQUEST_JOIN` |
| POST | `/portal/pozvanky/{invitationId}/zrusit` | `portal_invitation_revoke` | Revoke a pending invitation | (in-handler check) |
| POST | `/portal/zadosti/{requestId}/schvalit` | `portal_join_request_approve` | Approve a join request | `GroupVoter::MANAGE_MEMBERS` (on request's group) |
| POST | `/portal/zadosti/{requestId}/zamitnout` | `portal_join_request_reject` | Reject a join request | `GroupVoter::MANAGE_MEMBERS` |
| GET/POST | `/pripojit` | `portal_group_join_by_pin` | Join group by PIN (form) | ROLE_USER (`^/pripojit`) |
| POST | `/pripojit/rychle` | `portal_group_join_by_pin_quick` | Quick PIN join (redirect) | ROLE_USER |

### PORTAL — Matches (per-group view + tip deadlines)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET | `/portal/skupiny/{groupId}/zapasy/{sportMatchId}` | `portal_group_sport_match_guesses` | Match detail within a group: members' guesses, my tip form | `GroupVoter::VIEW` + `SportMatchVoter::VIEW` + `GuessVoter::VIEW` |
| POST | `/portal/skupiny/{groupId}/zapasy/{sportMatchId}/uzaverka` | `portal_group_sport_match_set_deadline` | Set per-match tip deadline override for the group | `GroupVoter::EDIT` |

### PORTAL — Guesses (tips)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| POST | `/portal/skupiny/{groupId}/zapasy/{sportMatchId}/clenove/{memberId}/tip` | `portal_group_guess_on_behalf` | Submit/update a tip on behalf of a member | `GroupVoter::MANAGE_MEMBERS` + `GuessVoter::SUBMIT_ON_BEHALF`/`UPDATE_ON_BEHALF` |
| POST | `/portal/skupiny/{groupId}/spravovat-tipy/{memberId}` | `portal_group_guess_on_behalf_batch` | Batch-submit tips on behalf of one member | `GroupVoter::MANAGE_MEMBERS` + `GuessVoter::SUBMIT/UPDATE_ON_BEHALF` |

(Note: a member's own tip submission/update is funneled through `portal_group_my_tips_batch` and the match-guesses page; `GuessVoter::SUBMIT`/`UPDATE` are the underlying attributes.)

### PORTAL — Match administration (tournament owner/admin)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET | `/portal/zapasy/{id}` | `portal_sport_match_detail` | Match admin detail | `SportMatchVoter::VIEW` |
| GET/POST | `/portal/turnaje/{tournamentId}/zapasy/novy` | `portal_sport_match_create` | Create a match | `SportMatchVoter::CREATE` (on tournament) |
| GET/POST | `/portal/zapasy/{id}/upravit` | `portal_sport_match_edit` | Edit match (teams, kickoff, venue) | `SportMatchVoter::EDIT` |
| GET/POST | `/portal/zapasy/{id}/skore` | `portal_sport_match_set_score` | Set final score (triggers evaluation) | `SportMatchVoter::SET_SCORE` |
| POST | `/portal/zapasy/{id}/odlozit` | `portal_sport_match_postpone` | Postpone match (new kickoff) | `SportMatchVoter::EDIT` |
| POST | `/portal/zapasy/{id}/presunout` | `portal_sport_match_reschedule` | Reschedule a postponed match → Scheduled | `SportMatchVoter::EDIT` |
| POST | `/portal/zapasy/{id}/zrusit` | `portal_sport_match_cancel` | Cancel match | `SportMatchVoter::CANCEL` |
| POST | `/portal/zapasy/{id}/smazat` | `portal_sport_match_delete` | Soft-delete match | `SportMatchVoter::DELETE` |
| GET | `/portal/turnaje/{tournamentId}/zapasy/import` | `portal_sport_match_import` | Bulk-import preview (CSV upload) | `SportMatchVoter::CREATE` |
| POST | `/portal/turnaje/{tournamentId}/zapasy/import/potvrdit` | `portal_sport_match_import_commit` | Commit bulk import | `SportMatchVoter::CREATE` |
| GET | `/portal/turnaje/zapasy/sablona.csv` | `portal_sport_match_template_download` | Download CSV import template | ROLE_USER |

### PORTAL — Leaderboard (žebříček)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET | `/portal/skupiny/{groupId}/zebricek` | `portal_group_leaderboard` | Group leaderboard | `LeaderboardVoter::VIEW` |
| GET | `/portal/skupiny/{groupId}/zebricek/matice` | `portal_group_leaderboard_matrix` | Guess matrix (all members × all matches grid) | `LeaderboardVoter::VIEW` |
| GET | `/portal/skupiny/{groupId}/zebricek/clen/{userId}` | `portal_group_leaderboard_member` | Per-member point breakdown (per match, per rule) | `LeaderboardVoter::VIEW` |
| GET/POST | `/portal/skupiny/{groupId}/zebricek/shoda` | `portal_group_leaderboard_resolve_ties` | Manually order tied players (only when tournament finished) | `LeaderboardVoter::RESOLVE_TIES` (owner/admin + finished) |

### ADMIN (ROLE_ADMIN, `^/admin`)
| Method | Path | Route name | Purpose | Access |
|---|---|---|---|---|
| GET | `/admin/turnaje` | `admin_tournament_list` | List all tournaments | ROLE_ADMIN |
| GET/POST | `/admin/turnaje/vytvorit` | `admin_tournament_create` | Create a **public** tournament | ROLE_ADMIN |
| GET/POST | `/admin/turnaje/{id}/upravit` | `admin_tournament_edit` | Edit tournament | ROLE_ADMIN + `TournamentVoter::EDIT` |
| GET/POST | `/admin/turnaje/{id}/pravidla` | `admin_tournament_rule_configuration` | Configure rules | ROLE_ADMIN + `TournamentVoter::EDIT` |
| POST | `/admin/turnaje/{id}/ukoncit` | `admin_tournament_finish` | Mark finished | ROLE_ADMIN + `TournamentVoter::FINISH` |
| POST | `/admin/turnaje/{id}/smazat` | `admin_tournament_delete` | Soft-delete | ROLE_ADMIN + `TournamentVoter::DELETE` |
| GET | `/admin/turnaje/{tournamentId}/zapasy` | `admin_sport_match_list` | List a tournament's matches | ROLE_ADMIN |
| GET | `/admin/skupiny` | `admin_group_list` | List all groups | ROLE_ADMIN |
| POST | `/admin/skupiny/{id}/smazat` | `admin_group_delete` | Soft-delete group | ROLE_ADMIN + `GroupVoter::DELETE` |
| GET | `/admin/pravidla` | `admin_rule_list` | List registered scoring rules (global registry) | ROLE_ADMIN |
| GET | `/admin/uzivatele` | `admin_user_list` | List users | ROLE_ADMIN |
| POST | `/admin/uzivatele/{id}/zablokovat` | `admin_user_block` | Block (deactivate) user | `AdminUserManagementVoter::BLOCK` |
| POST | `/admin/uzivatele/{id}/odblokovat` | `admin_user_unblock` | Unblock user | `AdminUserManagementVoter::UNBLOCK` |

---

## 2. DOMAIN MODEL

### Core entities (`src/Entity/`)

**`Sport`** (table `sports`) — `id`, `code` (unique, 32), `name`. Constant `Sport::FOOTBALL_ID = '01960000-0000-7000-8000-000000000001'`. Currently only football is seeded; tournaments reference a Sport. No behavior.

**`User`** (table `users`, soft-deletable) — Implements `UserInterface`, `PasswordAuthenticatedUserInterface`, `EntityWithEvents`, `SoftDeletable`. Fields: `id`, `email` (nullable, partial-unique where not null), `password` (nullable), `nickname` (nullable, partial-unique), `roles` (array, always contains `ROLE_USER`), `isVerified`, `isActive`, `firstName`, `lastName`, `phone`, timestamps. Virtual props: `hasPassword`, **`isAnonymous` (`email === null`)**, `fullName`, `displayName` (nickname → fullName → "Uživatel"). `getUserIdentifier()` returns email or `anon:<uuid>` for anonymous users. Methods: `assignEmail` (one-time, for promoting anon members), `markAsVerified`, `changePassword`, `changeRole`, `activate`/`deactivate` (block/unblock), `updateProfile`, `softDelete`. **Anonymous user = a proxy player created by a group manager with no email/password — they can be tipped on behalf of, and later promoted by assigning an email.**

**`Tournament`** (table `tournaments`, soft-deletable) — The **soutěž**. Fields: `id`, `sport` (FK), `owner` (FK User), `visibility` (`TournamentVisibility` Public/Private), `name` (160), `description` (TEXT), `startAt`, `endAt`, `finishedAt` (nullable), `creationPin` (8, nullable — gates group creation), timestamps. Virtual: `isPublic`, `hasCreationPin`, `isFinished`, `isActive` (not finished & not deleted). Methods: `updateDetails`, `setCreationPin`, `markFinished` (throws `TournamentAlreadyFinished`), `recordRulesChanged`, `softDelete`. Public tournaments created by admins; private by any user. Events: `TournamentCreated/Updated/Finished/Deleted/RulesChanged`.

**`Group`** (table `user_groups`, soft-deletable) — The **pool/skupina** within a tournament. Fields: `id`, `tournament` (FK), `owner` (FK User), `name` (160), `description` (TEXT), `pin` (8, partial-unique), `shareableLinkToken` (48, partial-unique), `hideOthersTipsBeforeDeadline` (bool — privacy of tips), `tipsDeadline` (group-wide default deadline, nullable), timestamps. Virtual `isNotDeleted`. Methods: `updateDetails`, `setPin`/`revokePin`, `setShareableLinkToken`/`revokeShareableLinkToken`, `softDelete`. Events: `GroupCreated/Updated/Deleted/PinRegenerated/PinRevoked/ShareableLinkRegenerated/ShareableLinkRevoked`.

**`Membership`** (table `memberships`) — Join between User and Group. `id`, `group`, `user`, `joinedAt`, `leftAt` (nullable). Partial-unique `(group_id, user_id) where left_at IS NULL` (one active membership). Virtual `isActive`. Methods: `leave`, `removeBy(removedByUserId)`. Events: `MemberJoinedGroup/MemberLeftGroup/MemberRemoved`.

**`SportMatch`** (table `sport_matches`, soft-deletable) — A **zápas/výkop**. `id`, `tournament` (FK), `homeTeam` (120), `awayTeam` (120), `kickoffAt`, `venue` (nullable), `state` (`SportMatchState`), `homeScore`/`awayScore` (nullable), timestamps. Virtual: `isScheduled/isLive/isFinished/isPostponed/isCancelled`, **`isOpenForGuesses` (Scheduled & not deleted)**. State machine: Scheduled → Live (`beginLive`) / Postponed (`postponeTo`) / Finished (`setFinalScore`) / Cancelled (`cancel`); Postponed → Scheduled (`reschedule`); cannot edit/score when cancelled/deleted; `setFinalScore` validates non-negative, re-emits `SportMatchScoreUpdated` if already finished else `SportMatchFinished`. `softDelete`. Events: Created/Updated/Live/Finished/ScoreUpdated/Postponed/Cancelled/Deleted.

**`Guess`** (table `guesses`, soft-deletable) — A **tip**. `id`, `user` (FK), `sportMatch` (FK), `group` (FK), `homeScore`, `awayScore`, `submittedAt`, `updatedAt`, **`submittedBy` (nullable FK — set when entered on behalf by a manager)**. Partial-unique `(user_id, sport_match_id, group_id) where deleted_at IS NULL` (one active guess per user/match/group). Methods: `updateScores`, `voidGuess` (soft-delete). Events: `GuessSubmitted/GuessUpdated/GuessVoided`. **Same match guessed independently per group the user belongs to.**

**`GroupMatchSetting`** (table `group_match_settings`) — Per-(group, match) tip-**deadline override** (unique). `deadline`, timestamps, `updateDeadline`.

**`TournamentRuleConfiguration`** (table `tournament_rule_configurations`) — Per-(tournament, ruleIdentifier) scoring config (unique). `enabled` (bool), `points` (int override of rule default), timestamps. Methods `enable`/`disable`/`updatePoints`. Provisioned for every registered rule on tournament creation with `enabled=true, points=rule.defaultPoints`.

**`GuessEvaluation`** (table `guess_evaluations`) — Result of scoring one guess against a finished match. One-to-one with `Guess` (unique `guess_id`). `totalPoints` (denormalized sum), one-to-many `GuessEvaluationRulePoints`. `addRulePoints()` increments total.

**`GuessEvaluationRulePoints`** (table `guess_evaluation_rule_points`) — Per-rule points line: `ruleIdentifier` (64), `points`. Unique `(evaluation_id, rule_identifier)`. **This is the audit trail of which rules fired and for how many points.**

**`GroupInvitation`** (table `group_invitations`) — **Email invite**. `group`, `inviter`, `email` (180), `token` (64, unique), `createdAt`, `expiresAt`, `acceptedAt`/`revokedAt` (nullable). Virtual `isAccepted`/`isRevoked`; `isExpiredAt(now)`. Methods `accept(userId)` (throws on accepted/revoked/expired), `revoke`. Events: `GroupInvitationSent/Accepted/Revoked`.

**`GroupJoinRequest`** (table `group_join_requests`) — A user's **request to join** a public group. `group`, `user`, `requestedAt`, `decidedAt`/`decidedBy`/`decision` (`JoinRequestDecision` Approved/Rejected, nullable). Partial-unique `(group_id, user_id) where decided_at IS NULL` (one pending request). Virtual `isDecided/isApproved/isRejected`. Methods `approve(decidedBy)`, `reject(decidedBy)`. Events: `JoinRequestCreated/JoinRequestRejected`.

**`LeaderboardTieResolution`** (table `leaderboard_tie_resolutions`) — Manual **tie-break override**. Unique `(group_id, user_id)`. `rank` (the forced rank), `resolvedAt`, `resolvedBy`. No behavior (immutable record).

**`ResetPasswordRequest`** (table) — symfonycasts/reset-password support.

### Concerns / infrastructure
- `Concerns/SoftDeletable` (interface) + `Concerns/SoftDeletes` (trait, `deletedAt`, `markDeleted`) — pervasive soft-delete.
- `EntityWithEvents` (interface) + `HasEvents` (trait, `recordThat`) — domain-event recording; events dispatched on `event.bus`.

### Enums (`src/Enum/`)
- `UserRole`: `USER='ROLE_USER'`, `ADMIN='ROLE_ADMIN'` (labels "Uživatel"/"Administrátor").
- `TournamentVisibility`: `Public`/`Private` (labels "Veřejný"/"Soukromý").
- `SportMatchState`: `Scheduled`/`Live`/`Finished`/`Postponed`/`Cancelled`.
- `InvitationKind`: `Email`/`ShareableLink`.
- `JoinRequestDecision`: `Approved`/`Rejected`.

### Scoring rules (`src/Rule/`)
Rules implement the `Rule` interface (`identifier`, `label`, `description`, `defaultPoints`, `evaluate(): int` returns 0/1). Tagged `#[AsRule]`, collected by `RuleRegistry` (dedup by identifier). **Point policy lives in `TournamentRuleConfiguration`, not the rule.** Four registered rules:
| identifier | label | default pts | fires when |
|---|---|---|---|
| `exact_score` | Přesný výsledek | **5** | guess home & away both exactly match |
| `correct_outcome` | Správný tip výsledku | **3** | sign(guess diff) == sign(actual diff) — W/D/L correct |
| `correct_home_goals` | Počet gólů domácí | **1** | guess home == actual home |
| `correct_away_goals` | Počet gólů hosté | **1** | guess away == actual away |

---

### Core flow

**Tournament → Group → Membership → SportMatch → Guess → Evaluation → Leaderboard**

1. **Tournament** is created (private by any user via `portal_tournament_create`; public by admins via `admin_tournament_create`). On `TournamentCreated`, `TournamentRuleConfigurationProvisioner` idempotently provisions a config row per registered rule (enabled, default points).
2. The owner adds **SportMatches** (manually or via CSV bulk-import preview/commit), each with teams, kickoff, venue. Matches start `Scheduled`.
3. Players create or join **Groups** (pools) inside the tournament. Group creation may be gated by the tournament's `creationPin`. Each membership is a `Membership` row.
4. Within a group, members submit **Guesses** (tips) per match: home/away score. A guess is valid only while the match `isOpenForGuesses` and **before the effective deadline** resolved by `EffectiveTipDeadlineResolver`: per-(group,match) `GroupMatchSetting` override → group-wide `tipsDeadline` → match `kickoffAt`. One active guess per (user, match, group). Visibility of others' tips before deadline is controlled by `Group.hideOthersTipsBeforeDeadline`.
5. When the owner **sets the final score** (`setFinalScore`), the match becomes `Finished` and emits `SportMatchFinished`. `SportMatchFinishedHandler` loads all active guesses for the match and runs `GuessEvaluator`: for each enabled rule, if it fires, records a `GuessEvaluationRulePoints` line with the tournament's configured points; totals into a `GuessEvaluation`. Re-setting the score emits `SportMatchScoreUpdated`, and `SportMatchScoreUpdatedHandler` deletes & recomputes all evaluations. Voiding guesses / cancelling / deleting matches have their own handlers (`GuessVoidedHandler`, `SportMatchCancelledHandler`, `SportMatchDeletedHandler`). Changing rules emits `TournamentRulesChanged` → async recompute (`TournamentRulesChangedAsyncHandler`, `RecalculateTournamentPoints` command).
6. **Leaderboard** (`GetGroupLeaderboardQuery`): aggregates `SUM(GuessEvaluation.totalPoints)` grouped by user, joined to active members. Ranks descending by points; ties share a rank (standard competition ranking). Tie-break ordering within equal points falls back to nickname.
7. **Tie resolution**: when the tournament is finished, an owner/admin can manually order tied players (`portal_group_leaderboard_resolve_ties` → `ResolveLeaderboardTiesHandler`). It validates all supplied users share the same point total, computes the base rank, deletes prior resolutions for those users, and writes `LeaderboardTieResolution` rows with explicit sequential ranks. The leaderboard query then applies these overrides.

### Invitations / joining (four paths)
1. **Email invite** (`GroupInvitation`): owner/manager sends to an email (single `portal_group_invitation_send` or bulk `..._send_bulk`); recipient lands on `/pozvanka/{token}` → `InvitationContextResolver` computes status (Active/Revoked/Accepted/Expired/TournamentFinished); `InvitationAcceptanceService` joins authenticated users (verifying email implicitly if invite targets their address, with email-mismatch detection), or shows a register/login form for guests (intent stored in session). Can be revoked (`portal_invitation_revoke`).
2. **Shareable link** (`Group.shareableLinkToken`, 48 hex): owner regenerates/revokes; anyone with the link hits `/skupiny/pozvanka/{token}` → join (verification gate applies for unverified users). 
3. **PIN join** (`Group.pin`, 8 chars): `/pripojit` form or `/pripojit/rychle` quick-join via `JoinGroupByPin`.
4. **Join request** (`GroupJoinRequest`, public groups only): a verified user requests via `portal_group_request_join`; owner/manager approves/rejects (`portal_join_request_approve`/`reject`).
5. **Anonymous (proxy) members**: a group manager adds a member with no email (`add_anonymous_member` → `CreateAnonymousMember`, creates an anonymous `User`). The manager submits tips on their behalf (`guess_on_behalf` / `guess_on_behalf_batch`, sets `Guess.submittedBy`). Later they can be promoted by assigning an email (`promote_anonymous_member` → `PromoteAnonymousMember`, sends an invite).

### Rule configuration & tie resolution
- **Rule config** UI (`portal_tournament_rule_configuration` / admin variant): per tournament, toggle each rule on/off and override its point value, persisted as `TournamentRuleConfiguration`. Editing triggers async recalculation of all evaluated guesses.
- **Tie resolution** described above (only post-finish, owner/admin).

---

## 3. CAPABILITY INVENTORY

**Public / guest**
- Browse public tournaments list and public tournament detail.
- View privacy policy and home landing.
- Land on email/shareable-link invitations; register or log in to accept.
- Register, log in, log out; verify email (with resend); request and complete password reset.

**Authenticated player**
- Dashboard: see my groups, owned tournaments, upcoming matches, recently evaluated guesses, discoverable public tournaments.
- Edit own profile (first/last name, phone); delete own account.
- Create a **private** tournament; view tournaments I can access.
- Create a group within a tournament (subject to creation-PIN/visibility); view group detail.
- Join a group via: PIN (form or quick-join), shareable link, email invitation, or join-request (public groups).
- Leave a group (non-owner).
- Submit and update my **tips** per match within a group (batch "my tips" page or per-match page), while open and before the deadline.
- View per-group leaderboard, guess matrix (all members × matches), and per-member point breakdown.
- View match detail within a group and others' tips (respecting hide-before-deadline).

**Group owner / manager**
- Edit group (name, description, hide-others-tips toggle, group-wide tips deadline, PIN).
- Soft-delete group.
- Manage join access: regenerate/revoke PIN, regenerate/revoke shareable link.
- Send single and bulk email invitations; revoke pending invitations.
- Approve/reject join requests (public groups).
- Add anonymous (proxy) members; promote them by assigning an email/invite; remove members.
- Enter/update tips **on behalf of** members (per-match and batch).
- Set per-match tip-deadline overrides for the group.
- Resolve leaderboard ties manually (after tournament finishes).

**Tournament owner**
- Edit tournament (name, description, dates, creation PIN).
- Configure scoring rules (enable/disable, point values).
- Create, edit, postpone, reschedule, cancel, soft-delete matches; bulk-import matches via CSV (preview + commit) with downloadable template.
- Set final scores (triggering automatic evaluation).
- Mark tournament finished; soft-delete tournament.

**Admin (ROLE_ADMIN)**
- Create **public** tournaments.
- List/edit/finish/delete any tournament; configure any tournament's rules; list a tournament's matches.
- List/delete any group.
- List globally registered scoring rules.
- List users; block/unblock users.
- `switch_user` impersonation.
- Inherits all owner/player capabilities (voters grant admin override broadly).

---

## 4. GLOSSARY MAPPING (design-system terms → current app)

| Glossary term | Maps to in current app | Notes |
|---|---|---|
| **soutěž / pool** | Ambiguous in this codebase. "Pool" most closely maps to **`Group`** (table `user_groups`) — the per-tournament competition unit with its own members, leaderboard, PIN, and link. "Soutěž" could also map to **`Tournament`**. The app distinguishes **Tournament (turnaj)** = the schedule of matches + rules, and **Group (skupina)** = the social pool that competes on those matches. The glossary's single "soutěž/pool" concept is **split across two entities** here. |
| **skupina / group** | **`Group`** entity, routes under `/portal/skupiny`, voter `GroupVoter`. Fully present. |
| **tip** | **`Guess`** entity ("tip" is used throughout UI: `moje-tipy`, `spravovat-tipy`, "Byl(a) jsi přidán(a)"). Fully present. |
| **výkop / kickoff** | **`SportMatch.kickoffAt`** (and default tip deadline). Present as a field; no dedicated "kickoff" surface beyond match scheduling. |
| **žebříček / leaderboard** | **Leaderboard** routes `/portal/skupiny/{id}/zebricek`, `…/matice`, `…/clen/{userId}`, `…/shoda`; `GetGroupLeaderboardQuery`, `LeaderboardTieResolution`. Fully present, incl. guess matrix and per-member breakdown. |
| **pavouk / bracket** | **MISSING.** There is no knockout/bracket structure. Matches are a flat list per tournament (`SportMatch` with no round/stage/parent-match linkage, no advancement). The two untracked CSVs in the repo root (`ms-hokej-2026-qf.csv`, `ms-hokej-2026-sf.csv` — quarter-final / semi-final) hint at bracket-style data being imported as flat matches, but the domain has **no bracket concept**. |
| **výplata / payout** | **MISSING.** No payout, prize, stake, money, or settlement concept anywhere in the entities, enums, commands, or routes. Purely points-based, no financial/prize layer. |

**Other notable gaps vs. a generic "pools" design system:**
- **Multi-sport in practice is stubbed** — only football is seeded (`Sport::FOOTBALL_ID`); `Sport` exists but there's no sport-management UI.
- **No match rounds/stages/groups-within-tournament** (no group stage vs. knockout modeling) — flat match list only.
- **No notifications/feed** beyond email (invitations, verification, password reset) and dashboard lists; domain events exist but drive emails/recalc, not an in-app activity feed.
- **No public group browsing** — only public *tournaments* are publicly listed; groups are reached via invite/PIN/link/request only.