<?php

declare(strict_types=1);

namespace App\Tests\Unit\Value;

use App\Value\TeamMonogram;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TeamMonogramTest extends TestCase
{
    #[DataProvider('initialsProvider')]
    public function testInitials(string $name, string $expected): void
    {
        self::assertSame($expected, TeamMonogram::forName($name)->initials);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function initialsProvider(): iterable
    {
        yield 'two words' => ['Sparta Praha', 'SP'];
        yield 'single word takes first three' => ['Sparta', 'SPA'];
        yield 'three words capped at three' => ['Real Madrid Club', 'RMC'];
        yield 'four words still capped' => ['A B C D', 'ABC'];
        yield 'diacritics upper-cased' => ['Žižkov', 'ŽIŽ'];
        yield 'collapses extra spaces' => ['  Baník   Ostrava ', 'BO'];
        yield 'empty falls back' => ['', '?'];
    }

    public function testBrandColorIsUsedAsBackground(): void
    {
        $monogram = TeamMonogram::forName('Sparta', '#EE1C25');

        self::assertSame('#EE1C25', $monogram->background);
    }

    public function testShortHexIsExpanded(): void
    {
        self::assertSame('#FFFFFF', TeamMonogram::forName('X', '#fff')->background);
    }

    public function testDarkBackgroundGetsLightForeground(): void
    {
        self::assertSame('#FFFFFF', TeamMonogram::forName('X', '#000000')->foreground);
    }

    public function testLightBackgroundGetsDarkForeground(): void
    {
        self::assertSame('#0B1220', TeamMonogram::forName('X', '#FFFFFF')->foreground);
    }

    public function testInvalidBrandColorFallsBackToDerivedColor(): void
    {
        $monogram = TeamMonogram::forName('Sparta', 'not-a-color');

        self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $monogram->background);
    }

    public function testDerivedColorIsDeterministicAndCaseInsensitive(): void
    {
        self::assertSame(
            TeamMonogram::forName('Sparta Praha')->background,
            TeamMonogram::forName('sparta praha')->background,
        );
    }

    public function testForegroundAlwaysHasReadableContrast(): void
    {
        // Every palette-derived background must yield a foreground with WCAG AA (>= 4.5) contrast.
        $names = [
            'Sparta', 'Slavia', 'Plzeň', 'Baník', 'Bohemians', 'Jablonec', 'Real Madrid',
            'Barcelona', 'Teplice', 'Liberec', 'Olomouc', 'Hradec', 'Zlín', 'Karviná',
            'Pardubice', 'Mladá Boleslav', 'Tygři', 'Lvi', 'Orli', 'Draci', 'Kometa', 'Oceláři',
        ];

        foreach ($names as $name) {
            $monogram = TeamMonogram::forName($name);
            self::assertGreaterThanOrEqual(
                4.5,
                self::contrast($monogram->background, $monogram->foreground),
                sprintf('"%s" (%s on %s) is below AA', $name, $monogram->foreground, $monogram->background),
            );
        }
    }

    private static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(
            static function (float $c): float {
                $c /= 255;

                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            [
                (float) hexdec(substr($hex, 1, 2)),
                (float) hexdec(substr($hex, 3, 2)),
                (float) hexdec(substr($hex, 5, 2)),
            ],
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
