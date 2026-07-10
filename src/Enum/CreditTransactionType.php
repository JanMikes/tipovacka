<?php

declare(strict_types=1);

namespace App\Enum;

enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    case AdminAdjustment = 'admin_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Nákup kreditů',
            self::AdminAdjustment => 'Úprava administrátorem',
        };
    }
}
