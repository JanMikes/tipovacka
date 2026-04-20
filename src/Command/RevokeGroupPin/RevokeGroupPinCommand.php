<?php

declare(strict_types=1);

namespace App\Command\RevokeGroupPin;

use Symfony\Component\Uid\Uuid;

final readonly class RevokeGroupPinCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $groupId,
    ) {
    }
}
