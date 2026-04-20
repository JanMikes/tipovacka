<?php

declare(strict_types=1);

namespace App\Query\GetTournamentRuleConfiguration;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<TournamentRuleConfigurationResult>
 */
final readonly class GetTournamentRuleConfiguration implements QueryMessage
{
    public function __construct(
        public Uuid $tournamentId,
    ) {
    }
}
