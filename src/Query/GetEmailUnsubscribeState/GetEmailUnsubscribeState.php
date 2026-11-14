<?php

declare(strict_types=1);

namespace App\Query\GetEmailUnsubscribeState;

use App\Query\QueryMessage;
use App\Value\UnsubscribeScope;
use Symfony\Component\Uid\Uuid;

/**
 * What the no-login unsubscribe page shows for one player and (optionally) the
 * thing a mail's link pointed at.
 *
 * @implements QueryMessage<GetEmailUnsubscribeStateResult|null>
 */
final readonly class GetEmailUnsubscribeState implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        public ?UnsubscribeScope $scope = null,
    ) {
    }
}
