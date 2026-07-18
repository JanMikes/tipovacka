<?php

declare(strict_types=1);

namespace App\Command\ApproveJoinRequest;

use App\Entity\Membership;
use App\Event\JoinRequestApproved;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Repository\CompetitionJoinRequestRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApproveJoinRequestHandler
{
    public function __construct(
        private CompetitionJoinRequestRepository $joinRequestRepository,
        private MembershipRepository $membershipRepository,
        private UserRepository $userRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ApproveJoinRequestCommand $command): void
    {
        $request = $this->joinRequestRepository->get($command->requestId);
        $approver = $this->userRepository->get($command->ownerId);

        if ($request->competition->matchSource->isFinished) {
            throw CannotJoinFinishedMatchSource::forCompetition($request->competition->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $request->approve($approver, $now);

        $membershipId = $this->identity->next();

        if (!$this->membershipRepository->hasActiveMembership($request->user->id, $request->competition->id)) {
            $membership = new Membership(
                id: $membershipId,
                competition: $request->competition,
                user: $request->user,
                joinedAt: $now,
            );

            $this->membershipRepository->save($membership);
        }

        $request->recordThat(new JoinRequestApproved(
            requestId: $request->id,
            membershipId: $membershipId,
            competitionId: $request->competition->id,
            userId: $request->user->id,
            occurredOn: $now,
        ));
    }
}
