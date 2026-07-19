<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionGuessMatrix;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<CompetitionGuessMatrixResult>
 */
final readonly class GetCompetitionGuessMatrix implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $requestingUserId,
    ) {
    }
}
