<?php

declare(strict_types=1);

namespace App\Command\UpdateSportMatch;

use App\Exception\SportMatchTeamsLocked;
use App\Repository\GuessScorerRepository;
use App\Repository\MatchEventRepository;
use App\Repository\SportMatchRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateSportMatchHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private MatchEventRepository $matchEventRepository,
        private GuessScorerRepository $guessScorerRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateSportMatchCommand $command): void
    {
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);

        // Players in the source roster pool are keyed by team NAME — renaming a
        // team on a match that already has recorded events or standing scorer
        // tips would silently split the roster (old rows point at the old name).
        // Minimal v1 guard: block the rename until those rows are removed.
        $renamesHomeTeam = null !== $command->homeTeam && $command->homeTeam !== $sportMatch->homeTeam;
        $renamesAwayTeam = null !== $command->awayTeam && $command->awayTeam !== $sportMatch->awayTeam;

        if (($renamesHomeTeam || $renamesAwayTeam)
            && ($this->matchEventRepository->countByMatch($sportMatch->id) > 0
                || $this->guessScorerRepository->countByMatch($sportMatch->id) > 0)
        ) {
            throw SportMatchTeamsLocked::create();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $sportMatch->updateDetails(
            homeTeam: $command->homeTeam,
            awayTeam: $command->awayTeam,
            kickoffAt: $command->kickoffAt,
            venue: $command->venue,
            now: $now,
            round: $command->round,
            isPlayoff: $command->isPlayoff,
        );
    }
}
