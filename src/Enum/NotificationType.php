<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The kinds of notification the app can deliver. Each type carries its Czech
 * {@see label} + {@see description} (for the preferences matrix), its default
 * channel policy ({@see defaultInApp} / {@see defaultEmail} — used when the user
 * has no {@see \App\Entity\NotificationPreference} row for the type), plus UI
 * {@see icon} / {@see tone} for the feed. Email defaults on only for the
 * important types (guess reminder, premium problems, competition ended, boost
 * refunded) per .docs/DOMAIN.md §Notifications; in-app defaults on for all.
 */
enum NotificationType: string
{
    case GuessReminder = 'guess_reminder';
    case MatchAdded = 'match_added';
    case MatchEvaluated = 'match_evaluated';
    case CompetitionEnded = 'competition_ended';
    case PremiumBalanceLow = 'premium_balance_low';
    case PremiumChargeUncovered = 'premium_charge_uncovered';
    case PremiumDowngraded = 'premium_downgraded';
    case PremiumEnabled = 'premium_enabled';
    case BoostRefunded = 'boost_refunded';
    case MemberJoined = 'member_joined';

    public function label(): string
    {
        return match ($this) {
            self::GuessReminder => 'Připomínka tipů',
            self::MatchAdded => 'Nový zápas v soutěži',
            self::MatchEvaluated => 'Vyhodnocení zápasu',
            self::CompetitionEnded => 'Konec soutěže',
            self::PremiumBalanceLow => 'Nízký zůstatek kreditů',
            self::PremiumChargeUncovered => 'Nepokrytá prémiová platba',
            self::PremiumDowngraded => 'Zrušení prémia',
            self::PremiumEnabled => 'Prémium potvrzeno',
            self::BoostRefunded => 'Vrácení vylepšení',
            self::MemberJoined => 'Nový hráč v soutěži',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GuessReminder => 'Blíží se uzávěrka a ještě vám chybí tipy na některé zápasy.',
            self::MatchAdded => 'Do vaší běžící soutěže přibyl nový zápas (např. play-off).',
            self::MatchEvaluated => 'Zápas byl vyhodnocen — kolik bodů jste získali a jak si stojíte.',
            self::CompetitionEnded => 'Soutěž skončila — vaše konečné umístění a body.',
            self::PremiumBalanceLow => 'Zůstatek vaší peněženky klesl nízko (týká se prémiových soutěží, které spravujete).',
            self::PremiumChargeUncovered => 'Prémiovou platbu za nového hráče se nepodařilo strhnout — dobijte kredity.',
            self::PremiumDowngraded => 'Prémium ve vaší soutěži bylo zrušeno kvůli nepokryté platbě a přepnuto na vylepšení.',
            self::PremiumEnabled => 'Prémium ve vaší soutěži bylo při startu potvrzeno.',
            self::BoostRefunded => 'Vaše zakoupené vylepšení bylo vráceno zpět do peněženky.',
            self::MemberJoined => 'Do soutěže, kterou spravujete, se připojil nový hráč.',
        };
    }

    /** Whether an in-app feed row is written when the user has no explicit preference. */
    public function defaultInApp(): bool
    {
        return true;
    }

    /** Whether an email is sent when the user has no explicit preference. */
    public function defaultEmail(): bool
    {
        return match ($this) {
            self::GuessReminder,
            self::CompetitionEnded,
            self::PremiumBalanceLow,
            self::PremiumChargeUncovered,
            self::PremiumDowngraded,
            self::BoostRefunded => true,
            self::MatchAdded,
            self::MatchEvaluated,
            self::PremiumEnabled,
            self::MemberJoined => false,
        };
    }

    /** Lucide icon name (without the `lucide:` prefix) for the feed row. */
    public function icon(): string
    {
        return match ($this) {
            self::GuessReminder => 'clock',
            self::MatchAdded => 'calendar',
            self::MatchEvaluated => 'circle-check-big',
            self::CompetitionEnded => 'trophy',
            self::PremiumBalanceLow => 'wallet',
            self::PremiumChargeUncovered => 'triangle-alert',
            self::PremiumDowngraded => 'chevron-down',
            self::PremiumEnabled => 'crown',
            self::BoostRefunded => 'rotate-ccw',
            self::MemberJoined => 'user-round-plus',
        };
    }

    /** Tailwind text-color utility class used to tint the feed row icon. */
    public function tone(): string
    {
        return match ($this) {
            self::GuessReminder => 'text-draw',
            self::MatchAdded => 'text-accent-300',
            self::MatchEvaluated => 'text-win',
            self::CompetitionEnded => 'text-[#f5cd54]',
            self::PremiumBalanceLow, self::PremiumChargeUncovered, self::PremiumDowngraded => 'text-loss',
            self::PremiumEnabled => 'text-[#f5cd54]',
            self::BoostRefunded => 'text-accent-300',
            self::MemberJoined => 'text-accent-300',
        };
    }
}
