<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class SendRegistrationInviteEmailHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(PasswordResetRequestedForUnregisteredEmail $event): void
    {
        $registerUrl = $this->urlGenerator->generate(
            'app_register',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->to($event->email)
            ->subject('Váš účet jsme nenašli — Tipovačka')
            ->htmlTemplate('emails/registration_invite.html.twig')
            ->context([
                'registerUrl' => $registerUrl,
                'userEmail' => $event->email,
            ]);

        $this->mailer->send($email);
    }
}
