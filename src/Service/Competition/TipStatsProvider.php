<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMonetization;
use App\Query\GetPickDistributions\GetPickDistributions;
use App\Query\QueryBus;
use App\Repository\CreditWalletRepository;
use App\Value\TipStats;
use Symfony\Component\Uid\Uuid;

/**
 * Resolves the „Rozložení tipů" surface for a whole page in a BOUNDED number of
 * queries, whatever the number of matches × competitions on it:
 *
 *   1 × distributions (batched)  +  1 × boost ownership (batched)  +  1 × wallet.
 *
 * Every match list in the portal renders this, so a per-match resolve would be a
 * textbook N+1 — always go through {@see forPairs} (or {@see forCompetition}),
 * never through the single-match query in a loop.
 *
 * WHETHER the split is readable is not decided here: {@see TipVisibilityGate}
 * owns that one rule for every surface (entitled, or the match has a result).
 */
final readonly class TipStatsProvider
{
    public function __construct(
        private QueryBus $queryBus,
        private CompetitionEntitlements $entitlements,
        private TipVisibilityGate $visibilityGate,
        private CreditWalletRepository $walletRepository,
    ) {
    }

    /**
     * @param list<SportMatch> $matches
     *
     * @return array<string, TipStats> keyed by sport match id RFC4122
     */
    public function forCompetition(Competition $competition, array $matches, ?User $viewer): array
    {
        $stats = $this->forPairs([[$competition, $matches]], $viewer);
        $byMatch = [];

        foreach ($matches as $match) {
            $key = $this->key($competition->id, $match->id);

            if (isset($stats[$key])) {
                $byMatch[$match->id->toRfc4122()] = $stats[$key];
            }
        }

        return $byMatch;
    }

    /**
     * @param list<array{0: Competition, 1: list<SportMatch>}> $competitionMatches
     *
     * @return array<string, TipStats> keyed by "competitionId:matchId"
     */
    public function forPairs(array $competitionMatches, ?User $viewer): array
    {
        if (0 === count($competitionMatches)) {
            return [];
        }

        $competitionIds = [];
        $matchIds = [];

        foreach ($competitionMatches as [$competition, $matches]) {
            $competitionIds[$competition->id->toRfc4122()] = $competition->id;

            foreach ($matches as $match) {
                $matchIds[$match->id->toRfc4122()] = $match->id;
            }
        }

        $distributions = $this->queryBus->handle(new GetPickDistributions(
            competitionIds: array_values($competitionIds),
            sportMatchIds: array_values($matchIds),
        ));

        // Warm both per-viewer lookups once: boost ownership across every
        // competition on the page, and the wallet the paywall quotes.
        $balance = 0;

        if (null !== $viewer) {
            $this->entitlements->preload($viewer->id, array_values($competitionIds));
            $balance = $this->walletRepository->findByUserId($viewer->id)->balance ?? 0;
        }

        $result = [];

        foreach ($competitionMatches as [$competition, $matches]) {
            $entitled = null !== $viewer && $this->entitlements->isEntitledToDistribution($competition, $viewer);
            // One rule, one home: entitled, or the match has a final result. Batched
            // per competition so a page stays O(competitions).
            $visibleByMatch = $this->visibilityGate->distributionVisibleByMatch($competition, $viewer, $matches);

            foreach ($matches as $match) {
                $visible = $visibleByMatch[$match->id->toRfc4122()] ?? false;
                $distribution = $distributions->for($competition->id, $match->id);

                $result[$this->key($competition->id, $match->id)] = new TipStats(
                    competitionId: $competition->id,
                    competitionName: $competition->name,
                    monetization: $competition->monetization,
                    visible: $visible,
                    entitled: $entitled,
                    total: $distribution->total,
                    homeWinPercent: $visible ? $distribution->homeWinPercent : 0,
                    drawPercent: $visible ? $distribution->drawPercent : 0,
                    awayWinPercent: $visible ? $distribution->awayWinPercent : 0,
                    homeWinCount: $visible ? $distribution->homeWinCount : 0,
                    drawCount: $visible ? $distribution->drawCount : 0,
                    awayWinCount: $visible ? $distribution->awayWinCount : 0,
                    purchasable: !$visible
                        && null !== $viewer
                        && CompetitionMonetization::Boosts === $competition->monetization,
                    price: BoostType::TipDistribution->price(),
                    balance: $balance,
                );
            }
        }

        return $result;
    }

    public function key(Uuid $competitionId, Uuid $sportMatchId): string
    {
        return $competitionId->toRfc4122().':'.$sportMatchId->toRfc4122();
    }
}
