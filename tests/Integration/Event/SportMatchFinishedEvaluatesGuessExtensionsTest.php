<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateCompetitionRuleConfiguration\UpdateCompetitionRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Query\GetMemberLeaderboardBreakdown\GetMemberLeaderboardBreakdown;
use App\Tests\Support\IntegrationTestCase;
use App\Value\GuessScorerInput;
use App\Value\MatchEventInput;
use App\Value\PeriodScores;
use Symfony\Component\Uid\Uuid;

/**
 * THE S06 semantic proof: one match finishing with periods + overtime + scorer
 * events evaluates every guess with its OWN competition's rule configuration —
 * the same tip scores base-only points in PUBLIC_COMPETITION and additionally
 * period/scorer/overtime points in the feature-on SUBSET_COMPETITION.
 */
final class SportMatchFinishedEvaluatesGuessExtensionsTest extends IntegrationTestCase
{
    public function testExtensionRulesScoreOnlyWhereEnabled(): void
    {
        // SUBSET has scorer_hit + period rules on (fixtures); enable overtime too.
        $this->commandBus()->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            changes: [
                'overtime_exact' => ['enabled' => true, 'points' => 3],
            ],
        ));

        // PUBLIC (all optional rules off): base-only tip 1:1.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
        ));

        // SUBSET: same main tip 1:1 + periods + overtime + one free-typed scorer.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
            periodScores: PeriodScores::fromArray([[1, 0], [0, 1]]),
            overtimeHomeScore: 2,
            overtimeAwayScore: 1,
            scorers: [new GuessScorerInput(MatchSide::Home, 'Karel Střelec')],
        ));

        // Final result 1:1 (periods exactly as tipped), OT 2:1, the tipped
        // scorer scores (organizer types the same name — case-insensitive pool).
        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 1,
            awayScore: 1,
            periodScores: PeriodScores::fromArray([[1, 0], [0, 1]]),
            overtimeHomeScore: 2,
            overtimeAwayScore: 1,
            events: [
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 27, 'karel střelec'),
                new MatchEventInput(MatchEventType::Goal, MatchSide::Away, 63, 'Ondřej Hostující'),
            ],
        ));

        // PUBLIC: base defaults 5 + 3 + 1 + 1 = 10 — regression, extensions add nothing.
        self::assertSame(
            [
                'correct_away_goals' => 1,
                'correct_home_goals' => 1,
                'correct_outcome' => 3,
                'exact_score' => 5,
            ],
            $this->rulePointsFor(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::ADMIN_ID),
        );

        // SUBSET: base 10 + period_exact 2×5 + scorer_hit 1×2 + overtime_exact 3 = 25.
        // period_tendency stays exclusive (both periods exact ⇒ no row).
        self::assertSame(
            [
                'correct_away_goals' => 1,
                'correct_home_goals' => 1,
                'correct_outcome' => 3,
                'exact_score' => 5,
                'overtime_exact' => 3,
                'period_exact' => 10,
                'scorer_hit' => 2,
            ],
            $this->rulePointsFor(AppFixtures::SUBSET_COMPETITION_ID, AppFixtures::SECOND_VERIFIED_USER_ID),
        );

        // Member breakdown exposes Czech labels + the ×2 multiplier for period_exact.
        $breakdown = $this->queryBus()->handle(new GetMemberLeaderboardBreakdown(
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
        ));

        $items = [];

        foreach ($breakdown->rows as $row) {
            foreach ($row->breakdown as $item) {
                $items[$item->ruleIdentifier] = $item;
            }
        }

        self::assertArrayHasKey('period_exact', $items);
        self::assertSame('Přesný výsledek části zápasu', $items['period_exact']->label);
        self::assertSame(2, $items['period_exact']->multiplier);
        self::assertSame(10, $items['period_exact']->points);
        self::assertArrayHasKey('scorer_hit', $items);
        self::assertSame('Trefený střelec', $items['scorer_hit']->label);
        self::assertSame(1, $items['scorer_hit']->multiplier);
    }

    /**
     * @return array<string, int> rule identifier → stored points (product)
     */
    private function rulePointsFor(string $competitionId, string $userId): array
    {
        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e', 'rp')
            ->from(GuessEvaluation::class, 'e')
            ->leftJoin('e.rulePoints', 'rp')
            ->innerJoin('e.guess', 'g')
            ->where('g.competition = :competitionId')
            ->andWhere('g.user = :userId')
            ->andWhere('g.sportMatch = :matchId')
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('matchId', Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $evaluations);

        $points = [];

        foreach ($evaluations[0]->rulePoints as $rulePoints) {
            $points[$rulePoints->ruleIdentifier] = $rulePoints->points;
        }

        ksort($points);

        return $points;
    }
}
