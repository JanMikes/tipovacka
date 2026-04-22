<?php

declare(strict_types=1);

namespace App\Command\UpdateGroup;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateGroupCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $groupId,
        public string $name,
        public ?string $description,
        public bool $hideOthersTipsBeforeDeadline,
        public ?\DateTimeImmutable $tipsDeadline,
    ) {
    }
}
