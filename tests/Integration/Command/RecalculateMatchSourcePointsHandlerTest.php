<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RecalculateMatchSourcePoints\RecalculateMatchSourcePointsCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RecalculateMatchSourcePointsHandlerTest extends IntegrationTestCase
{
    public function testRecalcRebuildsEvaluationsForFinishedMatches(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);

        // Fixture already contains one evaluation for admin's guess (3:0 vs actual 2:1 = 3 points).
        $this->commandBus()->dispatch(new RecalculateMatchSourcePointsCommand(
            matchSourceId: $matchSourceId,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('m.matchSource = :matchSourceId')
            ->setParameter('matchSourceId', $matchSourceId)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $evaluations);
        self::assertSame(3, $evaluations[0]->totalPoints);
    }

    public function testRecalcIsIdempotent(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);

        $this->commandBus()->dispatch(new RecalculateMatchSourcePointsCommand(
            matchSourceId: $matchSourceId,
        ));
        $this->commandBus()->dispatch(new RecalculateMatchSourcePointsCommand(
            matchSourceId: $matchSourceId,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('m.matchSource = :matchSourceId')
            ->setParameter('matchSourceId', $matchSourceId)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $evaluations);
        self::assertSame(3, $evaluations[0]->totalPoints);
    }

    public function testRecalcPicksUpNewGuessesAfterMatchFinish(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);

        // Submit a guess on the scheduled match, then finish it with exact score.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
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

        $this->commandBus()->dispatch(new RecalculateMatchSourcePointsCommand(
            matchSourceId: $matchSourceId,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('m.matchSource = :matchSourceId')
            ->setParameter('matchSourceId', $matchSourceId)
            ->getQuery()
            ->getResult();

        // Two evaluations: admin's fixture guess and the new guess (exact score 1:0).
        self::assertCount(2, $evaluations);

        $totals = array_map(fn (GuessEvaluation $e) => $e->totalPoints, $evaluations);
        sort($totals);
        // 3 (correct outcome only) + 10 (all four rules hit)
        self::assertSame([3, 10], $totals);
    }
}
