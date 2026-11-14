<?php

declare(strict_types=1);

namespace App\Command\MuteCompetitionNotifications;

use App\Repository\MembershipRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MuteCompetitionNotificationsHandler
{
    public function __construct(
        private MembershipRepository $membershipRepository,
    ) {
    }

    public function __invoke(MuteCompetitionNotificationsCommand $command): void
    {
        $membership = $this->membershipRepository->findActiveMembership($command->userId, $command->competitionId);

        // Not a member (any more): there is nothing to silence, and an old link
        // in a mail must not turn into an error page.
        if (null === $membership) {
            return;
        }

        if ($command->muted) {
            $membership->muteNotifications();
        } else {
            $membership->unmuteNotifications();
        }
    }
}
