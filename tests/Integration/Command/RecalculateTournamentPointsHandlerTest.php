<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RecalculateTournamentPoints\RecalculateTournamentPointsCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RecalculateTournamentPointsHandlerTest extends IntegrationTestCase
{
    public function testRecalcRebuildsEvaluationsForFinishedMatches(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);

        // Fixture already contains one evaluation for admin's guess (3:0 vs actual 2:1 = 3 points).
        $this->commandBus()->dispatch(new RecalculateTournamentPointsCommand(
            tournamentId: $tournamentId,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('m.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournamentId)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $evaluations);
        self::assertSame(3, $evaluations[0]->totalPoints);
    }

    public function testRecalcIsIdempotent(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);

        $this->commandBus()->dispatch(new RecalculateTournamentPointsCommand(
            tournamentId: $tournamentId,
        ));
        $this->commandBus()->dispatch(new RecalculateTournamentPointsCommand(
            tournamentId: $tournamentId,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('m.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournamentId)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $evaluations);
        self::assertSame(3, $evaluations[0]->totalPoints);
    }

    public function testRecalcPicksUpNewGuessesAfterMatchFinish(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);

        // Submit a guess on the scheduled match, then finish it with exact score.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
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

        $this->commandBus()->dispatch(new RecalculateTournamentPointsCommand(
            tournamentId: $tournamentId,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('m.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournamentId)
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
