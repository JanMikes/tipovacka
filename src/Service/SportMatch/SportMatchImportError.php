<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

final readonly class SportMatchImportError
{
    public function __construct(
        public int $rowNumber,
        public string $column,
        public string $message,
    ) {
    }
}
