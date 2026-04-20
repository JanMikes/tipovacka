<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\TournamentVisibility;
use Symfony\Component\Uid\Uuid;

final readonly class TournamentCreated
{
    public function __construct(
        public Uuid $tournamentId,
        public Uuid $ownerId,
        public TournamentVisibility $visibility,
        public string $name,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
