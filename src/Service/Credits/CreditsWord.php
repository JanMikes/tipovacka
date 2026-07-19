<?php

declare(strict_types=1);

namespace App\Service\Credits;

/**
 * Czech declension of the noun „kredit" for a given count — the single source of
 * truth shared by the `|credits` Twig filter ({@see \App\Twig\PricingExtension})
 * and controller flash messages. Follows the same 1 / 2–4 / 0+5+ pattern used
 * elsewhere in the UI (e.g. hráč / hráči / hráčů).
 */
final class CreditsWord
{
    /**
     * The declined noun only: 1 → „kredit", 2–4 → „kredity", 0 & 5+ → „kreditů".
     */
    public static function plural(int $count): string
    {
        return match (true) {
            1 === $count => 'kredit',
            $count >= 2 && $count <= 4 => 'kredity',
            default => 'kreditů',
        };
    }

    /**
     * The full phrase: number + declined noun, e.g. „1 kredit", „2 kredity",
     * „50 kreditů".
     */
    public static function format(int $count): string
    {
        return $count.' '.self::plural($count);
    }
}
