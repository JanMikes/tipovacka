<?php

declare(strict_types=1);

namespace App\Command\JoinGlobalCompetition;

use App\Entity\Competition;
use App\Entity\Membership;
use App\Enum\CreditTransactionType;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Exception\CompetitionIsNotGlobal;
use App\Exception\InsufficientCredits;
use App\Repository\CompetitionRepository;
use App\Repository\CreditTransactionRepository;
use App\Repository\CreditWalletRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Credits\CreditWalletProvider;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Joins a verified user into a global competition, charging the entry fee (when
 * > 0) against their credit wallet. Everything runs in ONE transaction: an
 * InsufficientCredits thrown by CreditWallet::spend() rolls the whole thing back,
 * so no membership and no ledger row survive a failed paid join. The fee is
 * BURNED — non-refundable; a rejoin after leaving is charged again (a fresh
 * membership + fresh spend). See .docs/DOMAIN.md §Global competitions.
 */
#[AsMessageHandler]
final readonly class JoinGlobalCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private UserRepository $userRepository,
        private CreditWalletProvider $walletProvider,
        private CreditWalletRepository $walletRepository,
        private CreditTransactionRepository $transactionRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(JoinGlobalCompetitionCommand $command): Competition
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $user = $this->userRepository->get($command->userId);

        if (!$competition->isGlobal) {
            throw CompetitionIsNotGlobal::withId($competition->id);
        }

        if ($competition->matchSource->isCompleted) {
            throw CannotJoinFinishedMatchSource::forCompetition($competition->id);
        }

        if ($this->membershipRepository->hasActiveMembership($user->id, $competition->id)) {
            throw AlreadyMember::in($competition->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Charge the entry fee BEFORE creating the membership: an insufficient
        // balance throws here and rolls back the whole transaction, so a failed
        // paid join leaves neither a membership nor a ledger entry.
        if ($competition->entryFeeCredits > 0) {
            // Short-circuit the obvious insufficient case WITHOUT creating an
            // empty wallet for someone who cannot pay; the authoritative check
            // under a row lock still lives in CreditWallet::spend().
            $existingWallet = $this->walletRepository->findByUserId($user->id);
            $balance = null !== $existingWallet ? $existingWallet->balance : 0;

            if ($balance < $competition->entryFeeCredits) {
                throw InsufficientCredits::forSpend($competition->entryFeeCredits - $balance);
            }

            $wallet = $this->walletProvider->getForUpdateOrCreate($user, $now);

            $transaction = $wallet->spend(
                transactionId: $this->identity->next(),
                amount: $competition->entryFeeCredits,
                type: CreditTransactionType::EntryFee,
                now: $now,
                competition: $competition,
            );

            $this->transactionRepository->save($transaction);
        }

        $this->membershipRepository->save(new Membership(
            id: $this->identity->next(),
            competition: $competition,
            user: $user,
            joinedAt: $now,
        ));

        return $competition;
    }
}
