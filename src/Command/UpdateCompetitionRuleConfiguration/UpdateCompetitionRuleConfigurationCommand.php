<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionRuleConfiguration;

use Symfony\Component\Uid\Uuid;

/**
 * @phpstan-type RuleChange array{enabled: bool, points: int}
 */
final readonly class UpdateCompetitionRuleConfigurationCommand
{
    /**
     * @param array<string, array{enabled: bool, points: int}> $changes
     */
    public function __construct(
        public Uuid $competitionId,
        public Uuid $editorId,
        public array $changes,
    ) {
    }
}
