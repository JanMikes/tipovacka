<?php

declare(strict_types=1);

namespace App\Query\ListAllCreditTransactions;

use Symfony\Component\Uid\Uuid;

/** A competition referenced by at least one credit transaction — a ledger filter option. */
final readonly class AdminCreditLedgerCompetitionOption
{
    public function __construct(
        public Uuid $id,
        public string $name,
    ) {
    }
}
