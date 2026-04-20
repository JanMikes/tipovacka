<?php

declare(strict_types=1);

namespace App\Command\RegenerateShareableLink;

use Symfony\Component\Uid\Uuid;

final readonly class RegenerateShareableLinkCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $groupId,
    ) {
    }
}
