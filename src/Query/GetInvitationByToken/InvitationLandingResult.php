<?php

declare(strict_types=1);

namespace App\Query\GetInvitationByToken;

final readonly class InvitationLandingResult
{
    public function __construct(
        public string $token,
        public string $groupName,
        public string $tournamentName,
        public string $inviterNickname,
        public bool $isExpired,
        public bool $isAccepted,
        public bool $isRevoked,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
