<?php

declare(strict_types=1);

namespace App\Enum;

enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    case AdminAdjustment = 'admin_adjustment';
    case EntryFee = 'entry_fee';
    case PremiumCharge = 'premium_charge';
    case BoostPurchase = 'boost_purchase';
    case PremiumRefund = 'premium_refund';
    case BoostRefund = 'boost_refund';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Nákup kreditů',
            self::AdminAdjustment => 'Úprava administrátorem',
            self::EntryFee => 'Vstupné do soutěže',
            self::PremiumCharge => 'Prémium za hráče',
            self::BoostPurchase => 'Nákup vylepšení',
            self::PremiumRefund => 'Vrácení prémia',
            self::BoostRefund => 'Vrácení vylepšení',
        };
    }

    /** Debits the wallet — allowed for CreditWallet::spend() only. */
    public function isSpend(): bool
    {
        return match ($this) {
            self::EntryFee, self::PremiumCharge, self::BoostPurchase => true,
            default => false,
        };
    }

    /** Credits the wallet back — allowed for CreditWallet::refund() only. */
    public function isRefund(): bool
    {
        return match ($this) {
            self::PremiumRefund, self::BoostRefund => true,
            default => false,
        };
    }
}
