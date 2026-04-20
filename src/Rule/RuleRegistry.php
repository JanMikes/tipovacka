<?php

declare(strict_types=1);

namespace App\Rule;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class RuleRegistry
{
    /**
     * @var array<string, RuleInterface>
     */
    private array $rules;

    /**
     * @param iterable<RuleInterface> $rules
     */
    public function __construct(
        #[AutowireIterator('app.rule')]
        iterable $rules,
    ) {
        $indexed = [];

        foreach ($rules as $rule) {
            $identifier = $rule->getIdentifier();

            if (array_key_exists($identifier, $indexed)) {
                throw new \LogicException(sprintf('Duplicate rule identifier "%s" detected. Each rule must have a unique identifier.', $identifier));
            }

            $indexed[$identifier] = $rule;
        }

        $this->rules = $indexed;
    }

    /**
     * @return array<string, RuleInterface>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function get(string $identifier): RuleInterface
    {
        return $this->rules[$identifier]
            ?? throw new \LogicException(sprintf('Rule with identifier "%s" is not registered.', $identifier));
    }
}
