<?php

declare(strict_types=1);

namespace App\Twig;

use Psr\Clock\ClockInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * `{{ someDate|czech_ago }}` → a short Czech relative label („právě teď",
 * „před 5 min", „před 2 h", „včera", „před 3 dny"), falling back to an absolute
 * „j. n. Y" for anything older than a week. „Now" comes from the clock so the
 * output is deterministic under the test MockClock.
 */
final class CzechDateExtension extends AbstractExtension
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('czech_ago', $this->ago(...)),
        ];
    }

    public function ago(\DateTimeInterface $moment): string
    {
        $now = $this->clock->now();
        $seconds = $now->getTimestamp() - $moment->getTimestamp();

        if ($seconds < 0) {
            $seconds = 0;
        }

        if ($seconds < 60) {
            return 'právě teď';
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return sprintf('před %d min', $minutes);
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 24) {
            return sprintf('před %d h', $hours);
        }

        $days = intdiv($hours, 24);

        if (1 === $days) {
            return 'včera';
        }

        if ($days < 7) {
            return sprintf('před %d dny', $days);
        }

        return \DateTimeImmutable::createFromInterface($moment)
            ->setTimezone(new \DateTimeZone(self::PRAGUE_TIMEZONE))
            ->format('j. n. Y');
    }
}
