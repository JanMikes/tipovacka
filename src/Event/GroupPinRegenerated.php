<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GroupPinRegenerated
{
    public function __construct(
        public Uuid $groupId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
