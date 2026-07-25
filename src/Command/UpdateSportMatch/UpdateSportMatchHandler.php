<?php

declare(strict_types=1);

namespace App\Command\UpdateSportMatch;

use App\Entity\MatchSource;
use App\Entity\Team;
use App\Exception\SportMatchTeamsLocked;
use App\Repository\GuessScorerRepository;
use App\Repository\MatchEventRepository;
use App\Repository\SportMatchRepository;
use App\Service\Team\TeamResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateSportMatchHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private MatchEventRepository $matchEventRepository,
        private GuessScorerRepository $guessScorerRepository,
        private TeamResolver $teamResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateSportMatchCommand $command): void
    {
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);
        $source = $sportMatch->matchSource;

        // Recorded scorers/cards point at Players of the match's CURRENT teams.
        // Reassigning the match to a DIFFERENT team would orphan those rows, so
        // block it once such rows exist. (Fixing a typo is a rename in the team
        // directory — the FK stays stable — not a match edit, so it's untouched.)
        $reassignsHome = null !== $command->homeTeam
            && !$this->keepsSameTeam($source, $sportMatch->homeTeam, $command->homeTeam);
        $reassignsAway = null !== $command->awayTeam
            && !$this->keepsSameTeam($source, $sportMatch->awayTeam, $command->awayTeam);

        if (($reassignsHome || $reassignsAway)
            && ($this->matchEventRepository->countByMatch($sportMatch->id) > 0
                || $this->guessScorerRepository->countByMatch($sportMatch->id) > 0)
        ) {
            throw SportMatchTeamsLocked::create();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $sportMatch->updateDetails(
            homeTeam: null !== $command->homeTeam
                ? $this->teamResolver->resolve($source, $command->homeTeam, $now)
                : null,
            awayTeam: null !== $command->awayTeam
                ? $this->teamResolver->resolve($source, $command->awayTeam, $now)
                : null,
            kickoffAt: $command->kickoffAt,
            venue: $command->venue,
            now: $now,
            round: $command->round,
            isPlayoff: $command->isPlayoff,
        );
    }

    /** Does the submitted name still resolve to the match's current team (find-only, never creates)? */
    private function keepsSameTeam(MatchSource $source, Team $current, string $name): bool
    {
        $existing = $this->teamResolver->findExisting($source, $name);

        return null !== $existing && $existing->id->equals($current->id);
    }
}
