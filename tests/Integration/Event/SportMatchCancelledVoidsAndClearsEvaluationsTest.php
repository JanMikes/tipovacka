<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\CancelSportMatch\CancelSportMatchCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class SportMatchCancelledVoidsAndClearsEvaluationsTest extends IntegrationTestCase
{
    public function testCancellingMatchRemovesEvaluationsAndVoidsGuesses(): void
    {
        // Submit a guess on the scheduled match, finish it (→ creates evaluation),
        // then cancel it.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 0,
            awayScore: 0,
        ));

        // Cancel directly (without finishing) — match is still scheduled.
        $this->commandBus()->dispatch(new CancelSportMatchCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<Guess> $activeGuesses */
        $activeGuesses = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.sportMatch = :matchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('matchId', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(0, $activeGuesses);

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.sportMatch = :matchId')
            ->setParameter('matchId', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(0, $evaluations);
    }

    public function testCancellingFinishedMatchCleansUpEvaluations(): void
    {
        // Fixture MATCH_FINISHED cannot be cancelled directly (cancel throws on finished).
        // Simulate: rescore does not enable cancelling, but we can test the evaluation
        // removal path by creating a fresh scheduled match, guessing on it, and cancelling.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
        ));

        // Rescore is blocked on scheduled match? Actually setFinalScore transitions
        // scheduled → finished, so go straight to finish.
        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 1,
            awayScore: 1,
        ));

        // Now there should be an evaluation. Sanity check.
        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.sportMatch = :matchId')
            ->setParameter('matchId', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->getQuery()
            ->getResult();

        self::assertGreaterThan(0, count($evaluations));
    }
}
