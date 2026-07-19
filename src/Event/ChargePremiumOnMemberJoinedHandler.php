<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\CompetitionPremiumCharge;
use App\Enum\CompetitionMonetization;
use App\Enum\CreditTransactionType;
use App\Exception\InsufficientCredits;
use App\Repository\CompetitionPremiumChargeRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CreditTransactionRepository;
use App\Repository\UserRepository;
use App\Service\Credits\CreditWalletProvider;
use App\Service\Credits\PricingConfig;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Premium charge-at-join. Runs on the event bus AFTER the join transaction has
 * committed (see {@see \App\Middleware\DispatchDomainEventsMiddleware}), in its
 * OWN transaction — so a failed charge NEVER rolls back the membership.
 *
 * Charges the OWNER {@see PricingConfig::PREMIUM_PER_PLAYER} per non-owner member
 * (regardless of whether the competition is global or a user competition). An
 * insufficient balance is caught, not propagated: the charge row is recorded as
 * Uncovered and {@see PremiumChargeUncovered} fires so S11 can notify — the
 * member stays joined. A late uncovered join AFTER reconciliation only notifies;
 * it never re-downgrades (this handler never touches monetization).
 *
 * See .docs/DOMAIN.md §Monetization.
 */
#[AsMessageHandler]
final readonly class ChargePremiumOnMemberJoinedHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionPremiumChargeRepository $chargeRepository,
        private CreditWalletProvider $walletProvider,
        private CreditTransactionRepository $transactionRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MemberJoinedCompetition $event): void
    {
        $competition = $this->competitionRepository->get($event->competitionId);

        if (CompetitionMonetization::Premium !== $competition->monetization) {
            return;
        }

        // The owner is never charged for their own membership.
        if ($event->userId->equals($competition->owner->id)) {
            return;
        }

        $member = $this->userRepository->get($event->userId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $charge = $this->chargeRepository->findByCompetitionAndMember($competition->id, $member->id);

        // Rejoin onto an already-paid slot: the (competition, member) charge row
        // can be refunded only ONCE, and leaving never refunds it — so the owner
        // has already paid for this slot and never got the money back. Charging
        // again here would debit them a second time against that single refundable
        // row (permanent PREMIUM_PER_PLAYER loss + broken refund symmetry). The
        // paid slot stands; do nothing. See .docs/DOMAIN.md §Monetization.
        if (null !== $charge && $charge->isCharged) {
            return;
        }

        $wallet = $this->walletProvider->getForUpdateOrCreate($competition->owner, $now);

        if (null === $charge) {
            $charge = new CompetitionPremiumCharge(
                id: $this->identity->next(),
                competition: $competition,
                member: $member,
                amount: PricingConfig::PREMIUM_PER_PLAYER,
                createdAt: $now,
            );
            $this->chargeRepository->save($charge);
        } else {
            // Rejoin onto an Uncovered/Refunded row — reuse it for a fresh attempt.
            $charge->reactivate(PricingConfig::PREMIUM_PER_PLAYER, $now);
        }

        try {
            $transaction = $wallet->spend(
                transactionId: $this->identity->next(),
                amount: PricingConfig::PREMIUM_PER_PLAYER,
                type: CreditTransactionType::PremiumCharge,
                now: $now,
                competition: $competition,
                relatedUser: $member,
            );
            $this->transactionRepository->save($transaction);
            $charge->markCharged($now);
        } catch (InsufficientCredits) {
            $charge->markUncovered($now);
        }

        if ($wallet->balance < PricingConfig::LOW_BALANCE_WARNING_THRESHOLD) {
            $charge->flagOwnerBalanceLow($wallet->balance, $now);
        }
    }
}
