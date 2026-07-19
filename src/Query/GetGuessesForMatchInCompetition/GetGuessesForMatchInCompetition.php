<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInCompetition;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<GuessesForMatchInCompetitionResult>
 */
final readonly class GetGuessesForMatchInCompetition implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $sportMatchId,
        public Uuid $viewerId,
    ) {
    }
}
