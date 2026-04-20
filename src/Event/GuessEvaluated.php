<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GuessEvaluated
{
    public function __construct(
        public Uuid $evaluationId,
        public Uuid $guessId,
        public int $totalPoints,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
