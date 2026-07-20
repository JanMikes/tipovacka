<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionMonetizationOverview;

final readonly class ActiveBoostRow
{
    public function __construct(
        public string $userName,
        public string $boostLabel,
        public int $pricePaid,
        public \DateTimeImmutable $purchasedAt,
    ) {
    }
}
