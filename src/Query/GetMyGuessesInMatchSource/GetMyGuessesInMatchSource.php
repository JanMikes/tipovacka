<?php

declare(strict_types=1);

namespace App\Query\GetMyGuessesInMatchSource;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<MyGuessRowItem>>
 */
final readonly class GetMyGuessesInMatchSource implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        public Uuid $matchSourceId,
        public Uuid $competitionId,
    ) {
    }
}
