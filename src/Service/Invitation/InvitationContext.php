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
        public Uuid $groupId,
        public string $groupName,
        public string $tournamentName,
        public ?string $inviterNickname,
        public ?string $presetEmail,
        public InvitationContextStatus $status,
        public ?\DateTimeImmutable $expiresAt,
    ) {
    }
}
