<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Query\ListRegisteredRules\ListRegisteredRules;
use App\Tests\Support\IntegrationTestCase;

final class ListRegisteredRulesQueryTest extends IntegrationTestCase
{
    public function testReturnsAllRegisteredRules(): void
    {
        $result = $this->queryBus()->handle(new ListRegisteredRules());

        self::assertCount(4, $result);

        $identifiers = array_map(static fn ($item) => $item->identifier, $result);

        self::assertContains('exact_score', $identifiers);
        self::assertContains('correct_outcome', $identifiers);
        self::assertContains('correct_home_goals', $identifiers);
        self::assertContains('correct_away_goals', $identifiers);
    }

    public function testEachItemHasLabelAndDefaultPoints(): void
    {
        $result = $this->queryBus()->handle(new ListRegisteredRules());

        foreach ($result as $item) {
            self::assertNotSame('', $item->label);
            self::assertNotSame('', $item->description);
            self::assertGreaterThan(0, $item->defaultPoints);
        }
    }
}
