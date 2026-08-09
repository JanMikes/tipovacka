<?php

declare(strict_types=1);

namespace App\Command\SponsorCompetitionPremium;

use App\Entity\Competition;
use App\Enum\UserRole;
use App\Exception\CompetitionIsGlobal;
use App\Exception\PremiumSponsorshipRequiresAdmin;
use App\Repository\CompetitionPremiumChargeRepository;
use App\Repository\CompetitionRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionEntitlements;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Grants (or withdraws) „premium on us" for one competition.
 *
 * The authorization lives HERE, not only on the admin controller, because the
 * same command is reachable from `app:competition:sponsor-premium`, where there
 * is no firewall to lean on.
 */
#[AsMessageHandler]
final readonly class SponsorCompetitionPremiumHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionPremiumChargeRepository $chargeRepository,
        private CompetitionEntitlements $entitlements,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SponsorCompetitionPremiumCommand $command): void
    {
        $grantedBy = $this->userRepository->get($command->grantedById);

        if (!in_array(UserRole::ADMIN->value, $grantedBy->getRoles(), true)) {
            throw PremiumSponsorshipRequiresAdmin::create();
        }

        $competition = $this->competitionRepository->get($command->competitionId);

        if ($competition->isGlobal) {
            throw CompetitionIsGlobal::premiumIsAlreadyOnUs();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if (!$command->sponsored) {
            $competition->withdrawPremiumSponsorship($now);
            $this->entitlements->forget($competition->id);

            return;
        }

        $competition->sponsorPremium($now);
        $this->forgiveUncoveredCharges($competition, $now);
        $this->entitlements->forget($competition->id);
    }

    /**
     * A group being sponsored may already carry Uncovered rows from members who
     * joined while the organizer's wallet was empty. Left alone, ONE of them
     * downgrades the competition at its first kickoff — the exact fate the gift
     * is meant to prevent — so they are settled here.
     *
     * Marked Refunded WITHOUT any wallet movement, which is precisely what an
     * uncovered row means: the credits were never taken, so there is nothing to
     * give back. (Reconciliation does the same for uncovered rows when it
     * downgrades.) Rows already Charged are deliberately untouched: that money
     * really did move, and handing it back is a refund decision of its own.
     */
    private function forgiveUncoveredCharges(Competition $competition, \DateTimeImmutable $now): void
    {
        foreach ($this->chargeRepository->findAllForCompetition($competition->id) as $charge) {
            if ($charge->isUncovered) {
                $charge->markRefunded($now);
            }
        }
    }
}
