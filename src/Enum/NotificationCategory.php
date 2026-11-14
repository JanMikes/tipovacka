<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The headings the preference matrix groups its ten {@see NotificationType}
 * rows under — purely presentational, nothing is switched per category.
 */
enum NotificationCategory: string
{
    case Tipping = 'tipping';
    case Results = 'results';
    case Organizer = 'organizer';

    public function label(): string
    {
        return match ($this) {
            self::Tipping => 'Tipování',
            self::Results => 'Výsledky',
            self::Organizer => 'Správa soutěže a kredity',
        };
    }

    /**
     * @return list<NotificationType>
     */
    public function types(): array
    {
        return array_values(array_filter(
            NotificationType::cases(),
            fn (NotificationType $type): bool => $type->category() === $this,
        ));
    }
}
