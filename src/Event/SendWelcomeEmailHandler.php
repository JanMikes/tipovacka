<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class SendWelcomeEmailHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(EmailVerified $event): void
    {
        $user = $this->userRepository->get($event->userId);

        if (null === $user->email) {
            return;
        }

        $loginUrl = $this->urlGenerator->generate(
            'app_login',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@tipovacka.cz', 'Tipovačka'))
            ->to(new Address($user->email, $user->displayName))
            ->subject('Vítejte v Tipovačce!')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context([
                'nickname' => $user->displayName,
                'loginUrl' => $loginUrl,
            ]);

        $this->mailer->send($email);
    }
}
