<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GroupShareableLinkRegenerated
{
    public function __construct(
        public Uuid $groupId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
