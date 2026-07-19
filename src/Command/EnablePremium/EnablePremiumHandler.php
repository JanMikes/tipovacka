<?php

declare(strict_types=1);

namespace App\Command\EnablePremium;

use App\Entity\BoostPurchase;
use App\Entity\Competition;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\Membership;
use App\Enum\CreditTransactionType;
use App\Exception\InsufficientCredits;
use App\Repository\BoostPurchaseRepository;
use App\Repository\CompetitionPremiumChargeRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CreditTransactionRepository;
use App\Repository\MembershipRepository;
use App\Service\Competition\CompetitionEntitlements;
use App\Service\Credits\CreditWalletProvider;
use App\Service\Credits\PricingConfig;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Enable premium atomically: charge PREMIUM_PER_PLAYER × current active
 * non-owner members as ONE wallet debit (all-or-nothing — an insufficient
 * balance throws {@see InsufficientCredits::forPremiumActivation} naming the
 * exact total, before anything is written), then stamp each member with a
 * Charged row and flip the competition to premium (reconciliation reset).
 *
 * Re-enabling premium also refunds every active boost purchase in the
 * competition (players who paid for boosts are made whole — the whole group is
 * now covered by the manager), inside this same transaction.
 *
 * See .docs/DOMAIN.md §Monetization.
 */
#[AsMessageHandler]
final readonly class EnablePremiumHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private CompetitionPremiumChargeRepository $chargeRepository,
        private BoostPurchaseRepository $boostPurchaseRepository,
        private CreditWalletProvider $walletProvider,
        private CreditTransactionRepository $transactionRepository,
        private CompetitionEntitlements $entitlements,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(EnablePremiumCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Refund every active boost purchase to its buyer (BoostRefund ledger row
        // + BoostRefunded event via the entity) before flipping to premium.
        foreach ($this->boostPurchaseRepository->listActiveByCompetition($competition->id) as $boostPurchase) {
            $this->refundBoost($boostPurchase, $competition, $now);
        }

        $this->entitlements->forget($competition->id);

        $nonOwnerMemberships = array_values(array_filter(
            $this->membershipRepository->findActiveByCompetition($competition->id),
            static fn (Membership $membership): bool => !$membership->user->id->equals($competition->owner->id),
        ));

        $count = count($nonOwnerMemberships);

        if ($count > 0) {
            $total = $count * PricingConfig::PREMIUM_PER_PLAYER;
            $wallet = $this->walletProvider->getForUpdateOrCreate($competition->owner, $now);

            if ($wallet->balance < $total) {
                throw InsufficientCredits::forPremiumActivation($total, $wallet->balance);
            }

            // ONE ledger debit for the whole group; the per-member Charged rows
            // below carry the accounting detail.
            $transaction = $wallet->spend(
                transactionId: $this->identity->next(),
                amount: $total,
                type: CreditTransactionType::PremiumCharge,
                now: $now,
                competition: $competition,
            );
            $this->transactionRepository->save($transaction);

            foreach ($nonOwnerMemberships as $membership) {
                $member = $membership->user;
                $charge = $this->chargeRepository->findByCompetitionAndMember($competition->id, $member->id);

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
                    $charge->reactivate(PricingConfig::PREMIUM_PER_PLAYER, $now);
                }

                $charge->markCharged($now);
            }
        }

        $competition->enablePremium($now);
    }

    private function refundBoost(BoostPurchase $boostPurchase, Competition $competition, \DateTimeImmutable $now): void
    {
        $wallet = $this->walletProvider->getForUpdateOrCreate($boostPurchase->user, $now);

        $transaction = $wallet->refund(
            transactionId: $this->identity->next(),
            amount: $boostPurchase->pricePaid,
            refundType: CreditTransactionType::BoostRefund,
            now: $now,
            competition: $competition,
            boostType: $boostPurchase->type->value,
        );
        $this->transactionRepository->save($transaction);

        // Records BoostRefunded on the entity (dispatched after commit).
        $boostPurchase->refund($now);
    }
}
