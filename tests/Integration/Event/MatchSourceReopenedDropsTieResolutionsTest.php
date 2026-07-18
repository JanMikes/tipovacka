<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\Command\ReopenMatchSource\ReopenMatchSourceCommand;
use App\Command\ResolveLeaderboardTies\ResolveLeaderboardTiesCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\LeaderboardTieResolution;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Manual tie-break ranks describe a frozen final standing. Reopening the match
 * source (more matches to play) must drop them for every attached competition —
 * the leaderboard falls back to pure point ordering.
 */
final class MatchSourceReopenedDropsTieResolutionsTest extends IntegrationTestCase
{
    public function testReopeningSourceDeletesTieResolutionsOfAttachedCompetitions(): void
    {
        $this->seedSecondMemberWithMatchingPoints();

        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);
        $matchSourceId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);

        $this->commandBus()->dispatch(new ResolveLeaderboardTiesCommand(
            competitionId: $competitionId,
            resolverId: Uuid::fromString(AppFixtures::ADMIN_ID),
            orderedUserIds: [
                Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                Uuid::fromString(AppFixtures::ADMIN_ID),
            ],
        ));

        self::assertSame(2, $this->countResolutions($competitionId));

        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(matchSourceId: $matchSourceId));
        $this->commandBus()->dispatch(new ReopenMatchSourceCommand(matchSourceId: $matchSourceId));

        self::assertSame(0, $this->countResolutions($competitionId));

        // Leaderboard is ordered purely by points again: both tied members share
        // rank 1 and no row carries a manual override.
        $leaderboard = $this->queryBus()->handle(new GetCompetitionLeaderboard(competitionId: $competitionId));

        self::assertCount(2, $leaderboard->rows);

        foreach ($leaderboard->rows as $row) {
            self::assertSame(1, $row->rank);
            self::assertFalse($row->isTieResolvedOverride);
            self::assertSame(3, $row->totalPoints);
        }
    }

    public function testReopenOnNeverCompletedSourceKeepsResolutions(): void
    {
        $this->seedSecondMemberWithMatchingPoints();

        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        $this->commandBus()->dispatch(new ResolveLeaderboardTiesCommand(
            competitionId: $competitionId,
            resolverId: Uuid::fromString(AppFixtures::ADMIN_ID),
            orderedUserIds: [
                Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                Uuid::fromString(AppFixtures::ADMIN_ID),
            ],
        ));

        // Reopen is a no-op on an active source — no event, resolutions stay.
        $this->commandBus()->dispatch(new ReopenMatchSourceCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
        ));

        self::assertSame(2, $this->countResolutions($competitionId));
    }

    private function countResolutions(Uuid $competitionId): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(LeaderboardTieResolution::class, 'r')
            ->where('r.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function seedSecondMemberWithMatchingPoints(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);

        $publicCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($publicCompetition);

        $finishedMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finishedMatch);

        $membership = new Membership(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $verified,
            sportMatch: $finishedMatch,
            competition: $publicCompetition,
            homeScore: 4,
            awayScore: 2,
            submittedAt: $now,
        );
        $guess->popEvents();
        $em->persist($guess);

        $evaluation = new GuessEvaluation(
            id: Uuid::v7(),
            guess: $guess,
            evaluatedAt: $now,
        );
        $evaluation->addRulePoints(new GuessEvaluationRulePoints(
            id: Uuid::v7(),
            evaluation: $evaluation,
            ruleIdentifier: 'correct_outcome',
            points: 3,
        ));
        $em->persist($evaluation);

        $em->flush();
    }
}
