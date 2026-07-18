<?php

declare(strict_types=1);

namespace App\Command\SendCompetitionInvitation;

use App\Entity\CompetitionInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\CompetitionInvitationRepository;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\InvitationTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use App\Service\User\UserNicknameGenerator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendCompetitionInvitationHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionInvitationRepository $invitationRepository,
        private MembershipRepository $membershipRepository,
        private InvitationTokenGenerator $tokenGenerator,
        private UserNicknameGenerator $nicknameGenerator,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendCompetitionInvitationCommand $command): CompetitionInvitation
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $inviter = $this->userRepository->get($command->inviterId);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $expiresAt = $now->modify('+7 days');
        $normalisedEmail = strtolower(trim($command->email));

        // Provision a stub user and active membership immediately so the competition manager
        // can submit guesses on the invitee's behalf before they accept the invitation.
        $invitee = $this->userRepository->findByEmail($normalisedEmail)
            ?? $this->createStubUser($normalisedEmail, $now);

        if (!$this->membershipRepository->hasActiveMembership($invitee->id, $competition->id)) {
            $this->membershipRepository->save(new Membership(
                id: $this->identity->next(),
                competition: $competition,
                user: $invitee,
                joinedAt: $now,
            ));
        }

        $invitation = new CompetitionInvitation(
            id: $this->identity->next(),
            competition: $competition,
            inviter: $inviter,
            email: $normalisedEmail,
            token: $this->tokenGenerator->generate(),
            createdAt: $now,
            expiresAt: $expiresAt,
        );

        $this->invitationRepository->save($invitation);

        return $invitation;
    }

    private function createStubUser(string $email, \DateTimeImmutable $now): User
    {
        $user = new User(
            id: $this->identity->next(),
            email: $email,
            password: null,
            nickname: $this->nicknameGenerator->forEmail($email),
            createdAt: $now,
        );

        $this->userRepository->save($user);

        return $user;
    }
}
