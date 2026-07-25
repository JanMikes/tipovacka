<?php

declare(strict_types=1);

namespace App\Value;

/**
 * The visual identity of a team when it has no uploaded logo (v1: always).
 *
 * A colored circle with 1–3 initials. The background is the team's brand color
 * when set, otherwise a stable color derived from the name; the foreground is
 * ALWAYS chosen for readable contrast against that background (WCAG), so text is
 * never illegible regardless of which color a team picks. Pure + deterministic
 * so it can be unit-tested and rendered identically server-side everywhere.
 */
final readonly class TeamMonogram
{
    /**
     * Dark-UI-friendly palette; a deliberate mix of dark and light hues so the
     * contrast logic picks both white and dark ink. Every entry clears WCAG AA
     * (>= 4.5) against its computed foreground — verified in TeamMonogramTest.
     */
    private const array PALETTE = [
        '#2563EB', '#4F46E5', '#7C3AED', '#9333EA', '#C026D3', '#E11D48',
        '#DC2626', '#EA580C', '#CA8A04', '#65A30D', '#059669', '#0891B2',
    ];

    private const string DARK_INK = '#0B1220';
    private const string LIGHT_INK = '#FFFFFF';

    public function __construct(
        /** 1–3 upper-cased letters. */
        public string $initials,
        /** #RRGGBB circle background. */
        public string $background,
        /** #RRGGBB text color with readable contrast against $background. */
        public string $foreground,
    ) {
    }

    public static function forName(string $name, ?string $brandColor = null): self
    {
        $background = self::normalizeHex($brandColor) ?? self::derivedColor($name);

        return new self(
            initials: self::initialsOf($name),
            background: $background,
            foreground: self::readableInkFor($background),
        );
    }

    private static function initialsOf(string $name): string
    {
        $name = trim($name);

        if ('' === $name) {
            return '?';
        }

        $words = array_values(array_filter(
            preg_split('/\s+/u', $name) ?: [],
            static fn (string $word): bool => '' !== trim($word),
        ));

        if (count($words) > 1) {
            $initials = implode('', array_map(
                static fn (string $word): string => mb_substr($word, 0, 1),
                $words,
            ));

            return mb_strtoupper(mb_substr($initials, 0, 3));
        }

        return mb_strtoupper(mb_substr($name, 0, 3));
    }

    /** Stable per-name pick from the palette (case-insensitive). */
    private static function derivedColor(string $name): string
    {
        $index = crc32(mb_strtolower(trim($name))) % count(self::PALETTE);

        return self::PALETTE[$index];
    }

    /** Accepts #RGB or #RRGGBB (any case); returns #RRGGBB upper-cased, or null when invalid. */
    private static function normalizeHex(?string $hex): ?string
    {
        if (null === $hex) {
            return null;
        }

        $hex = trim($hex);

        if (1 === preg_match('/^#([0-9a-fA-F]{6})$/', $hex)) {
            return mb_strtoupper($hex);
        }

        if (1 === preg_match('/^#([0-9a-fA-F]{3})$/', $hex)) {
            $r = $hex[1];
            $g = $hex[2];
            $b = $hex[3];

            return mb_strtoupper('#'.$r.$r.$g.$g.$b.$b);
        }

        return null;
    }

    /** Pick black-ink or white-ink by whichever yields the higher WCAG contrast ratio. */
    private static function readableInkFor(string $background): string
    {
        $bg = self::relativeLuminance($background);
        $dark = self::relativeLuminance(self::DARK_INK);
        $light = self::relativeLuminance(self::LIGHT_INK);

        return self::contrastRatio($dark, $bg) >= self::contrastRatio($light, $bg)
            ? self::DARK_INK
            : self::LIGHT_INK;
    }

    private static function contrastRatio(float $a, float $b): float
    {
        $lighter = max($a, $b);
        $darker = min($a, $b);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** WCAG 2.x relative luminance of a #RRGGBB color. */
    private static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::toLinear($hex);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * @return array{float, float, float}
     */
    private static function toLinear(string $hex): array
    {
        $channels = [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];

        return array_map(
            static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $channels,
        );
    }
}
