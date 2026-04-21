<?php

declare(strict_types=1);

namespace App\Query\ListRecentEvaluatedGuessesForUser;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<EvaluatedGuessItem>>
 */
final readonly class ListRecentEvaluatedGuessesForUser implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
