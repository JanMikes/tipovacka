<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
final class InvalidGuessScore extends \DomainException
{
    public static function create(): self
    {
        return new self('Skóre musí být 0 nebo vyšší.');
    }

    public static function periodCountMismatch(int $expected, string $periodLabelPlural): self
    {
        return new self(sprintf('Tip musí obsahovat skóre pro %d %s (nebo žádné).', $expected, $periodLabelPlural));
    }

    public static function periodSumMismatch(): self
    {
        return new self('Součet skóre za jednotlivé části musí odpovídat tipu na základní hrací dobu.');
    }

    public static function overtimeIncomplete(): self
    {
        return new self('Zadejte prosím obě hodnoty tipu po prodloužení.');
    }

    public static function overtimeWithoutDraw(): self
    {
        return new self('Prodloužení lze tipnout jen při remíze v základní hrací době.');
    }

    public static function overtimeNotPlayedInSource(): self
    {
        return new self('V tomto zdroji zápasů se prodloužení nehraje, remíza je konečný tip.');
    }

    public static function overtimeNotDrawPlusOne(): self
    {
        return new self('Tip po prodloužení či penaltách se zapisuje jako remíza plus jeden gól pro vítěze, např. 2:2 → 3:2 nebo 2:3.');
    }
}
