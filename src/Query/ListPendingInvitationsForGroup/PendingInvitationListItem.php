<?php

declare(strict_types=1);

namespace App\Query\ListPendingInvitationsForGroup;

use Symfony\Component\Uid\Uuid;

final readonly class PendingInvitationListItem
{
    public function __construct(
        public Uuid $invitationId,
        public string $email,
        public string $inviterNickname,
        public \DateTimeImmutable $sentAt,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
