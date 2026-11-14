# E-maily — one layout, and a way out of every notification

Two things live here: **how a mail is built** (so the next one looks right in every client)
and **how notification mail is throttled and left** (frequency, mute, unsubscribe).
Business rules and their rationale are in [DOMAIN.md §Notifications](../DOMAIN.md).

## See a mail

`/admin/e-maily` renders every mail with sample data, at desktop and phone width. The samples
live in `Service/Email/EmailPreviews` — add an entry when you add a mail (a test renders them
all, so a template that stops rendering fails CI rather than a user's inbox).

## Building a mail

Extend `emails/layout.html.twig` and fill four blocks:

```twig
{% extends 'emails/layout.html.twig' %}
{% import 'emails/_ui.html.twig' as ui %}

{% block title %}Nastavte si nové heslo{% endblock %}
{% block preheader %}The inbox preview line — say what the mail is, not „Dobrý den".{% endblock %}

{% block content %}
    <p class="eyebrow" style="{{ st.eyebrow }}">Zabezpečení účtu</p>
    <h1 class="h1" style="{{ st.h1 }}">Nastavte si nové heslo</h1>
    <p class="p" style="{{ st.p }}">…</p>
    {{ ui.button('Nastavit nové heslo', resetUrl) }}
    {{ ui.linkFallback(resetUrl) }}
{% endblock %}

{% block footer_reason %}…why the recipient got it…{% endblock %}
```

Rules, each one the answer to a real client:

- **Every element carries BOTH `style="{{ st.x }}"` and `class="x"`.** The inline style is
  what renders (the Gmail app drops the whole `<style>` block for non-Google accounts); the
  class is only the hook the dark palette overrides. A class without the inline style is
  invisible to half the inboxes; an inline style without the class stays light in dark mode.
- **Light by default.** Do not design a dark mail: Gmail on iOS and Outlook on Windows invert
  it into a washed-out light one and cannot be told not to. The dark look comes from the
  `prefers-color-scheme` block in the layout (Apple Mail, Outlook for Mac) and the
  `[data-ogsc]` / `[data-ogsb]` twins (Outlook.com).
- **No web fonts, no background images, no SVG.** System font stack; the logo is
  `public/images/email/logo.png`, a PNG with its navy tile baked in so it survives any
  background. It is deliberately NOT an AssetMapper asset — a mail outlives a deploy, so its
  image URL must never change. (Regenerate it from `assets/images/logo/logo-wtips.svg`.)
- **Links in the body are absolute.** Pass host-relative paths and wrap them in
  `absolute_url()` in the template; outside a request the host comes from `APP_URL`.
- **The button is `ui.button()`**, a padded `<a>` in a coloured `<td>`. No VML.
- **No text template needed.** `Service/Email/MailTextConverter` (wired as Twig's
  `mailer.html_to_text_converter`) derives the plain-text part and keeps every link as
  „text: URL". Symfony's default would be `strip_tags`, which loses them all.
- **Context must survive serialization.** Mail goes through Messenger: the template is
  rendered once when dispatched (errors surface there) and AGAIN in the worker from the
  serialized context. Pass scalars, enums and readonly value objects — never entities.
- In tests read the rendered mail with `self::getMailerMessages()`; what sits on the async
  transport is still the unrendered template.

Markup structure is adapted from Cerberus „fluid" and Postmark's transactional templates
(both MIT). `twig/cssinliner-extra` was considered and rejected: a dozen templates do not
justify a production dependency that re-serializes the document.

## Notification mail: who gets what, when

Only `Notifier` → `NotificationPolicy` → `NotificationMailer` send notification mail. A handler
never mails a notification itself and never decides whether to.

| Player's frequency | Urgent (money) | Standard | Burst (evaluated, added, joined) | Live (tip reminder) |
|---|---|---|---|---|
| **jednou denně** (default) | now | daily summary | daily summary | recomputed in the summary |
| **průběžně** | now | now | one mail per hour | now (hourly reminder sweep) |
| **žádné e-maily** | — | — | — | — |

- A **muted soutěž** is silent on both channels (urgent types excepted) and absent from
  reminders.
- „Queued" means `Notification.emailQueuedAt` is set; `app:match-digests:send` (alias
  `app:email-digests:send`, hourly at :10) folds each player's pending rows into one mail and
  settles them. It re-checks the player's CURRENT preferences first — a row queued before an
  unsubscribe is settled without a mail.
- The **daily summary** goes out at most once per Prague day, at or after
  `NotificationSettings.digestHour`. Missing tips are computed at send time with a 36 h
  look-ahead. A day with nothing to say sends nothing and stays „due".
- A new type needs: its `emailTiming()`, `category()`, `actionLabel()` in `NotificationType`.
  Nothing else — the policy, the settings matrix and the unsubscribe page follow the enum.

## Leaving

Every notification mail carries, via `NotificationMailer`:

- a footer link to **`/e-maily/{token}?co={scope}`** (`EmailPreferencesController`) — works
  with no login; `scope` is `typ-<type>`, `soutez-<uuid>` or `vse`. A GET only asks (mail
  scanners follow links); one button POSTs it; the result page offers the undo, „raději jen
  jeden souhrn denně" and „odhlásit všechny".
- **`List-Unsubscribe` + `List-Unsubscribe-Post: List-Unsubscribe=One-Click`** (RFC 8058) →
  `OneClickUnsubscribeController`, which unsubscribes on a bare POST and answers 200. For the
  daily summary the scope is `vse`: leaving the summary means leaving notification e-mail.

The token (`EmailPreferenceLinks`) is the user id + an HMAC keyed off `APP_SECRET`. It never
expires and it is not a login: the pages behind it can only turn e-mail DOWN. Rotating
`APP_SECRET` invalidates every link already in an inbox.

**Operational requirements** (outside this repo): the relay's DKIM signature must cover the
`List-Unsubscribe` and `List-Unsubscribe-Post` headers, and `APP_URL` must be the public
HTTPS origin — Gmail ignores a one-click target that is not HTTPS.

## The audit log — „Odeslané e-maily"

`/admin/odeslane-e-maily` lists every attempt to send a mail: when, to whom (and whose account
that is), the subject, which mail it was, whether the relay took it, and — for an accepted
one — what it said. Admin-only (`^/admin`); it holds other people's addresses.

- Written by `Service/Email/EmailLogRecorder`, a subscriber on the mailer's own events, so
  every mail is covered and **no sender logs anything itself**. A mail queued but not yet
  consumed has no row; a mail that fails and is retried has one row per attempt.
- **Sent** rows are written on `SentMessageEvent`, inside the worker's transaction.
  **Failed** rows are written on Messenger's `WorkerMessageFailedEvent`, i.e. *after* the
  rollback — the mailer's own failure event fires inside the transaction that is about to be
  rolled back and would lose the row. That handler is the one place outside fixtures that
  calls `flush()`.
- **„Sent" means accepted by the relay, not delivered.** A bounce arrives later, in the
  `robot@` mailbox, and is not known here.
- The stored text is the plain-text part run through `EmailBodyRedactor`: query strings are
  dropped and any path segment of 20+ characters that is not a UUID is masked. The rule is
  generic on purpose — a reset, verification, invitation or unsubscribe link is a credential,
  and a mail added later is covered without anyone listing it as sensitive.
- **Retention: 90 days** (`EmailLog::RETENTION_DAYS`). `app:match-digests:send` prunes the log
  after its sweep, so the log needs no crontab entry of its own.
- In a test, a mail only reaches the log once the queue is consumed — dispatch the queued
  `SendEmailMessage` with a `ReceivedStamp` (see `EmailLogRecorderTest`).

