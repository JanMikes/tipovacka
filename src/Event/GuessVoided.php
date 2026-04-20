<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GuessVoided
{
    public function __construct(
        public Uuid $guessId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
