<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Competition;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * THE single entry point for delivering a notification. Resolves the user's
 * per-type channel preference (an explicit {@see \App\Entity\NotificationPreference}
 * row, else the {@see NotificationType} defaults), then, whenever it delivers on
 * ANY enabled channel:
 *
 *  - writes ONE pre-rendered {@see Notification} row (no flush — callers run
 *    inside a bus handler whose `doctrine_transaction` middleware flushes on
 *    success). The row's `inAppVisible` flag = the user's in-app preference, so
 *    the bell / center only ever show in-app rows while an email-only delivery
 *    still leaves an (invisible) dedup row;
 *  - sends a dark-brand email (one shared `emails/notification.html.twig`) when the
 *    email channel is on and the user actually has an email address.
 *
 * `$dedupKey` makes a delivery idempotent: a second `notify()` with the same
 * `(user, type, dedupKey)` is dropped (backed by the `dedup_key` column). Because
 * the dedup row is written whenever ANY channel delivers, the guard is fully
 * channel-agnostic — a user with in-app OFF + email ON no longer re-receives the
 * email on every sweep (the historical email-spam hole).
 *
 * When BOTH channels are off (or email is on but the user has no address and
 * in-app is off) nothing is delivered and no row is written — there is nothing to
 * dedup.
 *
 * A notification is a side effect and must never break the triggering command:
 * every failure is caught and logged, never rethrown.
 */
final readonly class Notifier
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private NotificationPreferenceRepository $preferenceRepository,
        private ProvideIdentity $identityProvider,
        private MailerInterface $mailer,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, scalar|null>|null $payload
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
    ): void {
        try {
            if (null !== $dedupKey && $this->notificationRepository->existsForDedup($user->id, $type, $dedupKey)) {
                return;
            }

            $preference = $this->preferenceRepository->findOne($user->id, $type);
            $wantsInApp = null !== $preference ? $preference->inApp : $type->defaultInApp();
            $wantsEmail = null !== $preference ? $preference->email : $type->defaultEmail();

            $emailAddress = null !== $user->email && '' !== $user->email ? $user->email : null;
            $deliverInApp = $wantsInApp;
            $deliverEmail = $wantsEmail && null !== $emailAddress;

            // Nothing to deliver on any channel ⇒ nothing to write, nothing to dedup.
            if (!$deliverInApp && !$deliverEmail) {
                return;
            }

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
                createdAt: \DateTimeImmutable::createFromInterface($this->clock->now()),
                url: $url,
                payload: $payload,
                dedupKey: $dedupKey,
                inAppVisible: $deliverInApp,
            ));

            if (null !== $emailAddress && $deliverEmail) {
                $this->sendEmail($emailAddress, $user->displayName, $type, $title, $body, $url);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Notification delivery failed', [
                'notificationType' => $type->value,
                'userId' => $user->id->toRfc4122(),
                'exception' => $e,
            ]);
        }
    }

    private function sendEmail(
        string $email,
        string $displayName,
        NotificationType $type,
        string $title,
        string $body,
        ?string $url,
    ): void {
        // From header comes from the mailer config (MAILER_DSN sender), not set here.
        $message = (new TemplatedEmail())
            ->to(new Address($email, $displayName))
            ->subject($title)
            ->htmlTemplate('emails/notification.html.twig')
            ->context([
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'type' => $type,
            ]);

        $this->mailer->send($message);
    }
}
