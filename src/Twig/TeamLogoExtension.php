<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Team\TeamLogoStorage;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Turns the storage path kept in `Team.logo` into a URL an <img> can use.
 * Null-safe, so templates pipe `team.logo` through it without a guard.
 */
final class TeamLogoExtension extends AbstractExtension
{
    public function __construct(
        private readonly TeamLogoStorage $logoStorage,
    ) {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('team_logo_url', $this->logoStorage->url(...)),
        ];
    }
}
