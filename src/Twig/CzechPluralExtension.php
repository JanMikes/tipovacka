<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\CzechPlural;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Exposes {@see CzechPlural} to templates so Czech declension has ONE source of
 * truth across PHP and Twig. `{{ count }} {{ count|czech_unread }}` →
 * „1 nepřečtené" / „3 nepřečtená" / „7 nepřečtených"; `{{ count|czech_zapas }}` →
 * „1 zápas" / „3 zápasy" / „7 zápasů".
 */
final class CzechPluralExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('czech_unread', CzechPlural::neprectene(...)),
            new TwigFilter('czech_zapas', CzechPlural::zapas(...)),
        ];
    }
}
