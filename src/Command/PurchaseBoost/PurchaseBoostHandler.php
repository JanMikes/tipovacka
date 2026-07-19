<?php

declare(strict_types=1);

namespace App\Command\PurchaseBoost;

use App\Entity\BoostPurchase;
use App\Enum\BoostType;
use App\Enum\CompetitionMonetization;
use App\Enum\CreditTransactionType;
use App\Exception\BoostNotAvailable;
use App\Exception\NotAMember;
use App\Repository\BoostPurchaseRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CreditTransactionRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionEntitlements;
use App\Service\Credits\CreditWalletProvider;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Buy a per-competition boost: guard (boosts-monetized, active member, not
 * already owned / superseded), then a single wallet debit + a BoostPurchase row
 * in one transaction. {@see \App\Exception\InsufficientCredits} bubbles to the
 * controller (friendly top-up prompt). See .docs/DOMAIN.md §Monetization.
 *
 * Superset: owning OthersTips entitles the buyer to the distribution bar, so the
 * TipDistribution offer is blocked once OthersTips is held. Buying OthersTips
 * while already owning TipDistribution is allowed and charges the FULL OthersTips
 * price (kept dumb — no differential pricing).
 */
#[AsMessageHandler]
final readonly class PurchaseBoostHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private BoostPurchaseRepository $boostPurchaseRepository,
        private CreditWalletProvider $walletProvider,
        private CreditTransactionRepository $transactionRepository,
        private CompetitionEntitlements $entitlements,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PurchaseBoostCommand $command): BoostPurchase
    {
        $competition = $this->competitionRepository->get($command->competitionId);

        if (CompetitionMonetization::Boosts !== $competition->monetization) {
            throw BoostNotAvailable::becauseCompetitionIsNotBoosts();
        }

        if (!$this->membershipRepository->hasActiveMembership($command->userId, $competition->id)) {
            throw NotAMember::of($competition->id);
        }

        if (null !== $this->boostPurchaseRepository->findActiveByUserCompetitionType(
            $command->userId,
            $competition->id,
            $command->type,
        )) {
            throw BoostNotAvailable::becauseAlreadyOwned($command->type);
        }

        // Superset: the distribution bar is already covered by an owned OthersTips.
        if (BoostType::TipDistribution === $command->type && null !== $this->boostPurchaseRepository->findActiveByUserCompetitionType(
            $command->userId,
            $competition->id,
            BoostType::OthersTips,
        )) {
            throw BoostNotAvailable::becauseSupersededByOthersTips();
        }

        $user = $this->userRepository->get($command->userId);

        // Don't sell a visibility boost to a buyer already entitled to it for free.
        // A manager/admin is auto-entitled to see the distribution bar + others'
        // tips, so charging them for Lišta/Konkrétní would just burn their credits.
        // tip_change is NOT auto-granted to managers (subject to the tip freeze),
        // so it stays buyable by the owner. See .docs/DOMAIN.md §Monetization.
        if (
            (BoostType::TipDistribution === $command->type && $this->entitlements->isEntitledToDistribution($competition, $user))
            || (BoostType::OthersTips === $command->type && $this->entitlements->isEntitledToOthersTips($competition, $user))
        ) {
            throw BoostNotAvailable::becauseAlreadyEntitled($command->type);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $price = $command->type->price();

        // Load-for-update serialises concurrent movements; InsufficientCredits
        // bubbles (nothing persisted — the transaction rolls back).
        $wallet = $this->walletProvider->getForUpdateOrCreate($user, $now);
        $transaction = $wallet->spend(
            transactionId: $this->identity->next(),
            amount: $price,
            type: CreditTransactionType::BoostPurchase,
            now: $now,
            competition: $competition,
            boostType: $command->type->value,
        );
        $this->transactionRepository->save($transaction);

        $boostPurchase = new BoostPurchase(
            id: $this->identity->next(),
            user: $user,
            competition: $competition,
            type: $command->type,
            pricePaid: $price,
            purchasedAt: $now,
        );
        $this->boostPurchaseRepository->save($boostPurchase);

        $this->entitlements->forget($competition->id);

        return $boostPurchase;
    }
}
