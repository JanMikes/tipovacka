<?php

declare(strict_types=1);

namespace App\Command\RevokeShareableLink;

use Symfony\Component\Uid\Uuid;

final readonly class RevokeShareableLinkCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $groupId,
    ) {
    }
}
