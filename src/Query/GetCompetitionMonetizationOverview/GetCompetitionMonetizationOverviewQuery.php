<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionMonetizationOverview;

use App\Entity\BoostPurchase;
use App\Entity\CompetitionPremiumCharge;
use App\Enum\PremiumChargeStatus;
use App\Repository\BoostPurchaseRepository;
use App\Repository\CompetitionPremiumChargeRepository;
use App\Repository\CompetitionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionMonetizationOverviewQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionPremiumChargeRepository $chargeRepository,
        private BoostPurchaseRepository $boostRepository,
    ) {
    }

    public function __invoke(GetCompetitionMonetizationOverview $query): CompetitionMonetizationOverviewResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);

        $charges = $this->chargeRepository->findAllForCompetition($competition->id);
        $chargedCount = 0;
        $uncoveredCount = 0;
        $chargedCredits = 0;
        foreach ($charges as $charge) {
            if (PremiumChargeStatus::Charged === $charge->status) {
                ++$chargedCount;
                $chargedCredits += $charge->amount;
            } elseif (PremiumChargeStatus::Uncovered === $charge->status) {
                ++$uncoveredCount;
            }
        }

        $boosts = $this->boostRepository->listActiveByCompetition($competition->id);
        $boostCredits = 0;
        foreach ($boosts as $boost) {
            $boostCredits += $boost->pricePaid;
        }

        return new CompetitionMonetizationOverviewResult(
            monetization: $competition->monetization,
            premiumCharges: array_map(
                static fn (CompetitionPremiumCharge $c): PremiumChargeRow => new PremiumChargeRow(
                    memberName: $c->member->displayName,
                    amount: $c->amount,
                    status: $c->status,
                    createdAt: $c->createdAt,
                ),
                $charges,
            ),
            chargedCount: $chargedCount,
            uncoveredCount: $uncoveredCount,
            chargedCredits: $chargedCredits,
            activeBoosts: array_map(
                static fn (BoostPurchase $b): ActiveBoostRow => new ActiveBoostRow(
                    userName: $b->user->displayName,
                    boostLabel: $b->type->label(),
                    pricePaid: $b->pricePaid,
                    purchasedAt: $b->purchasedAt,
                ),
                $boosts,
            ),
            boostCredits: $boostCredits,
        );
    }
}
