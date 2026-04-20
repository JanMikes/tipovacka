<?php

declare(strict_types=1);

namespace App\Command\CompleteInvitationRegistration;

use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Exception\InvitationAlreadyRegistered;
use App\Exception\UserNotFound;
use App\Repository\GroupInvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler]
final readonly class CompleteInvitationRegistrationHandler
{
    public function __construct(
        private GroupInvitationRepository $invitationRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CompleteInvitationRegistrationCommand $command): GroupInvitation
    {
        $invitation = $this->invitationRepository->getByToken($command->token);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $user = $this->userRepository->findByEmail($invitation->email)
            ?? throw UserNotFound::withEmail($invitation->email);

        if ($user->hasPassword) {
            throw InvitationAlreadyRegistered::create();
        }

        $hashed = $this->passwordHasher->hashPassword($user, $command->plainPassword);
        $user->changePassword($hashed, $now);

        if (!$user->isVerified) {
            $user->markAsVerified($now);
        }

        $invitation->accept($user->id, $now);

        if (!$this->membershipRepository->hasActiveMembership($user->id, $invitation->group->id)) {
            $membership = new Membership(
                id: $this->identity->next(),
                group: $invitation->group,
                user: $user,
                joinedAt: $now,
            );

            $this->membershipRepository->save($membership);
        }

        return $invitation;
    }
}
