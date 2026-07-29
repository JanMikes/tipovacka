<?php

declare(strict_types=1);

namespace App\Query\ListBrowsableCompetitions;

use Symfony\Component\Uid\Uuid;

final readonly class SportFilterOption
{
    public function __construct(
        public Uuid $sportId,
        public string $name,
    ) {
    }
}
