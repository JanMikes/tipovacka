<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\GroupInvitationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class SendGroupInvitationEmailHandler
{
    public function __construct(
        private GroupInvitationRepository $invitationRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(GroupInvitationSent $event): void
    {
        $invitation = $this->invitationRepository->get($event->invitationId);

        $invitationUrl = $this->urlGenerator->generate(
            'group_accept_invitation',
            ['token' => $event->token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@tipovacka.cz', 'Tipovačka'))
            ->to($event->email)
            ->subject('Pozvánka do skupiny na Tipovačce')
            ->htmlTemplate('emails/group_invitation.html.twig')
            ->context([
                'inviterNickname' => $invitation->inviter->displayName,
                'groupName' => $invitation->group->name,
                'tournamentName' => $invitation->group->tournament->name,
                'invitationUrl' => $invitationUrl,
                'expiresAt' => $invitation->expiresAt,
            ]);

        $this->mailer->send($email);
    }
}
