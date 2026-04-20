<?php

declare(strict_types=1);

namespace App\Command\PostponeSportMatch;

use Symfony\Component\Uid\Uuid;

final readonly class PostponeSportMatchCommand
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $editorId,
        public \DateTimeImmutable $newKickoffAt,
    ) {
    }
}
