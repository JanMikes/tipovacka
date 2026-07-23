<?php

declare(strict_types=1);

namespace App\Query\GetPickDistributions;

use App\Query\GetMatchPickDistribution\MatchPickDistributionResult;
use Symfony\Component\Uid\Uuid;

/**
 * Distribution lookup keyed by (competition, match). A pair nobody has tipped is
 * absent from the map and answers with an empty result, so callers never branch
 * on „missing" vs „zero tips".
 */
final readonly class PickDistributions
{
    /**
     * @param array<string, MatchPickDistributionResult> $byPair keyed by {@see self::key}
     */
    public function __construct(
        private array $byPair,
    ) {
    }

    public static function key(Uuid $competitionId, Uuid $sportMatchId): string
    {
        return $competitionId->toRfc4122().':'.$sportMatchId->toRfc4122();
    }

    public function for(Uuid $competitionId, Uuid $sportMatchId): MatchPickDistributionResult
    {
        return $this->byPair[self::key($competitionId, $sportMatchId)] ?? MatchPickDistributionResult::empty();
    }
}
