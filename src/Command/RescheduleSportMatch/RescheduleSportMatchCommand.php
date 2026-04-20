<?php

declare(strict_types=1);

namespace App\Command\RescheduleSportMatch;

use Symfony\Component\Uid\Uuid;

final readonly class RescheduleSportMatchCommand
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $editorId,
        public \DateTimeImmutable $newKickoffAt,
    ) {
    }
}
