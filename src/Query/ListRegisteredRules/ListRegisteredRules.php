<?php

declare(strict_types=1);

namespace App\Query\ListRegisteredRules;

use App\Query\QueryMessage;

/**
 * @implements QueryMessage<list<RuleRegistryItem>>
 */
final readonly class ListRegisteredRules implements QueryMessage
{
}
