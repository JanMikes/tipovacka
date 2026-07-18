<?php

declare(strict_types=1);

namespace App\Query\GetUserGuessInCompetitionForMatch;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<UserGuessResult|null>
 */
final readonly class GetUserGuessInCompetitionForMatch implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        public Uuid $competitionId,
        public Uuid $sportMatchId,
    ) {
    }
}
