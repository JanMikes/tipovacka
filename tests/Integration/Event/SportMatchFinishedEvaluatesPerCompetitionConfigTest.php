<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateCompetitionRuleConfiguration\UpdateCompetitionRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * THE key S04 semantic proof: one match finishing evaluates every guess with its
 * OWN competition's rule configuration — two competitions on the same source with
 * different points produce different totals for identical tips.
 */
final class SportMatchFinishedEvaluatesPerCompetitionConfigTest extends IntegrationTestCase
{
    public function testSameMatchYieldsDifferentPointsPerCompetitionConfig(): void
    {
        // PUBLIC_COMPETITION and SUBSET_COMPETITION both live on PUBLIC_SOURCE and
        // both contain MATCH_SCHEDULED (explicitly selected in the subset).
        // Bump exact_score to 50 points in the subset competition only.
        $this->commandBus()->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 50],
            ],
        ));

        // Identical tips in both competitions.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 0,
        ));
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 0,
        ));

        // One match finish → both guesses evaluated, each with its competition's config.
        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 1,
            awayScore: 0,
        ));

        // Public: defaults 5 + 3 + 1 + 1 = 10.
        self::assertSame(
            [10],
            $this->evaluationTotals(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID),
        );

        // Subset: 50 + 3 + 1 + 1 = 55 — same match, same tip, different competition config.
        self::assertSame(
            [55],
            $this->evaluationTotals(AppFixtures::SUBSET_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID),
        );
    }

    /**
     * @return list<int>
     */
    private function evaluationTotals(string $competitionId, string $matchId): array
    {
        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.competition = :competitionId')
            ->andWhere('g.sportMatch = :matchId')
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->setParameter('matchId', Uuid::fromString($matchId))
            ->getQuery()
            ->getResult();

        $totals = array_map(fn (GuessEvaluation $e) => $e->totalPoints, $evaluations);
        sort($totals);

        return $totals;
    }
}
