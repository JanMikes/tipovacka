<?php

declare(strict_types=1);

namespace App\Command\InviteToGlobalCompetition;

use App\Exception\CompetitionIsNotGlobal;
use App\Repository\CompetitionRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mails the public invitation page of a global competition.
 *
 * There is no `CompetitionInvitation` row and no pre-provisioned Membership here, unlike
 * the private path — that pre-provisioning is what lets an organizer tip on an invitee's
 * behalf, and doing it for a paid competition would be handing out a fee-free seat. So the
 * only thing this command produces is the e-mail; the recipient joins (and pays) through
 * {@see \App\Controller\Invitation\JoinGlobalCompetitionInviteController} like anybody who
 * found the competition on the public list.
 *
 * Consequently there is nothing to revoke, nothing to expire and nothing to accept — and
 * no reason to send the mail through a domain event, since no transaction has to commit
 * first for it to be truthful.
 */
#[AsMessageHandler]
final readonly class InviteToGlobalCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(InviteToGlobalCompetitionCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $inviter = $this->userRepository->get($command->inviterId);

        if (!$competition->isGlobal) {
            throw CompetitionIsNotGlobal::withId($competition->id);
        }

        $invitationUrl = $this->urlGenerator->generate(
            'competition_global_invitation',
            ['id' => $competition->id->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->mailer->send(
            (new TemplatedEmail())
                ->to(strtolower(trim($command->email)))
                ->subject('Pozvánka do soutěže na Tipovačce')
                ->htmlTemplate('emails/global_competition_invitation.html.twig')
                ->context([
                    'inviterNickname' => $inviter->displayName,
                    'competitionName' => $competition->name,
                    'matchSourceName' => $competition->headlineSource->name,
                    'entryFeeCredits' => $competition->entryFeeCredits,
                    'invitationUrl' => $invitationUrl,
                ])
        );
    }
}
