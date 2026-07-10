<?php

declare(strict_types=1);

namespace App\Enum;

enum CreditPurchaseStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Expired = 'expired';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Čeká na platbu',
            self::Completed => 'Zaplaceno',
            self::Expired => 'Vypršelo',
            self::Failed => 'Platba selhala',
        };
    }
}
