<?php

declare(strict_types=1);

namespace App\Command\CreateGlobalCompetition;

use App\Entity\Competition;
use App\Repository\MatchSourceRepository;
use App\Repository\UserRepository;
use App\Service\Competition\GlobalCompetitionComposer;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Composes the whole global-competition aggregate in ONE transaction (the command
 * bus's `doctrine_transaction` middleware flushes on success, rolls back on any
 * exception) via {@see GlobalCompetitionComposer}: the competition (isGlobal,
 * mode All, owner = admin), the admin's owner membership and the per-rule
 * configuration (defaults overlaid by changes).
 */
#[AsMessageHandler]
final readonly class CreateGlobalCompetitionHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private UserRepository $userRepository,
        private GlobalCompetitionComposer $composer,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateGlobalCompetitionCommand $command): Competition
    {
        $admin = $this->userRepository->get($command->adminId);
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);

        return $this->composer->compose(
            matchSource: $matchSource,
            admin: $admin,
            name: $command->name,
            entryFeeCredits: $command->entryFeeCredits,
            monetization: $command->monetization,
            ruleChanges: $command->ruleChanges,
            now: \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
