<?php

declare(strict_types=1);

namespace App\Query\GetInvitationByToken;

use App\Query\QueryMessage;

/**
 * @implements QueryMessage<InvitationLandingResult>
 */
final readonly class GetInvitationByToken implements QueryMessage
{
    public function __construct(
        public string $token,
        public \DateTimeImmutable $now,
    ) {
    }
}
