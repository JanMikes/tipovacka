<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\CompetitionInvitationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class SendCompetitionInvitationEmailHandler
{
    public function __construct(
        private CompetitionInvitationRepository $invitationRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(CompetitionInvitationSent $event): void
    {
        $invitation = $this->invitationRepository->get($event->invitationId);

        $invitationUrl = $this->urlGenerator->generate(
            'competition_accept_invitation',
            ['token' => $event->token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->to($event->email)
            ->subject('Pozvánka do soutěže na Tipovačce')
            ->htmlTemplate('emails/competition_invitation.html.twig')
            ->context([
                'inviterNickname' => $invitation->inviter->displayName,
                'competitionName' => $invitation->competition->name,
                'matchSourceName' => $invitation->competition->matchSource->name,
                'invitationUrl' => $invitationUrl,
                'expiresAt' => $invitation->expiresAt,
            ]);

        $this->mailer->send($email);
    }
}
