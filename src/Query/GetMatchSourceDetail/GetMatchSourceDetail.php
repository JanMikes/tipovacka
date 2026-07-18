<?php

declare(strict_types=1);

namespace App\Query\GetMatchSourceDetail;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<GetMatchSourceDetailResult>
 */
final readonly class GetMatchSourceDetail implements QueryMessage
{
    public function __construct(
        public Uuid $matchSourceId,
    ) {
    }
}
