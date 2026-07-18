<?php

declare(strict_types=1);

namespace App\Command\RequestToJoinCompetition;

use App\Entity\CompetitionJoinRequest;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Exception\DuplicatePendingJoinRequest;
use App\Exception\JoinRequestNotAllowed;
use App\Repository\CompetitionJoinRequestRepository;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RequestToJoinCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private CompetitionJoinRequestRepository $joinRequestRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestToJoinCompetitionCommand $command): CompetitionJoinRequest
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $user = $this->userRepository->get($command->userId);

        if (!$competition->matchSource->isPublic) {
            throw JoinRequestNotAllowed::privateMatchSource();
        }

        if ($competition->matchSource->isFinished) {
            throw CannotJoinFinishedMatchSource::forCompetition($competition->id);
        }

        if ($this->membershipRepository->hasActiveMembership($user->id, $competition->id)) {
            throw AlreadyMember::in($competition->id);
        }

        if ($this->joinRequestRepository->hasPendingFor($user->id, $competition->id)) {
            throw DuplicatePendingJoinRequest::forCompetition($competition->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $request = new CompetitionJoinRequest(
            id: $this->identity->next(),
            competition: $competition,
            user: $user,
            requestedAt: $now,
        );

        $this->joinRequestRepository->save($request);

        return $request;
    }
}
