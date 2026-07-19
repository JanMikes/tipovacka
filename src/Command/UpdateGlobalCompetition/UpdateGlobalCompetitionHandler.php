<?php

declare(strict_types=1);

namespace App\Command\UpdateGlobalCompetition;

use App\Entity\Competition;
use App\Exception\CompetitionIsNotGlobal;
use App\Exception\GlobalCompetitionFeeLocked;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Enforces the global fee-lock: the entry fee + monetization are editable only
 * until the FIRST non-owner ever joins. The lock is monotonic — it counts ALL
 * memberships (including left ones), so once anyone besides the owner has joined
 * (count > 1) the terms stay locked forever, even after that member leaves.
 * Players joined under the advertised fee; a left member's row keeps it locked.
 */
#[AsMessageHandler]
final readonly class UpdateGlobalCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateGlobalCompetitionCommand $command): Competition
    {
        $competition = $this->competitionRepository->get($command->competitionId);

        if (!$competition->isGlobal) {
            throw CompetitionIsNotGlobal::withId($competition->id);
        }

        if ($this->membershipRepository->countAllForCompetition($competition->id) > 1) {
            throw GlobalCompetitionFeeLocked::withId($competition->id);
        }

        $competition->updateGlobalSettings(
            entryFeeCredits: $command->entryFeeCredits,
            monetization: $command->monetization,
            now: \DateTimeImmutable::createFromInterface($this->clock->now()),
        );

        return $competition;
    }
}
