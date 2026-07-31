<?php

declare(strict_types=1);

namespace App\Command\CreateTeam;

use App\Entity\Team;
use App\Repository\SportRepository;
use App\Repository\TeamRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateTeamHandler
{
    public function __construct(
        private TeamRepository $teamRepository,
        private SportRepository $sportRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateTeamCommand $command): Team
    {
        $sport = $this->sportRepository->get($command->sportId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $team = new Team(
            id: $this->identity->next(),
            sport: $sport,
            matchSource: null,
            name: $command->name,
            createdAt: $now,
            shortName: $command->shortName,
            country: null !== $command->country ? mb_strtoupper($command->country) : null,
            brandColor: null !== $command->brandColor ? mb_strtoupper($command->brandColor) : null,
            logo: $command->logo,
        );

        $this->teamRepository->save($team);

        return $team;
    }
}
