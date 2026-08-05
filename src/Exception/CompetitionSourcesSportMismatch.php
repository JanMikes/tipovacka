<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Every zdroj a soutěž draws from must be of the SAME sport. Scoring rules are
 * configured per competition and are phrased in the sport's own vocabulary
 * (2 poločasy vs 3 třetiny), so a mixed-sport scope would have no coherent
 * ruleset to evaluate against. See .docs/DOMAIN.md §Core model.
 */
#[\Symfony\Component\HttpKernel\Attribute\WithHttpStatus(422)]
final class CompetitionSourcesSportMismatch extends \DomainException
{
    public static function between(string $expectedSport, string $foundSport): self
    {
        return new self(sprintf(
            'Soutěž může kombinovat jen zdroje stejného sportu — vybrali jste %s a %s.',
            $expectedSport,
            $foundSport,
        ));
    }
}
