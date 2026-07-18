# S11 — Notification center

**Goal**: in-app bell + feed + per-user preferences + email channel, fed by domain events
and a scheduled reminder sweep. Communication is the product's key trust feature.

## Domain

- `Enum\NotificationType` (string-backed, Czech `label()` + `description()` for the
  preferences page + `defaultInApp()`/`defaultEmail()`):
  `guess_reminder` (email default ON), `match_added`, `match_evaluated`,
  `competition_ended` (email ON), `premium_balance_low` (email ON),
  `premium_charge_uncovered` (email ON), `premium_downgraded` (email ON),
  `premium_enabled`, `boost_refunded` (email ON), `member_joined` (managers only).
- Entity `Notification` — `id`, `user` FK, `type`, `title` string(160), `body` TEXT,
  `?string url`, `competition` FK nullable, `?array payload` (JSON), `readAt` nullable,
  `createdAt`. Index `(user_id, read_at, created_at)`. Content is pre-rendered Czech at
  creation (title/body/url) — no runtime template resolution for old rows.
- Entity `NotificationPreference` — `id`, `user` FK, `type`, `inApp` bool, `email` bool,
  `updatedAt`; unique `(user_id, type)`. Missing row = type defaults.
- `Service\Notification\Notifier` — single entry point:
  `notify(User $user, NotificationType $type, title, body, ?url, ?Competition, ?payload,
  ?string $dedupKey = null)`: checks preference; persists in-app row (if inApp);
  sends email (if email && user has email) via a shared dark-brand template
  (`emails/notification.html.twig` with CTA button); dedupKey guards repeats
  (payload-stored, checked per user+type+key, e.g. reminders per match-day, balance-low
  per competition+day).
- Event handlers (event.bus, each thin, composing `Notifier`):
  - `SportMatchCreated` (source has started competitions in mode All incl. playoff rules)
    → `match_added` to members: „Do soutěže {name} přibyl zápas {home}–{away} ({date})".
  - Evaluation completion (from S06's evaluation flow — emit `GuessEvaluatedForUser` per
    evaluation batch) → `match_evaluated`: „{home} {score} {away}: získáváš {points} b.,
    jsi {rank}. v {competition}" (rank from leaderboard query post-evaluation).
  - `MatchSourceCompleted` + all-matches-evaluated check per competition →
    `competition_ended` to every member: final rank + points (+ winner congratulation
    variant); fires once (`Competition.endedNotifiedAt` guard or dedupKey).
  - Premium/boost events from S10 → respective types (manager or affected buyer).
  - `MemberJoinedCompetition` → `member_joined` to the manager (skip global? no — managers
    of global = admin, keep but preference-off by default? keep default ON, admin can
    disable).
- **Reminder sweep** (scheduler, hourly): for each user × competition, find matches with
  effective deadline within the next 24 h (resolver!) missing a guess → ONE notification
  per user+competition+deadline-day (dedupKey) „V soutěži {name} ti chybí tip na {n}
  zápasů, uzávěrka {time}". Runs via `SendGuessRemindersCommand` (also manually
  invokable).

## UX

- Nav bell (`icon-btn` + unread `icon-dot`, styleguide §C): dropdown with latest 5 +
  „Vše" link; badge = unread count (Twig component querying count; Live Component with
  60 s poll on the dropdown only).
- `/portal/oznameni` — the center: feed (unread highlighted, relative Czech dates,
  per-item mark-read on click-through; „Označit vše jako přečtené"), and a
  **Nastavení oznámení** tab: matrix of type × (V aplikaci / E-mail) toggles (Live
  Component, instant save). Types listed with human descriptions.
- Emails: one shared notification template; respect existing dark brand.

## Tests

- `Notifier` unit/integration: preference respect (in-app off, email off, both), dedup,
  email-less users, pre-rendered content.
- Handler integration per type (match added → members notified; evaluated → correct
  points/rank in body; ended fires once; premium events).
- Reminder sweep: fixture matrix (missing vs present guesses, deadline windows, dedup on
  second run, preference off).
- Flow: center renders, mark-read, preference toggle persists, bell badge count.

## Acceptance

- [ ] Every DOMAIN.md notification type delivered per preferences; center + bell live.
- [ ] Reminder sweep scheduled and idempotent.
- [ ] Quality gate green.
