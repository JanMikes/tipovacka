<?php

declare(strict_types=1);

namespace App\Query\ListBrowsableCompetitions;

use App\Enum\CompetitionStateFilter;
use Symfony\Component\Uid\Uuid;

/**
 * One competition card. Field names are deliberately shared with the older
 * discovery item shape so every existing card template keeps rendering.
 *
 * Money is an entry FEE, never a prize pool: entry fees are burned credits and
 * there are no payouts (.docs/DOMAIN.md), which is also why the design's
 * „VÝHERNÍ BANK" hero card was dropped.
 */
/* Not a `readonly class`: the virtual properties below are hooked, and hooked
   properties may not be readonly — the data is immutable per-property instead. */
final class BrowsableCompetitionItem
{
    public function __construct(
        public readonly Uuid $competitionId,
        public readonly string $name,
        public readonly Uuid $sportId,
        public readonly string $sportName,
        public readonly string $matchSourceName,
        public readonly ?\DateTimeImmutable $sourceStartAt,
        public readonly ?\DateTimeImmutable $sourceEndAt,
        public readonly int $entryFeeCredits,
        public readonly int $playerCount,
        public readonly int $matchCount,
        public readonly int $startedMatchCount,
        public readonly int $finishedMatchCount,
        public readonly int $liveMatchCount,
        public readonly bool $isGlobal,
        public readonly bool $sourceIsCompleted,
        public readonly bool $viewerIsMember,
        public readonly bool $viewerIsOwner,
    ) {
    }

    /** „% dokončeno" — settled share of the competition's own matches. */
    public int $progressPercent {
        get => 0 === $this->matchCount
            ? 0
            : (int) round($this->finishedMatchCount / $this->matchCount * 100);
    }

    public bool $isFinished {
        get => $this->sourceIsCompleted
            || ($this->matchCount > 0 && $this->finishedMatchCount === $this->matchCount);
    }

    public CompetitionStateFilter $state {
        get => match (true) {
            $this->isFinished => CompetitionStateFilter::Finished,
            0 === $this->startedMatchCount => CompetitionStateFilter::Upcoming,
            default => CompetitionStateFilter::Running,
        };
    }
}
