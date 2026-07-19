<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class SportMatchScoreUpdatedReEvaluatesTest extends IntegrationTestCase
{
    public function testReScoringAFinishedMatchRebuildsEvaluations(): void
    {
        // Admin's fixture guess is 3:0, MATCH_FINISHED was 2:1 (correct outcome = 3 points).
        // Now rescore the match to 3:0 — admin's guess should become an exact hit (10 points).
        // S06: the SUBSET fixture guess (2:1, periods + scorer tips) is ALSO re-evaluated;
        // the rescore clears periods and events, so only correct_outcome (3) remains there.
        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 3,
            awayScore: 0,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e', 'g')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.sportMatch = :matchId')
            ->setParameter('matchId', Uuid::fromString(AppFixtures::MATCH_FINISHED_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(2, $evaluations);

        $totalsByGuess = [];

        foreach ($evaluations as $evaluation) {
            $totalsByGuess[$evaluation->guess->id->toRfc4122()] = $evaluation->totalPoints;
        }

        self::assertSame(10, $totalsByGuess[AppFixtures::FIXTURE_GUESS_ID]);
        self::assertSame(3, $totalsByGuess[AppFixtures::SUBSET_GUESS_ID]);
    }
}
