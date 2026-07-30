<?php

declare(strict_types=1);

namespace App\Command\RememberPendingJoin;

use App\Enum\InvitationKind;
use Symfony\Component\Uid\Uuid;

final readonly class RememberPendingJoinCommand
{
    public function __construct(
        public Uuid $userId,
        public InvitationKind $kind,
        public string $token,
    ) {
    }
}
