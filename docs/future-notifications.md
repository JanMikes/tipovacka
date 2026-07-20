# Notifications — implemented

> This file used to be a *roadmap* of deferred listeners written against the pre-rebuild
> `Tournament`/`Group`/`JoinRequest` domain. That backlog is obsolete: the notification
> system was **built** in stage S11. This is now a pointer, not a plan.

**Authoritative behavior:** [`.docs/DOMAIN.md` → §Notifications](../.docs/DOMAIN.md) and the
Decision-log rows dated 2026-07-18 (in-app + email, per-type × channel preferences) and
2026-07-19 (delivery-level, channel-agnostic dedup).

## What shipped

- **Entities:** `Notification` (bell + center feed, `inAppVisible` stamped per the user's
  in-app preference) and `NotificationPreference` (per user × `NotificationType` × channel).
- **Service:** `App\Service\Notification\Notifier` — writes one `Notification` row whenever it
  delivers on any channel, honoring the user's per-type in-app/email preferences. Email
  defaults on only for the important types (guess reminder, the three premium problems,
  competition ended, boost refunded); in-app defaults on for all.
- **Types** (`App\Enum\NotificationType`): guess reminder, new match added after start, match
  evaluated (your points + standing), competition ended (final standing), premium
  low-balance / uncovered / downgraded / re-enabled, boost refunded, nový hráč se připojil
  do soutěže, kterou spravujete (member_joined — in-app default on / email off, skipped when
  the joiner is the owner).
- **Delivery:** event-driven via the `event.bus` → messenger `async` transport. `competition_ended`
  fires only when the source is completed **and** every included match is finished+evaluated,
  guarded once by `endedNotifiedAt` (a source reopen clears the guard + deletes sent rows so a
  corrected standing re-sends).
- **Scheduled sweeps** run on the prod worker via **symfony/scheduler** (`scheduler_default`
  transport): the guess-reminder sweep (`SendGuessReminders`, missing tips with deadline
  < 24 h), premium reconciliation (`ReconcilePremiumCompetitions`) and the daily leaderboard
  snapshots (`CaptureDailyLeaderboardSnapshots`).

Transactional account/auth emails (verification, welcome, password reset, competition
invitation) remain plain `event.bus` handlers routing through `symfony/mailer` →
`SendEmailMessage` → `async`. In dev, mail lands in Mailpit at <http://localhost:8025>.
