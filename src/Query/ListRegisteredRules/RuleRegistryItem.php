<?php

declare(strict_types=1);

namespace App\Query\ListRegisteredRules;

final readonly class RuleRegistryItem
{
    public function __construct(
        public string $identifier,
        public string $label,
        public string $description,
        public int $defaultPoints,
    ) {
    }
}
