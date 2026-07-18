<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\RecalculateCompetitionPoints\RecalculateCompetitionPointsCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateCompetitionMatchSelection\UpdateCompetitionMatchSelectionCommand;
use App\DataFixtures\AppFixtures;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class CompetitionMatchSelectionChangedTriggersRecalcTest extends IntegrationTestCase
{
    public function testRecalcIsDispatchedWhenFinishedMatchGuessExists(): void
    {
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $async->reset();

        $competitionId = Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID);

        // Owner tips on the selected scheduled match, then the match finishes —
        // the competition now has a guess on a finished match (and its evaluation).
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: $competitionId,
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 0,
        ));

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 1,
            awayScore: 0,
        ));

        $async->reset();

        // Deselect the finished match — its points must stop counting, so a recalc is due.
        $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: $competitionId,
            selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_FINISHED_ID)],
        ));

        $recalcCommands = array_filter(
            $async->getSent(),
            fn ($envelope) => $envelope->getMessage() instanceof RecalculateCompetitionPointsCommand,
        );

        self::assertCount(1, $recalcCommands);

        $async->reset();
    }

    public function testNoRecalcWhenCompetitionHasNoGuesses(): void
    {
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $async->reset();

        // SUBSET_COMPETITION has no guesses (and no evaluations) at fixture time.
        $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_FINISHED_ID)],
        ));

        $recalcCommands = array_filter(
            $async->getSent(),
            fn ($envelope) => $envelope->getMessage() instanceof RecalculateCompetitionPointsCommand,
        );

        self::assertCount(0, $recalcCommands);
    }
}
