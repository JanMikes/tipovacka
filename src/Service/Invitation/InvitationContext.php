<?php

declare(strict_types=1);

namespace App\Service\Invitation;

use App\Enum\InvitationKind;
use Symfony\Component\Uid\Uuid;

final readonly class InvitationContext
{
    public function __construct(
        public InvitationKind $kind,
        public string $token,
        public Uuid $competitionId,
        public string $competitionName,
        public string $matchSourceName,
        public ?string $inviterNickname,
        public ?string $presetEmail,
        public InvitationContextStatus $status,
        public ?\DateTimeImmutable $expiresAt,
        /**
         * What joining costs, in credits. Only a global competition charges anything —
         * every secret-based way in (invitation / link / PIN) is free by construction —
         * so this is 0 for all other kinds and the landing page says nothing about money.
         */
        public int $entryFeeCredits = 0,
    ) {
    }
}
