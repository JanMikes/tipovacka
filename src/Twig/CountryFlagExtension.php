<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Turns an ISO 3166-1 alpha-2 country code into its flag emoji, e.g.
 * „CZ" → 🇨🇿. Returns '' for null/invalid input so templates can render it
 * unconditionally. Used by the TeamFlag „coin" to badge a team's country.
 */
final class CountryFlagExtension extends AbstractExtension
{
    private const int REGIONAL_INDICATOR_A = 0x1F1E6;

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('country_flag', $this->toFlag(...)),
        ];
    }

    public function toFlag(?string $code): string
    {
        if (null === $code) {
            return '';
        }

        $code = strtoupper(trim($code));

        if (1 !== preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }

        return mb_chr(self::REGIONAL_INDICATOR_A + (ord($code[0]) - ord('A')))
            .mb_chr(self::REGIONAL_INDICATOR_A + (ord($code[1]) - ord('A')));
    }
}
