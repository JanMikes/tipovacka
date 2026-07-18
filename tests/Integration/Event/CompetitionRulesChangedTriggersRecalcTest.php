<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\RecalculateCompetitionPoints\RecalculateCompetitionPointsCommand;
use App\Command\UpdateCompetitionRuleConfiguration\UpdateCompetitionRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class CompetitionRulesChangedTriggersRecalcTest extends IntegrationTestCase
{
    public function testRecalcIsDispatchedWhenEvaluationsExist(): void
    {
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $async->reset();

        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        // Fixture has an evaluation in PUBLIC_COMPETITION, so the rules change must trigger a recalc.
        $this->commandBus()->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: $competitionId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 12],
            ],
        ));

        $envelopes = $async->getSent();

        $recalcCommands = array_filter(
            $envelopes,
            fn ($envelope) => $envelope->getMessage() instanceof RecalculateCompetitionPointsCommand,
        );

        self::assertCount(1, $recalcCommands);
    }

    public function testNoRecalcWhenNoEvaluationsExist(): void
    {
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $async->reset();

        // VERIFIED_COMPETITION has no evaluations at fixture time.
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->commandBus()->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: $competitionId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 12],
            ],
        ));

        $envelopes = $async->getSent();

        $recalcCommands = array_filter(
            $envelopes,
            fn ($envelope) => $envelope->getMessage() instanceof RecalculateCompetitionPointsCommand,
        );

        self::assertCount(0, $recalcCommands);
    }
}
