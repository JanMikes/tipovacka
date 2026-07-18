<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchSourceVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Veřejný',
            self::Private => 'Soukromý',
        };
    }
}
