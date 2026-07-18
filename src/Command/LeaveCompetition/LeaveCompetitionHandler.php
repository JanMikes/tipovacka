<?php

declare(strict_types=1);

namespace App\Command\LeaveCompetition;

use App\Exception\CannotLeaveAsOwner;
use App\Exception\NotAMember;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LeaveCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(LeaveCompetitionCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);

        if ($competition->owner->id->equals($command->userId)) {
            throw CannotLeaveAsOwner::of($competition->id);
        }

        $membership = $this->membershipRepository->findActiveMembership($command->userId, $competition->id);

        if (null === $membership) {
            throw NotAMember::of($competition->id);
        }

        $membership->leave(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
