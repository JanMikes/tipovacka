<?php

declare(strict_types=1);

namespace App\Command\SettleUncoveredPremiumCharges;

use App\Enum\CreditTransactionType;
use App\Repository\CompetitionPremiumChargeRepository;
use App\Repository\CreditTransactionRepository;
use App\Repository\UserRepository;
use App\Service\Credits\CreditWalletProvider;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Settles a manager's Uncovered premium charges (oldest first) from their
 * topped-up wallet, marking each Charged on success and stopping as soon as the
 * balance cannot cover the next one — the remaining charges stay Uncovered for
 * the next top-up. See .docs/DOMAIN.md §Monetization.
 */
#[AsMessageHandler]
final readonly class SettleUncoveredPremiumChargesHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private CompetitionPremiumChargeRepository $chargeRepository,
        private CreditWalletProvider $walletProvider,
        private CreditTransactionRepository $transactionRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SettleUncoveredPremiumChargesCommand $command): void
    {
        $charges = $this->chargeRepository->findUncoveredForOwner($command->ownerId);

        if ([] === $charges) {
            return;
        }

        $owner = $this->userRepository->get($command->ownerId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $wallet = $this->walletProvider->getForUpdateOrCreate($owner, $now);

        foreach ($charges as $charge) {
            if ($wallet->balance < $charge->amount) {
                // Oldest-first order + all-equal price ⇒ once one can't be
                // covered, none of the rest can either.
                break;
            }

            $transaction = $wallet->spend(
                transactionId: $this->identity->next(),
                amount: $charge->amount,
                type: CreditTransactionType::PremiumCharge,
                now: $now,
                competition: $charge->competition,
                relatedUser: $charge->member,
            );
            $this->transactionRepository->save($transaction);
            $charge->markCharged($now);
        }
    }
}
