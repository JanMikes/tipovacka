<?php

declare(strict_types=1);

namespace App\Query\GetMyGuessesInTournament;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<MyGuessRowItem>>
 */
final readonly class GetMyGuessesInTournament implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        public Uuid $tournamentId,
        public Uuid $groupId,
    ) {
    }
}
