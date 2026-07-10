<?php

declare(strict_types=1);

namespace App\Query\GetCreditWallet;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<GetCreditWalletResult>
 */
final readonly class GetCreditWallet implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
