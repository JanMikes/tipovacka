<?php

declare(strict_types=1);

namespace App\Command\RegenerateGroupPin;

use Symfony\Component\Uid\Uuid;

final readonly class RegenerateGroupPinCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $groupId,
    ) {
    }
}
