<?php

declare(strict_types=1);

namespace App\Twig;

use App\Value\Country;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Resolves a stored ISO 3166-1 alpha-2 code into the `Country` value object, so
 * templates can render its Czech name and its round flag asset. Returns null for
 * null/blank/unknown input, letting call sites use it unconditionally.
 *
 * Rendering lives in <twig:CountryFlag>; this only does the lookup.
 */
final class CountryExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('country', Country::tryFrom(...)),
        ];
    }
}
