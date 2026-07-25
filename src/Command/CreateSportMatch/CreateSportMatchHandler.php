<?php

declare(strict_types=1);

namespace App\Command\CreateSportMatch;

use App\Entity\SportMatch;
use App\Repository\MatchSourceRepository;
use App\Repository\SportMatchRepository;
use App\Service\Identity\ProvideIdentity;
use App\Service\Team\TeamResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateSportMatchHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private MatchSourceRepository $matchSourceRepository,
        private TeamResolver $teamResolver,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateSportMatchCommand $command): SportMatch
    {
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $sportMatch = new SportMatch(
            id: $this->identity->next(),
            matchSource: $matchSource,
            homeTeam: $this->teamResolver->resolve($matchSource, $command->homeTeam, $now),
            awayTeam: $this->teamResolver->resolve($matchSource, $command->awayTeam, $now),
            kickoffAt: $command->kickoffAt,
            venue: $command->venue,
            createdAt: $now,
            round: $command->round,
            isPlayoff: $command->isPlayoff,
        );

        $this->sportMatchRepository->save($sportMatch);

        return $sportMatch;
    }
}
