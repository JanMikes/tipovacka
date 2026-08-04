<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Player\PlayerNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerNameNormalizerTest extends TestCase
{
    private PlayerNameNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PlayerNameNormalizer();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function matchingPairs(): iterable
    {
        yield 'identical' => ['Jan Novák', 'Jan Novák'];
        yield 'case only' => ['jan novák', 'Jan Novák'];
        yield 'diacritics stripped' => ['Jan Novak', 'Jan Novák'];
        yield 'extra whitespace' => ['Jan  Novák ', 'Jan Novák'];
        yield 'initial form with dot' => ['J. Novák', 'Jan Novák'];
        yield 'initial form without dot' => ['J Novák', 'Jan Novák'];
        yield 'initial form reversed direction' => ['Jan Novák', 'J. Novák'];
        yield 'initial form plus diacritics' => ['J. Novak', 'Jan Novák'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonMatchingPairs(): iterable
    {
        yield 'different surname' => ['Jan Novák', 'Jan Novotný'];
        yield 'different initial' => ['P. Novák', 'Jan Novák'];
        yield 'surname alone vs full name' => ['Novák', 'Jan Novák'];
        yield 'typo is not fuzzy-matched' => ['Jan Nowák', 'Jan Novák'];
        yield 'empty never matches' => ['', 'Jan Novák'];
        yield 'multi-token surname must be identical' => ['J. van Dijk', 'Jan de Dijk'];
    }

    #[DataProvider('matchingPairs')]
    public function testMatches(string $a, string $b): void
    {
        self::assertTrue($this->normalizer->matches($a, $b));
    }

    #[DataProvider('nonMatchingPairs')]
    public function testDoesNotMatch(string $a, string $b): void
    {
        self::assertFalse($this->normalizer->matches($a, $b));
    }

    public function testNormalizeStripsCaseWhitespaceDotsAndDiacritics(): void
    {
        self::assertSame('jan novak', $this->normalizer->normalize('  Jan   NOVÁK '));
        self::assertSame('j novak', $this->normalizer->normalize('J. Novák'));
    }
}
