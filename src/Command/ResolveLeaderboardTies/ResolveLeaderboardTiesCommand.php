<?php

declare(strict_types=1);

namespace App\Command\ResolveLeaderboardTies;

use Symfony\Component\Uid\Uuid;

final readonly class ResolveLeaderboardTiesCommand
{
    /**
     * @param list<Uuid> $orderedUserIds
     */
    public function __construct(
        public Uuid $groupId,
        public Uuid $resolverId,
        public array $orderedUserIds,
    ) {
    }
}
