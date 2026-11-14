<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationSettingsRepository;
use App\Value\EmailDigest;
use App\Value\EmailDigestSection;
use App\Value\EmailItem;
use App\Value\UnsubscribeScope;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends the two notification mails — one notification on its own
 * (`emails/notification.html.twig`) and many folded into one
 * (`emails/digest.html.twig`) — and is the ONLY place that knows what makes a
 * notification mail different from account mail:
 *
 *  - the `List-Unsubscribe` + `List-Unsubscribe-Post` headers (RFC 8058), which
 *    put a real „Odhlásit" button into Gmail / Seznam / Apple Mail. Gmail and
 *    Yahoo expect it on anything a recipient can opt out of, and a button that
 *    works beats the „spam" button sitting next to it;
 *  - the footer that says WHY the mail came and links to the no-login
 *    unsubscribe page and to the settings.
 *
 * Account mail (verification, password reset, receipts, invitations) is sent
 * by its own handlers and deliberately carries neither — there is nothing to
 * opt out of.
 *
 * It decides nothing about WHETHER to mail; that is
 * {@see NotificationPolicy}'s job, done before anything gets here.
 */
final readonly class NotificationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailPreferenceLinks $links,
        private NotificationSettingsRepository $settingsRepository,
    ) {
    }

    /**
     * @param string|null     $url   host-relative path the button leads to
     * @param list<EmailItem> $items an optional list under the text (a soutěž per row, …)
     */
    public function sendNotification(
        User $user,
        NotificationType $type,
        string $title,
        string $body,
        ?string $url = null,
        array $items = [],
    ): void {
        $scope = UnsubscribeScope::type($type);

        $this->send($user, $title, $scope, 'emails/notification.html.twig', [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'items' => $items,
            'type' => $type,
            'unsubscribeUrl' => $this->links->manageUrl($user, $scope),
            'settingsUrl' => $this->links->manageUrl($user),
        ]);
    }

    public function sendDigest(User $user, EmailDigest $digest): void
    {
        $this->send($user, $digest->subject, $digest->unsubscribeScope, 'emails/digest.html.twig', [
            'digest' => $digest,
            // The „ztlumit tuto soutěž" link of every section that has a soutěž.
            'muteUrls' => array_map(
                fn (EmailDigestSection $section): ?string => null !== $section->competitionId
                    ? $this->links->manageUrl($user, UnsubscribeScope::competition($section->competitionId))
                    : null,
                $digest->sections,
            ),
            'digestHour' => NotificationPolicy::digestHourOf($this->settingsRepository->findForUser($user->id)),
            'unsubscribeUrl' => $this->links->manageUrl($user, $digest->unsubscribeScope),
            'settingsUrl' => $this->links->manageUrl($user),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(User $user, string $subject, UnsubscribeScope $scope, string $template, array $context): void
    {
        if (null === $user->email || '' === $user->email) {
            return;
        }

        // From header comes from the mailer config (MAILER_FROM_*), not set here.
        $message = (new TemplatedEmail())
            ->to(new Address($user->email, $user->displayName))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        // RFC 8058 one-click: the mail client POSTs to this URL with no cookies
        // and no confirmation step. Both headers must be DKIM-signed (the relay
        // signs all of them) and the target must be HTTPS in production.
        $message->getHeaders()
            ->addTextHeader('List-Unsubscribe', sprintf('<%s>', $this->links->oneClickUrl($user, $scope)))
            ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $this->mailer->send($message);
    }
}
