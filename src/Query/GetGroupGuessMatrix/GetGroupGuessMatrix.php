<?php

declare(strict_types=1);

namespace App\Query\GetGroupGuessMatrix;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<GroupGuessMatrixResult>
 */
final readonly class GetGroupGuessMatrix implements QueryMessage
{
    public function __construct(
        public Uuid $groupId,
        public Uuid $requestingUserId,
        public bool $applyHiding,
    ) {
    }
}
