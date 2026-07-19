<?php

declare(strict_types=1);

namespace App\Service;

/**
 * The app stores every timestamp in UTC but reasons about "days" in the user's
 * wall-clock zone (Europe/Prague) — a tipovačka's natural round is a Prague
 * calendar day. Pure by design: callers pass an instant obtained from the
 * injected {@see \Psr\Clock\ClockInterface} (MockClock in tests), never
 * `new \DateTimeImmutable()`.
 */
final class PragueCalendar
{
    public const string TIMEZONE = 'Europe/Prague';

    public static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(self::TIMEZONE);
    }

    /**
     * The Prague calendar day of the given instant, as a Prague-midnight date
     * (time stripped). Persisted into a DATE column via its `Y-m-d`, so an
     * evaluation at 23:30 Prague (= 21:30/22:30 UTC) lands on the Prague day,
     * not the UTC one.
     */
    public static function day(\DateTimeInterface $moment): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($moment)
            ->setTimezone(self::timezone())
            ->setTime(0, 0);
    }
}
