<?php

declare(strict_types=1);

namespace App\Command\SwitchToBoosts;

use App\Enum\CreditTransactionType;
use App\Repository\CompetitionPremiumChargeRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CreditTransactionRepository;
use App\Service\Credits\CreditWalletProvider;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Switch a premium competition to boosts: refund every Charged premium row back
 * to the manager (PremiumRefund), mark them Refunded, and flip the monetization.
 * Uncovered rows were never paid, so they are simply left dormant (excluded from
 * settle by the premium-only filter). See .docs/DOMAIN.md §Monetization.
 */
#[AsMessageHandler]
final readonly class SwitchToBoostsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionPremiumChargeRepository $chargeRepository,
        private CreditWalletProvider $walletProvider,
        private CreditTransactionRepository $transactionRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SwitchToBoostsCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $charges = $this->chargeRepository->findChargedForCompetition($competition->id);

        if ([] !== $charges) {
            $wallet = $this->walletProvider->getForUpdateOrCreate($competition->owner, $now);

            foreach ($charges as $charge) {
                $transaction = $wallet->refund(
                    transactionId: $this->identity->next(),
                    amount: $charge->amount,
                    refundType: CreditTransactionType::PremiumRefund,
                    now: $now,
                    competition: $competition,
                    relatedUser: $charge->member,
                );
                $this->transactionRepository->save($transaction);
                $charge->markRefunded($now);
            }
        }

        $competition->switchToBoosts($now);
    }
}
