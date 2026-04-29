<?php

declare(strict_types=1);

namespace App\Command\AcceptGroupInvitation;

use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Repository\GroupInvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AcceptGroupInvitationHandler
{
    public function __construct(
        private GroupInvitationRepository $invitationRepository,
        private MembershipRepository $membershipRepository,
        private UserRepository $userRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AcceptGroupInvitationCommand $command): GroupInvitation
    {
        $invitation = $this->invitationRepository->getByToken($command->token);
        $user = $this->userRepository->get($command->userId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $invitation->accept($user->id, $now);

        // Receiving the invitation in the user's mailbox is itself proof of email
        // ownership, so accepting one targeted at their address verifies the account.
        if (!$user->isVerified
            && null !== $user->email
            && 0 === strcasecmp($user->email, $invitation->email)
        ) {
            $user->markAsVerified($now);
        }

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
