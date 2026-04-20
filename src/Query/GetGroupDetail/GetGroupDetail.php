<?php

declare(strict_types=1);

namespace App\Query\GetGroupDetail;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<GetGroupDetailResult>
 */
final readonly class GetGroupDetail implements QueryMessage
{
    public function __construct(
        public Uuid $groupId,
        public Uuid $viewerId,
        public bool $viewerIsAdmin,
    ) {
    }
}
