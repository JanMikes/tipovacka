<?php

declare(strict_types=1);

namespace App\Value;

/**
 * One row of the soutěž switcher (<twig:SoutezSwitcher>), already flattened for
 * rendering. The control can be fed from more than one read model — „my soutěže"
 * (`CompetitionListItem`) or, logged out, the public competition list — and those
 * DTOs name their fields differently, so everything is normalised into this shape.
 *
 * Build it with {@see self::fromDates()}: source dates are stored UTC and the UI is
 * Europe/Prague wall clock, so the range must never be formatted straight from UTC.
 */
final readonly class CompetitionSwitcherOption
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';

    public function __construct(
        /** RFC4122 competition id — the submitted <option> value. */
        public string $id,
        public string $name,
        /** Second line in the dropdown: the zdroj zápasů (or the sport, publicly). */
        public string $subtitle,
        /** „2. 6. 2026 – 11. 6. 2026", „od 2. 6. 2026", „do 11. 6. 2026" or ''. */
        public string $dateRange,
        /** Drives the grouping: false → „Probíhající", true → „Ukončené". */
        public bool $isFinished,
    ) {
    }

    public static function fromDates(
        string $id,
        string $name,
        string $subtitle,
        ?\DateTimeImmutable $startAt,
        ?\DateTimeImmutable $endAt,
        bool $isFinished,
    ): self {
        return new self(
            id: $id,
            name: $name,
            subtitle: $subtitle,
            dateRange: self::formatRange($startAt, $endAt),
            isFinished: $isFinished,
        );
    }

    private static function formatRange(?\DateTimeImmutable $startAt, ?\DateTimeImmutable $endAt): string
    {
        $timezone = new \DateTimeZone(self::PRAGUE_TIMEZONE);
        $start = $startAt?->setTimezone($timezone)->format('j. n. Y');
        $end = $endAt?->setTimezone($timezone)->format('j. n. Y');

        if (null !== $start && null !== $end) {
            return $start === $end ? $start : $start.' – '.$end;
        }

        if (null !== $start) {
            return 'od '.$start;
        }

        if (null !== $end) {
            return 'do '.$end;
        }

        return '';
    }
}
