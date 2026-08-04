<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Enum\FeedProvider;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Resolves the adapter for a source's FeedProvider. Null when no adapter is
 * implemented yet — app:matches:sync skips such sources with a warning, so a
 * provider can be configured on a source before its adapter ships.
 */
final readonly class MatchDataProviderRegistry
{
    /** @param iterable<MatchDataProvider> $providers */
    public function __construct(
        #[AutowireIterator('app.match_data_provider')]
        private iterable $providers,
    ) {
    }

    public function providerFor(FeedProvider $provider): ?MatchDataProvider
    {
        foreach ($this->providers as $candidate) {
            if ($candidate::provides() === $provider) {
                return $candidate;
            }
        }

        return null;
    }
}
