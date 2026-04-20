<?php

declare(strict_types=1);

namespace App\Query\GetUserGuessInGroupForMatch;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<UserGuessResult|null>
 */
final readonly class GetUserGuessInGroupForMatch implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        public Uuid $groupId,
        public Uuid $sportMatchId,
    ) {
    }
}
