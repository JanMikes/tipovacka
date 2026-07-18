<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\RecalculateMatchSourcePoints\RecalculateMatchSourcePointsCommand;
use App\Command\UpdateMatchSourceRuleConfiguration\UpdateMatchSourceRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class MatchSourceRulesChangedTriggersRecalcTest extends IntegrationTestCase
{
    public function testRecalcIsDispatchedWhenEvaluationsExist(): void
    {
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $async->reset();

        $matchSourceId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);

        // Fixture has an evaluation for PUBLIC_SOURCE, so the rules change must trigger a recalc.
        $this->commandBus()->dispatch(new UpdateMatchSourceRuleConfigurationCommand(
            matchSourceId: $matchSourceId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 12],
            ],
        ));

        $envelopes = $async->getSent();

        $recalcCommands = array_filter(
            $envelopes,
            fn ($envelope) => $envelope->getMessage() instanceof RecalculateMatchSourcePointsCommand,
        );

        self::assertCount(1, $recalcCommands);
    }

    public function testNoRecalcWhenNoEvaluationsExist(): void
    {
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $async->reset();

        // PRIVATE_SOURCE has no evaluations at fixture time.
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);

        $this->commandBus()->dispatch(new UpdateMatchSourceRuleConfigurationCommand(
            matchSourceId: $matchSourceId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 12],
            ],
        ));

        $envelopes = $async->getSent();

        $recalcCommands = array_filter(
            $envelopes,
            fn ($envelope) => $envelope->getMessage() instanceof RecalculateMatchSourcePointsCommand,
        );

        self::assertCount(0, $recalcCommands);
    }
}
