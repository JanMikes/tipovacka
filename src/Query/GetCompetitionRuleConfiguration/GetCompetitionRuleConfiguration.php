<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionRuleConfiguration;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<CompetitionRuleConfigurationResult>
 */
final readonly class GetCompetitionRuleConfiguration implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
    ) {
    }
}
