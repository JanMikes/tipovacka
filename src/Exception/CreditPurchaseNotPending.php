<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\CreditPurchaseStatus;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(409)]
final class CreditPurchaseNotPending extends \DomainException
{
    public static function withStatus(Uuid $purchaseId, CreditPurchaseStatus $status): self
    {
        return new self(sprintf(
            'Nákup kreditů "%s" nelze změnit, není ve stavu "pending" (aktuální stav: "%s").',
            $purchaseId->toRfc4122(),
            $status->value,
        ));
    }
}
