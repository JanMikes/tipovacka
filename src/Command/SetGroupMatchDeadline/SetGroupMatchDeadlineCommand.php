<?php

declare(strict_types=1);

namespace App\Command\SetGroupMatchDeadline;

use Symfony\Component\Uid\Uuid;

final readonly class SetGroupMatchDeadlineCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $groupId,
        public Uuid $sportMatchId,
        public ?\DateTimeImmutable $deadline,
    ) {
    }
}
