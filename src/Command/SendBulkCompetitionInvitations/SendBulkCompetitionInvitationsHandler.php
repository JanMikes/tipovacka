<?php

declare(strict_types=1);

namespace App\Command\SendBulkCompetitionInvitations;

use App\Repository\CompetitionRepository;
use App\Repository\UserRepository;
use App\Service\Invitation\CompetitionInviter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendBulkCompetitionInvitationsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionInviter $inviter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendBulkCompetitionInvitationsCommand $command): BulkInvitationResult
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $inviter = $this->userRepository->get($command->inviterId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Lenient: malformed addresses are collected in the result, never thrown —
        // the bulk-invite UI reports them back to the manager.
        return $this->inviter->invite(
            competition: $competition,
            inviter: $inviter,
            rawEntries: [$command->rawEmails],
            now: $now,
            strict: false,
        );
    }
}
