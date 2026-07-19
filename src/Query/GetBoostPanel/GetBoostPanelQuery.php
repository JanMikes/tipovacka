<?php

declare(strict_types=1);

namespace App\Query\GetBoostPanel;

use App\Entity\BoostPurchase;
use App\Repository\BoostPurchaseRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CreditWalletRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetBoostPanelQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private BoostPurchaseRepository $boostPurchaseRepository,
        private CreditWalletRepository $walletRepository,
    ) {
    }

    public function __invoke(GetBoostPanel $query): GetBoostPanelResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);
        $wallet = $this->walletRepository->findByUserId($query->userId);

        $ownedTypes = array_map(
            static fn (BoostPurchase $purchase) => $purchase->type,
            $this->boostPurchaseRepository->findActiveByUserAndCompetition($query->userId, $competition->id),
        );

        return new GetBoostPanelResult(
            monetization: $competition->monetization,
            balance: $wallet->balance ?? 0,
            ownedTypes: $ownedTypes,
            tipChangeOffsetMinutes: $competition->tipChangeOffsetMinutes,
        );
    }
}
