<?php

declare(strict_types=1);

namespace App\Command\PromoteAnonymousMember;

use App\Entity\CompetitionInvitation;
use App\Enum\UserRole;
use App\Exception\UserAlreadyExists;
use App\Exception\UserAlreadyPromoted;
use App\Repository\CompetitionInvitationRepository;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\InvitationTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler]
final readonly class PromoteAnonymousMemberHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private CompetitionInvitationRepository $invitationRepository,
        private InvitationTokenGenerator $tokenGenerator,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PromoteAnonymousMemberCommand $command): CompetitionInvitation
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $actor = $this->userRepository->get($command->actorId);
        $target = $this->userRepository->get($command->userId);

        $isAdmin = in_array(UserRole::ADMIN->value, $actor->getRoles(), true);

        if (!$isAdmin && !$actor->id->equals($competition->owner->id)) {
            throw new AccessDeniedException('Pouze vlastník soutěže nebo administrátor může přidat e-mail tipujícímu.');
        }

        if (!$this->membershipRepository->hasActiveMembership($target->id, $competition->id)) {
            throw new AccessDeniedException('Tipující není členem této soutěže.');
        }

        if (!$target->isAnonymous) {
            throw UserAlreadyPromoted::forUser($target->displayName);
        }

        $normalisedEmail = strtolower(trim($command->email));

        if (null !== $this->userRepository->findByEmail($normalisedEmail)) {
            throw UserAlreadyExists::withEmail($normalisedEmail);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $target->assignEmail($normalisedEmail, $now);

        $invitation = new CompetitionInvitation(
            id: $this->identity->next(),
            competition: $competition,
            inviter: $actor,
            email: $normalisedEmail,
            token: $this->tokenGenerator->generate(),
            createdAt: $now,
            expiresAt: $now->modify('+7 days'),
        );

        $this->invitationRepository->save($invitation);

        return $invitation;
    }
}
