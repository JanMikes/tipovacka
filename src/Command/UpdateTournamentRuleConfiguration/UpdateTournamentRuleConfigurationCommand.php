<?php

declare(strict_types=1);

namespace App\Command\UpdateTournamentRuleConfiguration;

use Symfony\Component\Uid\Uuid;

/**
 * @phpstan-type RuleChange array{enabled: bool, points: int}
 */
final readonly class UpdateTournamentRuleConfigurationCommand
{
    /**
     * @param array<string, array{enabled: bool, points: int}> $changes
     */
    public function __construct(
        public Uuid $tournamentId,
        public Uuid $editorId,
        public array $changes,
    ) {
    }
}
