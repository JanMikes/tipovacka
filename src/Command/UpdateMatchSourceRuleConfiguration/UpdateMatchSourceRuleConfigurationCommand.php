<?php

declare(strict_types=1);

namespace App\Command\UpdateMatchSourceRuleConfiguration;

use Symfony\Component\Uid\Uuid;

/**
 * @phpstan-type RuleChange array{enabled: bool, points: int}
 */
final readonly class UpdateMatchSourceRuleConfigurationCommand
{
    /**
     * @param array<string, array{enabled: bool, points: int}> $changes
     */
    public function __construct(
        public Uuid $matchSourceId,
        public Uuid $editorId,
        public array $changes,
    ) {
    }
}
