<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionMonetizationOverview;

use App\Enum\PremiumChargeStatus;

final readonly class PremiumChargeRow
{
    public function __construct(
        public string $memberName,
        public int $amount,
        public PremiumChargeStatus $status,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
