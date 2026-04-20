<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

final readonly class SportMatchImportPreview
{
    /**
     * @param list<SportMatchImportRow>   $validRows
     * @param list<SportMatchImportError> $errors
     */
    public function __construct(
        public array $validRows,
        public array $errors,
    ) {
    }
}
