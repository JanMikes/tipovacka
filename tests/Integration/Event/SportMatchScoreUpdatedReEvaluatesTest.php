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
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.sportMatch = :matchId')
            ->setParameter('matchId', Uuid::fromString(AppFixtures::MATCH_FINISHED_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $evaluations);
        self::assertSame(10, $evaluations[0]->totalPoints);
    }
}
