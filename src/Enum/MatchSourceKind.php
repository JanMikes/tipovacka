<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchSourceKind: string
{
    case Curated = 'curated';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Curated => 'Kurátorovaný',
            self::Private => 'Soukromý',
        };
    }
}
