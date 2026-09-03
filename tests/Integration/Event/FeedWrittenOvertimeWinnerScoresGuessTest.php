<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\SyncMatchSourceFeed\SyncMatchSourceFeedCommand;
use App\Command\UpdateCompetitionRuleConfiguration\UpdateCompetitionRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Enum\FeedMatchStatus;
use App\Enum\FeedProvider;
use App\Enum\MatchSide;
use App\Service\Feed\MatchSnapshot;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The last link of the UEFA overtime chain: the winner the adapter derives from
 * `score.total` / `score.penalty` travels through the sync pipeline into the
 * match and is scored by `overtime_exact` exactly like a hand-entered one — no
 * human touches the result at any point.
 */
final class FeedWrittenOvertimeWinnerScoresGuessTest extends IntegrationTestCase
{
    private const string EXTERNAL_ID = '2049278';

    public function testAWinnerArrivingFromTheFeedScoresAnOvertimeTip(): void
    {
        $this->commandBus()->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            changes: ['overtime_exact' => ['enabled' => true, 'points' => 3]],
        ));

        // „Bude to remíza 1:1 a vyhraje hostující tým."
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
            overtimeHomeScore: 1,
            overtimeAwayScore: 2,
        ));

        $this->bindFeedToTheScheduledMatch();

        // What UefaMatchDataProvider emits for SK Rapid – Hearts (regular 1:1,
        // total 2:2, penalty 3:4): the draw plus one goal for the away side.
        $this->commandBus()->dispatch(new SyncMatchSourceFeedCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            snapshots: [new MatchSnapshot(
                externalId: self::EXTERNAL_ID,
                homeTeamName: 'Sparta Praha',
                awayTeamName: 'Slavia Praha',
                kickoffUtc: new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
                status: FeedMatchStatus::Finished,
                homeScore: 1,
                awayScore: 1,
                overtimeHomeScore: 1,
                overtimeAwayScore: 2,
            )],
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(MatchSide::Away, $match->overtimeWinner);

        self::assertSame(3, $this->overtimePointsOfTheSubsetTip());
    }

    /**
     * The fixture match carries no externalId (it was hand-seeded); the feed
     * anchors on one, exactly as `app:matches:adopt-external-ids` would set it.
     */
    private function bindFeedToTheScheduledMatch(): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $em = $this->entityManager();

        $source = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $source);
        self::assertTrue($source->hasOvertime, 'the fixture zdroj plays prodloužení');
        $source->bindFeed(FeedProvider::Fixture, 'unused.json', $now);
        $source->popEvents();

        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        $match->linkExternal(self::EXTERNAL_ID, $now);

        $em->flush();
    }

    private function overtimePointsOfTheSubsetTip(): int
    {
        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $this->entityManager()->createQueryBuilder()
            ->select('e', 'rp')
            ->from(GuessEvaluation::class, 'e')
            ->leftJoin('e.rulePoints', 'rp')
            ->innerJoin('e.guess', 'g')
            ->where('g.competition = :competition')
            ->andWhere('g.user = :user')
            ->setParameter('competition', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
            ->setParameter('user', Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID))
            ->getQuery()
            ->getResult();

        foreach ($evaluations as $evaluation) {
            foreach ($evaluation->rulePoints as $rulePoints) {
                if ('overtime_exact' === $rulePoints->ruleIdentifier) {
                    return $rulePoints->points;
                }
            }
        }

        self::fail('The tip scored no overtime_exact points.');
    }
}
