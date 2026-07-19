<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\VoidGuessesForMatch\VoidGuessesForMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Entity\GuessScorer;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GuessVoidedDropsEvaluationTest extends IntegrationTestCase
{
    public function testVoidingGuessRemovesItsEvaluation(): void
    {
        // Fixture: admin guess on MATCH_FINISHED has an evaluation.
        $this->commandBus()->dispatch(new VoidGuessesForMatchCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $evaluation = $em->find(GuessEvaluation::class, Uuid::fromString(AppFixtures::FIXTURE_GUESS_EVALUATION_ID));

        self::assertNull($evaluation);
    }

    public function testVoidingGuessRemovesItsScorerTips(): void
    {
        // Fixture: SECOND user's SUBSET guess on MATCH_FINISHED has a scorer tip.
        $this->commandBus()->dispatch(new VoidGuessesForMatchCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $scorer = $em->find(GuessScorer::class, Uuid::fromString(AppFixtures::SUBSET_GUESS_SCORER_ID));

        self::assertNull($scorer);
    }
}
