<?php

declare(strict_types=1);

namespace App\Query\GetMatchSourceRuleConfiguration;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<MatchSourceRuleConfigurationResult>
 */
final readonly class GetMatchSourceRuleConfiguration implements QueryMessage
{
    public function __construct(
        public Uuid $matchSourceId,
    ) {
    }
}
