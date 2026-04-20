<?php

declare(strict_types=1);

namespace App\Command\CreateSportMatch;

use App\Entity\SportMatch;
use App\Repository\SportMatchRepository;
use App\Repository\TournamentRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateSportMatchHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private TournamentRepository $tournamentRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateSportMatchCommand $command): SportMatch
    {
        $tournament = $this->tournamentRepository->get($command->tournamentId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $sportMatch = new SportMatch(
            id: $this->identity->next(),
            tournament: $tournament,
            homeTeam: $command->homeTeam,
            awayTeam: $command->awayTeam,
            kickoffAt: $command->kickoffAt,
            venue: $command->venue,
            createdAt: $now,
        );

        $this->sportMatchRepository->save($sportMatch);

        return $sportMatch;
    }
}
