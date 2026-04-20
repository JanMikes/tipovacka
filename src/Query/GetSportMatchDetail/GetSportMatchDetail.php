<?php

declare(strict_types=1);

namespace App\Query\GetSportMatchDetail;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<SportMatchDetailResult>
 */
final readonly class GetSportMatchDetail implements QueryMessage
{
    public function __construct(
        public Uuid $sportMatchId,
    ) {
    }
}
