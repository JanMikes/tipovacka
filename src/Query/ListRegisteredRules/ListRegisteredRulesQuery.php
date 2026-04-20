<?php

declare(strict_types=1);

namespace App\Query\ListRegisteredRules;

use App\Rule\RuleRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListRegisteredRulesQuery
{
    public function __construct(
        private RuleRegistry $ruleRegistry,
    ) {
    }

    /**
     * @return list<RuleRegistryItem>
     */
    public function __invoke(ListRegisteredRules $query): array
    {
        $items = [];

        foreach ($this->ruleRegistry->all() as $rule) {
            $items[] = new RuleRegistryItem(
                identifier: $rule->identifier,
                label: $rule->label,
                description: $rule->description,
                defaultPoints: $rule->defaultPoints,
            );
        }

        return $items;
    }
}
