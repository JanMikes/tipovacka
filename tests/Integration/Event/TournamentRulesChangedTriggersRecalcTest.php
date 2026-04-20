<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\RecalculateTournamentPoints\RecalculateTournamentPointsCommand;
use App\Command\UpdateTournamentRuleConfiguration\UpdateTournamentRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class TournamentRulesChangedTriggersRecalcTest extends IntegrationTestCase
{
    public function testRecalcIsDispatchedWhenEvaluationsExist(): void
    {
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $async->reset();

        $tournamentId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);

        // Fixture has an evaluation for PUBLIC_TOURNAMENT, so the rules change must trigger a recalc.
        $this->commandBus()->dispatch(new UpdateTournamentRuleConfigurationCommand(
            tournamentId: $tournamentId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 12],
            ],
        ));

        $envelopes = $async->getSent();

        $recalcCommands = array_filter(
            $envelopes,
            fn ($envelope) => $envelope->getMessage() instanceof RecalculateTournamentPointsCommand,
        );

        self::assertCount(1, $recalcCommands);
    }

    public function testNoRecalcWhenNoEvaluationsExist(): void
    {
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $async->reset();

        // PRIVATE_TOURNAMENT has no evaluations at fixture time.
        $tournamentId = Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID);

        $this->commandBus()->dispatch(new UpdateTournamentRuleConfigurationCommand(
            tournamentId: $tournamentId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 12],
            ],
        ));

        $envelopes = $async->getSent();

        $recalcCommands = array_filter(
            $envelopes,
            fn ($envelope) => $envelope->getMessage() instanceof RecalculateTournamentPointsCommand,
        );

        self::assertCount(0, $recalcCommands);
    }
}
