<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Rule\RuleInterface;
use App\Rule\RuleRegistry;
use PHPUnit\Framework\TestCase;

final class RuleRegistryTest extends TestCase
{
    public function testEmptyRegistryReturnsEmptyArray(): void
    {
        self::assertSame([], (new RuleRegistry([]))->all());
    }

    public function testRegistryIndexesRulesByIdentifier(): void
    {
        $rule = $this->makeRule('test_rule');
        $registry = new RuleRegistry([$rule]);

        self::assertSame(['test_rule' => $rule], $registry->all());
    }

    public function testGetReturnsRegisteredRule(): void
    {
        $rule = $this->makeRule('my_rule');

        self::assertSame($rule, (new RuleRegistry([$rule]))->get('my_rule'));
    }

    public function testGetThrowsForUnknownIdentifier(): void
    {
        $this->expectException(\LogicException::class);
        (new RuleRegistry([]))->get('nonexistent');
    }

    public function testDuplicateIdentifierThrowsOnConstruction(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Duplicate rule identifier "duplicate"');

        new RuleRegistry([$this->makeRule('duplicate'), $this->makeRule('duplicate')]);
    }

    private function makeRule(string $identifier): RuleInterface
    {
        return new class ($identifier) implements RuleInterface {
            public function __construct(private readonly string $id)
            {
            }

            public function getIdentifier(): string
            {
                return $this->id;
            }

            public function getLabel(): string
            {
                return 'Test';
            }

            public function getDescription(): string
            {
                return 'Test rule';
            }

            public function getDefaultPoints(): int
            {
                return 1;
            }

            public function evaluate(Guess $guess, SportMatch $match): int
            {
                return 0;
            }
        };
    }
}
