<?php

declare(strict_types=1);

namespace App\Value;

use App\Enum\MatchEventType;
use App\Enum\MatchSide;

/**
 * One row of the score-entry event sheet (goal / card) as submitted by the
 * organizer. The team follows from the side; the player is resolved (or
 * created) in the source's roster pool by name.
 */
final readonly class MatchEventInput
{
    public function __construct(
        public MatchEventType $type,
        public MatchSide $side,
        public ?int $minute,
        public string $playerName,
    ) {
    }
}
