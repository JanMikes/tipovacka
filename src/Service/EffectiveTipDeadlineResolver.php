<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\SportMatch;
use App\Repository\GroupMatchSettingRepository;

final readonly class EffectiveTipDeadlineResolver
{
    public function __construct(
        private GroupMatchSettingRepository $overrideRepository,
    ) {
    }

    public function resolve(Group $group, SportMatch $sportMatch): \DateTimeImmutable
    {
        $override = $this->overrideRepository->findByGroupAndMatch($group->id, $sportMatch->id);

        if (null !== $override) {
            return $override->deadline;
        }

        return $group->tipsDeadline ?? $sportMatch->kickoffAt;
    }

    /**
     * @param list<SportMatch> $matches
     *
     * @return array<string, \DateTimeImmutable> keyed by sport match id RFC4122
     */
    public function resolveMany(Group $group, array $matches): array
    {
        if ([] === $matches) {
            return [];
        }

        $matchIds = array_map(static fn (SportMatch $m) => $m->id, $matches);
        $overrides = $this->overrideRepository->findByGroupAndMatches($group->id, $matchIds);

        $result = [];

        foreach ($matches as $match) {
            $key = $match->id->toRfc4122();
            $override = $overrides[$key] ?? null;

            if (null !== $override) {
                $result[$key] = $override->deadline;
            } else {
                $result[$key] = $group->tipsDeadline ?? $match->kickoffAt;
            }
        }

        return $result;
    }
}
