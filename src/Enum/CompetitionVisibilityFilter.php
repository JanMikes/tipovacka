<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * „Viditelnost" chips of the shared competition filter bar (item 07). Rendered
 * for the organizer list only — the public/global list is, by definition, all
 * {@see Public}. Declaration order IS chip order.
 */
enum CompetitionVisibilityFilter: string
{
    case All = 'vse';
    /** Global competitions — publicly listed, joinable by anyone. */
    case Public = 'verejne';
    /** Everything else — reachable only by PIN, link or e-mail invitation. */
    case Private = 'neverejne';

    public static function fromRequest(?string $value): self
    {
        return (null !== $value ? self::tryFrom($value) : null) ?? self::All;
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'Všechny',
            self::Public => 'Veřejné',
            self::Private => 'Neveřejné',
        };
    }
}
