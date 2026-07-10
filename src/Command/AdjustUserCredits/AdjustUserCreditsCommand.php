<?php

declare(strict_types=1);

namespace App\Command\AdjustUserCredits;

use Symfony\Component\Uid\Uuid;

/**
 * Admin credit adjustment — positive adds credits, negative removes them
 * (a correction). Always audited: the note and the acting admin end up in the
 * user's transaction history.
 */
final readonly class AdjustUserCreditsCommand
{
    public function __construct(
        public Uuid $userId,
        public int $amount,
        public string $note,
        public Uuid $adjustedById,
    ) {
    }
}
