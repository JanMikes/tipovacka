<?php

declare(strict_types=1);

namespace App\Command\MarkBoostIntroSeen;

use App\Repository\MembershipRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Idempotent and silent: a non-member (or a member who already dismissed it) is
 * a no-op, never an error. The modal is a courtesy — a failed dismissal must not
 * be able to break the page it was opened from.
 */
#[AsMessageHandler]
final readonly class MarkBoostIntroSeenHandler
{
    public function __construct(
        private MembershipRepository $membershipRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkBoostIntroSeenCommand $command): void
    {
        $membership = $this->membershipRepository->findActiveMembership(
            $command->userId,
            $command->competitionId,
        );

        $membership?->markBoostIntroSeen(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
