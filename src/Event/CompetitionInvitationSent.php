<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class CompetitionInvitationSent
{
    public function __construct(
        public Uuid $invitationId,
        public Uuid $competitionId,
        public Uuid $inviterId,
        public string $email,
        public string $token,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
