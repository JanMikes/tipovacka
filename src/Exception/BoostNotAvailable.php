<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\BoostType;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * A boost cannot be purchased: the competition is not `boosts`-monetized, the
 * buyer already owns the same active boost, or the requested boost is already
 * covered by an owned OthersTips (superset). See .docs/DOMAIN.md §Monetization.
 */
#[WithHttpStatus(409)]
final class BoostNotAvailable extends \DomainException
{
    public static function becauseCompetitionIsNotBoosts(): self
    {
        return new self('V této soutěži nelze vylepšení koupit — nemá zapnuté příspěvky jednotlivců.');
    }

    public static function becauseAlreadyOwned(BoostType $type): self
    {
        return new self(sprintf('Vylepšení „%s" už v této soutěži máte.', $type->label()));
    }

    public static function becauseSupersededByOthersTips(): self
    {
        return new self('Lišta tipů ostatních je už součástí vašeho vylepšení „Konkrétní tipy kolegů".');
    }
}
