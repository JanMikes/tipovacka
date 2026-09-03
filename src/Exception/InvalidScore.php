<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
final class InvalidScore extends \DomainException
{
    public static function negative(): self
    {
        return new self('Skóre nemůže být záporné.');
    }

    public static function emptyPeriods(): self
    {
        return new self('Musí být uvedeno skóre alespoň jedné části zápasu.');
    }

    public static function invalidPeriodPair(): self
    {
        return new self('Skóre každé části zápasu musí být dvojice nezáporných celých čísel.');
    }

    public static function periodCountMismatch(int $expected, string $periodLabelPlural): self
    {
        return new self(sprintf('Zápas musí mít zadané skóre pro %d %s.', $expected, $periodLabelPlural));
    }

    public static function tooManyPeriods(int $maximum, string $periodLabelPlural): self
    {
        return new self(sprintf('Zápas nemůže mít více než %d %s.', $maximum, $periodLabelPlural));
    }

    public static function periodSumMismatch(): self
    {
        return new self('Součet gólů za jednotlivé části zápasu musí odpovídat konečnému skóre.');
    }

    public static function overtimeIncomplete(): self
    {
        return new self('Zadejte prosím obě hodnoty skóre po prodloužení.');
    }

    public static function overtimeWithoutDraw(): self
    {
        return new self('Skóre po prodloužení lze zadat jen při remíze v základní hrací době.');
    }

    public static function overtimeNotPlayedInSource(): self
    {
        return new self('V tomto zdroji zápasů se prodloužení nehraje, remíza je konečný výsledek.');
    }

    public static function overtimeNotDrawPlusOne(): self
    {
        return new self('Výsledek po prodloužení či penaltách se zapisuje jako remíza plus jeden gól pro vítěze, např. 2:2 → 3:2 nebo 2:3.');
    }
}
