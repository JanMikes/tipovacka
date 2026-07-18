<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetMatchSourceRuleConfiguration\GetMatchSourceRuleConfiguration;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetMatchSourceRuleConfigurationQueryTest extends IntegrationTestCase
{
    public function testReturnsAllRegisteredRulesWithMatchSourceConfig(): void
    {
        $result = $this->queryBus()->handle(new GetMatchSourceRuleConfiguration(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
        ));

        self::assertCount(4, $result->items);

        $identifiers = array_map(fn ($item) => $item->identifier, $result->items);
        self::assertContains('exact_score', $identifiers);
        self::assertContains('correct_outcome', $identifiers);
        self::assertContains('correct_home_goals', $identifiers);
        self::assertContains('correct_away_goals', $identifiers);

        foreach ($result->items as $item) {
            self::assertTrue($item->enabled);
            self::assertSame($item->defaultPoints, $item->points);
        }
    }

    public function testReportsEvaluationCount(): void
    {
        $result = $this->queryBus()->handle(new GetMatchSourceRuleConfiguration(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
        ));

        self::assertSame(1, $result->evaluationCount);
    }
}
