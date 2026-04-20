<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\SoftDeleteSportMatch\SoftDeleteSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class SportMatchDeletedClearsEvaluationsTest extends IntegrationTestCase
{
    public function testDeletingMatchVoidsGuessesAndClearsEvaluations(): void
    {
        // MATCH_FINISHED_ID has a fixture guess + evaluation already.
        $this->commandBus()->dispatch(new SoftDeleteSportMatchCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<Guess> $activeGuesses */
        $activeGuesses = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.sportMatch = :matchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('matchId', Uuid::fromString(AppFixtures::MATCH_FINISHED_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(0, $activeGuesses);

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.sportMatch = :matchId')
            ->setParameter('matchId', Uuid::fromString(AppFixtures::MATCH_FINISHED_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(0, $evaluations);
    }
}
