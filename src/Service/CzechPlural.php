<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Czech noun/adjective declension by count — the shared source of truth for
 * notification copy (and templates, via {@see \App\Twig\CzechPluralExtension}).
 * Czech agreement is three-way for most count constructions: 1, the paucal 2–4,
 * and 0 / 5+; the „z N …" (genitive) construction is a simpler 1 vs many split.
 * Mirrors {@see Credits\CreditsWord} for the „kredit" noun.
 */
final class CzechPlural
{
    /** „zápas" (1) / „zápasy" (2–4) / „zápasů" (0, 5+). Nominative count noun. */
    public static function zapas(int $count): string
    {
        return match (true) {
            1 === $count => 'zápas',
            $count >= 2 && $count <= 4 => 'zápasy',
            default => 'zápasů',
        };
    }

    /**
     * „tip" (1) / „tipy" (else) — the subject noun in „chybí vám tip/tipy na N
     * zápasů", agreeing with the (match) count as one tip per match.
     */
    public static function tip(int $count): string
    {
        return 1 === $count ? 'tip' : 'tipy';
    }

    /**
     * Genitive after a numeral („z N hráčů"): „hráče" (1) / „hráčů" (else) — e.g.
     * „1. místo z 1 hráče", „3. místo z 8 hráčů".
     */
    public static function hracu(int $count): string
    {
        return 1 === $count ? 'hráče' : 'hráčů';
    }

    /**
     * Neuter agreement with „oznámení": „nepřečtené" (1) / „nepřečtená" (2–4) /
     * „nepřečtených" (0, 5+) — the bell's unread label.
     */
    public static function neprectene(int $count): string
    {
        return match (true) {
            1 === $count => 'nepřečtené',
            $count >= 2 && $count <= 4 => 'nepřečtená',
            default => 'nepřečtených',
        };
    }
}
