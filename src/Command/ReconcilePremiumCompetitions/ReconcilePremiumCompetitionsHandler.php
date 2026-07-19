<?php

declare(strict_types=1);

namespace App\Command\ReconcilePremiumCompetitions;

use App\Entity\Competition;
use App\Enum\CreditTransactionType;
use App\Repository\CompetitionPremiumChargeRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CreditTransactionRepository;
use App\Service\Credits\CreditWalletProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Premium reconciliation at competition start (DOMAIN.md §Monetization):
 *
 * For every premium competition not yet reconciled whose start moment
 * ({@see EffectiveTipDeadlineResolver::lockMomentFor} — manual lock or first
 * kickoff) is at/behind now:
 *  - all charges Charged ⇒ {@see Competition::markPremiumReconciled} (PremiumConfirmed);
 *  - any Uncovered ⇒ refund every Charged row to the manager, mark all rows
 *    Refunded, {@see Competition::downgradeToBoosts} (PremiumDowngraded).
 *
 * Idempotent: the premiumReconciledAt guard means a re-run (or a competition
 * already reconciled) is a no-op. A late uncovered join after reconciliation is
 * NOT re-processed here (its competition is already stamped), so it never
 * triggers a second downgrade — it only notifies via the join hook.
 */
#[AsMessageHandler]
final readonly class ReconcilePremiumCompetitionsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionPremiumChargeRepository $chargeRepository,
        private CreditWalletProvider $walletProvider,
        private CreditTransactionRepository $transactionRepository,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ReconcilePremiumCompetitionsCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($this->competitionRepository->findPremiumAwaitingReconciliation() as $competition) {
            $startMoment = $this->deadlineResolver->lockMomentFor($competition);

            // No matches yet, or the competition has not started — leave it for
            // a later tick (a match may still be added or postponed).
            if (null === $startMoment || $startMoment > $now) {
                continue;
            }

            $this->reconcile($competition, $now);
        }
    }

    private function reconcile(Competition $competition, \DateTimeImmutable $now): void
    {
        if ($this->chargeRepository->countUncoveredForCompetition($competition->id) > 0) {
            $this->refundAndDowngrade($competition, $now);

            return;
        }

        // All charges covered (or none at all) — confirm.
        $competition->markPremiumReconciled($now);
    }

    private function refundAndDowngrade(Competition $competition, \DateTimeImmutable $now): void
    {
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

        $competition->downgradeToBoosts($now);
    }
}
