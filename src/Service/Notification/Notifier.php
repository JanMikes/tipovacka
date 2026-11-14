<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Competition;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\EmailPlan;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use App\Service\Identity\ProvideIdentity;
use App\Value\EmailItem;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * THE single entry point for delivering a notification. It asks
 * {@see NotificationPolicy} what this notification does for this player — in-app
 * or not, and whether its mail goes now, waits for a digest, or does not exist —
 * and then, whenever ANYTHING is delivered:
 *
 *  - writes ONE pre-rendered {@see Notification} row (no flush — callers run
 *    inside a bus handler whose `doctrine_transaction` middleware flushes on
 *    success). The row's `inAppVisible` flag = the in-app half of the decision,
 *    so the bell / center only ever show in-app rows while an e-mail-only
 *    delivery still leaves an (invisible) row for dedup and for the digest;
 *  - sends the mail at once ({@see EmailPlan::SendNow}) through
 *    {@see NotificationMailer}, or marks the row as owing one
 *    ({@see EmailPlan::Queue}) for
 *    {@see \App\Command\SendEmailDigests\SendEmailDigestsHandler} to fold into a
 *    single mail.
 *
 * `$dedupKey` makes a delivery idempotent: a second `notify()` with the same
 * `(user, type, dedupKey)` is dropped (backed by the `dedup_key` column). Because
 * the row is written whenever ANY channel delivers, the guard is fully
 * channel-agnostic — a user with in-app OFF + email ON no longer re-receives the
 * email on every sweep (the historical email-spam hole).
 *
 * `$additionalDedupKeys` lets ONE aggregated delivery stand in for several
 * logical reminders (the guess reminder spanning many competitions): each
 * extra key leaves an invisible dedup-only row, so a later `notify()` carrying
 * that key — as primary or as marker — is dropped exactly as if it had been
 * delivered on its own. Markers are written only when the delivery itself
 * happened; a fully-muted user keeps no marks and loses nothing.
 *
 * When the decision is silent (both channels off, a muted soutěž, e-mail wanted
 * but no address and in-app off) nothing is delivered and no row is written —
 * there is nothing to dedup.
 *
 * `$url` is a host-RELATIVE path (`/soutez/…`), never an absolute URL: the in-app
 * click-through stays on whatever host the user is browsing, so a wrong origin can
 * never send the bell off-domain. The mail template — the one place a host is
 * required — absolutizes it with `absolute_url()`, which falls back to the
 * router's `default_uri` (env `APP_URL`) when there is no request (cron, worker).
 *
 * A notification is a side effect and must never break the triggering command:
 * every failure is caught and logged, never rethrown.
 */
final readonly class Notifier
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private NotificationPolicy $policy,
        private NotificationMailer $notificationMailer,
        private ProvideIdentity $identityProvider,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string|null                     $url                 host-RELATIVE path (see the class doc)
     * @param array<string, scalar|null>|null $payload
     * @param list<string>                    $additionalDedupKeys
     * @param string|null                     $emailBody           what a directly sent mail says instead of $body —
     *                                                             for a notification whose mail lists $emailItems
     *                                                             where the feed row spells them out in its text
     * @param list<EmailItem>                 $emailItems          rows a directly sent mail lists under its text
     */
    public function notify(
        User $user,
        NotificationType $type,
        string $title,
        string $body,
        ?string $url = null,
        ?Competition $competition = null,
        ?array $payload = null,
        ?string $dedupKey = null,
        array $additionalDedupKeys = [],
        ?string $emailBody = null,
        array $emailItems = [],
    ): void {
        try {
            if (null !== $dedupKey && $this->notificationRepository->existsForDedup($user->id, $type, $dedupKey)) {
                return;
            }

            $decision = $this->policy->decide($user, $type, $competition);

            // Nothing to deliver on any channel ⇒ nothing to write, nothing to dedup.
            if ($decision->isSilent) {
                return;
            }

            $now = \DateTimeImmutable::createFromInterface($this->clock->now());

            // One row per delivery regardless of channel — the dedup marker is
            // delivery-level, not in-app-level. `inAppVisible` keeps email-only
            // rows out of the feed while still guarding against email repeats.
            $this->notificationRepository->save(new Notification(
                id: $this->identityProvider->next(),
                user: $user,
                type: $type,
                title: $title,
                body: $body,
                competition: $competition,
                createdAt: $now,
                url: $url,
                payload: $payload,
                dedupKey: $dedupKey,
                inAppVisible: $decision->inApp,
                emailQueued: EmailPlan::Queue === $decision->email,
            ));

            if (EmailPlan::SendNow === $decision->email) {
                $this->notificationMailer->sendNotification($user, $type, $title, $emailBody ?? $body, $url, $emailItems);
            }

            foreach ($additionalDedupKeys as $markerKey) {
                if ($markerKey === $dedupKey || $this->notificationRepository->existsForDedup($user->id, $type, $markerKey)) {
                    continue;
                }

                $this->notificationRepository->save(new Notification(
                    id: $this->identityProvider->next(),
                    user: $user,
                    type: $type,
                    title: $title,
                    body: $body,
                    competition: $competition,
                    createdAt: $now,
                    url: $url,
                    payload: null,
                    dedupKey: $markerKey,
                    inAppVisible: false,
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->error('Notification delivery failed', [
                'notificationType' => $type->value,
                'userId' => $user->id->toRfc4122(),
                'exception' => $e,
            ]);
        }
    }
}
