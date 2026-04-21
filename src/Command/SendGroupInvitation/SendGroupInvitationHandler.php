<?php

declare(strict_types=1);

namespace App\Command\SendGroupInvitation;

use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\GroupInvitationRepository;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Group\InvitationTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use App\Service\User\UserNicknameGenerator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendGroupInvitationHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
        private GroupInvitationRepository $invitationRepository,
        private MembershipRepository $membershipRepository,
        private InvitationTokenGenerator $tokenGenerator,
        private UserNicknameGenerator $nicknameGenerator,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendGroupInvitationCommand $command): GroupInvitation
    {
        $group = $this->groupRepository->get($command->groupId);
        $inviter = $this->userRepository->get($command->inviterId);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $expiresAt = $now->modify('+7 days');
        $normalisedEmail = strtolower(trim($command->email));

        // Provision a stub user and active membership immediately so the group manager
        // can submit guesses on the invitee's behalf before they accept the invitation.
        $invitee = $this->userRepository->findByEmail($normalisedEmail)
            ?? $this->createStubUser($normalisedEmail, $now);

        if (!$this->membershipRepository->hasActiveMembership($invitee->id, $group->id)) {
            $this->membershipRepository->save(new Membership(
                id: $this->identity->next(),
                group: $group,
                user: $invitee,
                joinedAt: $now,
            ));
        }

        $invitation = new GroupInvitation(
            id: $this->identity->next(),
            group: $group,
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
