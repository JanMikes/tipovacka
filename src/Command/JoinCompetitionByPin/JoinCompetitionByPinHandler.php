<?php

declare(strict_types=1);

namespace App\Command\JoinCompetitionByPin;

use App\Entity\Competition;
use App\Entity\Membership;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JoinCompetitionByPinHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private UserRepository $userRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(JoinCompetitionByPinCommand $command): Competition
    {
        $competition = $this->competitionRepository->getByPin($command->pin);
        $user = $this->userRepository->get($command->userId);

        if ($competition->matchSource->isCompleted) {
            throw CannotJoinFinishedMatchSource::forCompetition($competition->id);
        }

        if ($this->membershipRepository->hasActiveMembership($user->id, $competition->id)) {
            throw AlreadyMember::in($competition->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $membership = new Membership(
            id: $this->identity->next(),
            competition: $competition,
            user: $user,
            joinedAt: $now,
        );

        $this->membershipRepository->save($membership);

        return $competition;
    }
}
