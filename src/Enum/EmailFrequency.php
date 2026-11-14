<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How often a player wants to hear from us by e-mail — ONE knob above the
 * per-type switches, because „too many mails" is a complaint about the
 * envelope count, not about any single type.
 *
 * It only ever shapes the e-mail channel; the in-app feed is untouched. The
 * rules it stands for live in {@see \App\Service\Notification\NotificationPolicy}.
 */
enum EmailFrequency: string
{
    /** Things arrive as they happen (bursts are still collapsed hourly). */
    case Immediate = 'immediate';

    /** At most ONE mail a day, at the player's hour, grouped by soutěž. */
    case Daily = 'daily';

    /** No notification e-mail at all. Account mail (password, receipts) still goes. */
    case Never = 'never';

    /**
     * What a player gets until they choose. Daily, not Immediate: a tipping game
     * whose default is a mail per event teaches people to reach for „spam".
     */
    public static function default(): self
    {
        return self::Daily;
    }

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Průběžně',
            self::Daily => 'Jednou denně',
            self::Never => 'Žádné e-maily',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Immediate => 'E-mail přijde hned, jak se něco stane. Může jich být několik denně.',
            self::Daily => 'Nejvýš jeden souhrn denně — chybějící tipy, výsledky a novinky pohromadě, seřazené podle soutěží.',
            self::Never => 'Oznámení uvidíte jen v aplikaci. E-maily k účtu (heslo, platby) chodí dál.',
        };
    }
}
