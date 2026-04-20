<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteGroup;

use Symfony\Component\Uid\Uuid;

final readonly class SoftDeleteGroupCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $groupId,
    ) {
    }
}
