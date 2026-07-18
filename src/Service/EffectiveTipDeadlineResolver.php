<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Repository\CompetitionMatchSettingRepository;

final readonly class EffectiveTipDeadlineResolver
{
    public function __construct(
        private CompetitionMatchSettingRepository $overrideRepository,
    ) {
    }

    public function resolve(Competition $competition, SportMatch $sportMatch): \DateTimeImmutable
    {
        $override = $this->overrideRepository->findByCompetitionAndMatch($competition->id, $sportMatch->id);

        if (null !== $override) {
            return $override->deadline;
        }

        return $competition->tipsDeadline ?? $sportMatch->kickoffAt;
    }

    /**
     * @param list<SportMatch> $matches
     *
     * @return array<string, \DateTimeImmutable> keyed by sport match id RFC4122
     */
    public function resolveMany(Competition $competition, array $matches): array
    {
        if ([] === $matches) {
            return [];
        }

        $matchIds = array_map(static fn (SportMatch $m) => $m->id, $matches);
        $overrides = $this->overrideRepository->findByCompetitionAndMatches($competition->id, $matchIds);

        $result = [];

        foreach ($matches as $match) {
            $key = $match->id->toRfc4122();
            $override = $overrides[$key] ?? null;

            if (null !== $override) {
                $result[$key] = $override->deadline;
            } else {
                $result[$key] = $competition->tipsDeadline ?? $match->kickoffAt;
            }
        }

        return $result;
    }
}
