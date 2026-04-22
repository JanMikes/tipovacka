<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInGroup;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<GuessesForMatchInGroupResult>
 */
final readonly class GetGuessesForMatchInGroup implements QueryMessage
{
    public function __construct(
        public Uuid $groupId,
        public Uuid $sportMatchId,
        public Uuid $viewerId,
        public bool $applyHiding = false,
    ) {
    }
}
