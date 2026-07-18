<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionDetail;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<GetCompetitionDetailResult>
 */
final readonly class GetCompetitionDetail implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $viewerId,
        public bool $viewerIsAdmin,
    ) {
    }
}
