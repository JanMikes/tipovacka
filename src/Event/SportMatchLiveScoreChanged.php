<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * Live (in-progress) score changed. Intentionally has NO evaluation handler —
 * only the final score drives guess evaluation.
 */
final readonly class SportMatchLiveScoreChanged
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $matchSourceId,
        public int $homeScore,
        public int $awayScore,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
