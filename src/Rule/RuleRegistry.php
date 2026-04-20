<?php

declare(strict_types=1);

namespace App\Rule;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class RuleRegistry
{
    /**
     * @var array<string, Rule>
     */
    private array $rules;

    /**
     * @param iterable<Rule> $rules
     */
    public function __construct(
        #[AutowireIterator('app.rule')]
        iterable $rules,
    ) {
        $indexed = [];

        foreach ($rules as $rule) {
            if (array_key_exists($rule->identifier, $indexed)) {
                throw new \LogicException(sprintf('Duplicate rule identifier "%s" detected. Each rule must have a unique identifier.', $rule->identifier));
            }

            $indexed[$rule->identifier] = $rule;
        }

        $this->rules = $indexed;
    }

    /**
     * @return array<string, Rule>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function get(string $identifier): Rule
    {
        return $this->rules[$identifier]
            ?? throw new \LogicException(sprintf('Rule with identifier "%s" is not registered.', $identifier));
    }
}
