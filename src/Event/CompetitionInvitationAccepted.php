<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class CompetitionInvitationAccepted
{
    public function __construct(
        public Uuid $invitationId,
        public Uuid $competitionId,
        public Uuid $userId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
