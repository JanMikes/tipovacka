<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

/**
 * Re-enabling premium on a competition that is ALREADY premium. Enabling charges
 * the manager N × PREMIUM_PER_PLAYER, so a repeat invocation (double-submit /
 * direct POST) would debit the owner again with no new charge rows. Guarded
 * before any wallet movement; the controller maps this to a friendly flash.
 * See .docs/DOMAIN.md §Monetization.
 */
#[WithHttpStatus(409)]
final class PremiumAlreadyEnabled extends \DomainException
{
    public static function forCompetition(Uuid $competitionId): self
    {
        return new self(sprintf('Premium is already enabled on competition %s.', $competitionId->toRfc4122()));
    }
}
