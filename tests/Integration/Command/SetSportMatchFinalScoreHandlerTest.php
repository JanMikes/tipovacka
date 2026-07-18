<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Entity\MatchEvent;
use App\Entity\MatchSource;
use App\Entity\Player;
use App\Entity\SportMatch;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Enum\SportMatchState;
use App\Tests\Support\IntegrationTestCase;
use App\Value\MatchEventInput;
use App\Value\PeriodScores;
use Symfony\Component\Uid\Uuid;

final class SetSportMatchFinalScoreHandlerTest extends IntegrationTestCase
{
    public function testFinalizesMatchWithScore(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 3,
            awayScore: 1,
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertSame(3, $match->homeScore);
        self::assertSame(1, $match->awayScore);
    }

    public function testPersistsPeriodsOvertimeAndEventsWithPlayerPool(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 2,
            awayScore: 2,
            periodScores: PeriodScores::fromArray([[1, 1], [1, 1]]),
            overtimeHomeScore: 3,
            overtimeAwayScore: 2,
            events: [
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 12, 'Tomáš Rosický'),
                // Same new player scores twice — must create only ONE Player row.
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 78, 'Tomáš Rosický'),
                new MatchEventInput(MatchEventType::Goal, MatchSide::Away, 40, 'Lukáš Provod'),
                new MatchEventInput(MatchEventType::YellowCard, MatchSide::Away, null, 'Lukáš Provod'),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertNotNull($match->periodScores);
        self::assertSame([[1, 1], [1, 1]], $match->periodScores->toArray());
        self::assertSame(3, $match->overtimeHomeScore);
        self::assertSame(2, $match->overtimeAwayScore);

        /** @var list<MatchEvent> $events */
        $events = $em->createQueryBuilder()
            ->select('e', 'p')
            ->from(MatchEvent::class, 'e')
            ->innerJoin('e.player', 'p')
            ->where('e.sportMatch = :matchId')
            ->setParameter('matchId', $matchId)
            ->getQuery()
            ->getResult();
        self::assertCount(4, $events);

        /** @var list<Player> $players */
        $players = $em->createQueryBuilder()
            ->select('p')
            ->from(Player::class, 'p')
            ->where('p.name IN (:names)')
            ->setParameter('names', ['Tomáš Rosický', 'Lukáš Provod'])
            ->getQuery()
            ->getResult();
        self::assertCount(2, $players);

        $byName = [];
        foreach ($players as $player) {
            $byName[$player->name] = $player;
        }
        self::assertSame('Sparta Praha', $byName['Tomáš Rosický']->teamName);
        self::assertSame('Slavia Praha', $byName['Lukáš Provod']->teamName);
    }

    public function testReusesExistingPlayerFromPoolByName(): void
    {
        // MATCH_FINISHED belongs to PUBLIC_SOURCE whose pool already has
        // 'Jan Novák' for team 'Bohemians 1905' (fixture).
        $matchId = Uuid::fromString(AppFixtures::MATCH_FINISHED_ID);

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 2,
            awayScore: 1,
            events: [
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 10, AppFixtures::PLAYER_HOME_SCORER_ONE_NAME),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<MatchEvent> $events */
        $events = $em->createQueryBuilder()
            ->select('e', 'p')
            ->from(MatchEvent::class, 'e')
            ->innerJoin('e.player', 'p')
            ->where('e.sportMatch = :matchId')
            ->setParameter('matchId', $matchId)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $events);
        self::assertSame(AppFixtures::PLAYER_HOME_SCORER_ONE_ID, $events[0]->player->id->toRfc4122());

        // No duplicate player row was created.
        $count = $em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Player::class, 'p')
            ->where('p.name = :name')
            ->setParameter('name', AppFixtures::PLAYER_HOME_SCORER_ONE_NAME)
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(1, (int) $count);
    }

    public function testScorerNameLookupIsCaseInsensitive(): void
    {
        // Pool already has 'Jan Novák' (fixture). Different casings in the event
        // sheet must all resolve to that one player — no duplicate rows.
        $matchId = Uuid::fromString(AppFixtures::MATCH_FINISHED_ID);

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 2,
            awayScore: 1,
            events: [
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 10, 'jan novák'),
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 55, 'JAN NOVÁK'),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<MatchEvent> $events */
        $events = $em->createQueryBuilder()
            ->select('e', 'p')
            ->from(MatchEvent::class, 'e')
            ->innerJoin('e.player', 'p')
            ->where('e.sportMatch = :matchId')
            ->setParameter('matchId', $matchId)
            ->getQuery()
            ->getResult();

        self::assertCount(2, $events);

        foreach ($events as $event) {
            self::assertSame(AppFixtures::PLAYER_HOME_SCORER_ONE_ID, $event->player->id->toRfc4122());
            // First-seen casing is kept on the stored row.
            self::assertSame(AppFixtures::PLAYER_HOME_SCORER_ONE_NAME, $event->player->name);
        }

        $count = $em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Player::class, 'p')
            ->where('LOWER(p.name) = :name')
            ->setParameter('name', 'jan novák')
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(1, (int) $count);
    }

    public function testSavingReplacesExistingEvents(): void
    {
        // MATCH_FINISHED starts with three fixture events.
        $matchId = Uuid::fromString(AppFixtures::MATCH_FINISHED_ID);

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 2,
            awayScore: 1,
            events: [
                new MatchEventInput(MatchEventType::RedCard, MatchSide::Away, 88, 'Nový Hráč'),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<MatchEvent> $events */
        $events = $em->createQueryBuilder()
            ->select('e', 'p')
            ->from(MatchEvent::class, 'e')
            ->innerJoin('e.player', 'p')
            ->where('e.sportMatch = :matchId')
            ->setParameter('matchId', $matchId)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $events);
        self::assertSame(MatchEventType::RedCard, $events[0]->type);
        self::assertSame('Nový Hráč', $events[0]->player->name);
    }

    public function testCorrectionReEvaluatesAndMayChangePeriodsAndOvertime(): void
    {
        // Fixture: MATCH_FINISHED 2:1 with admin's guess 3:0 evaluated at 3 points.
        // Correcting the result to 3:0 makes the guess an exact hit (all 4 rules = 10).
        $matchId = Uuid::fromString(AppFixtures::MATCH_FINISHED_ID);

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 3,
            awayScore: 0,
            periodScores: PeriodScores::fromArray([[2, 0], [1, 0]]),
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(3, $match->homeScore);
        self::assertSame(0, $match->awayScore);
        self::assertNotNull($match->periodScores);
        self::assertSame([[2, 0], [1, 0]], $match->periodScores->toArray());

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->where('e.guess = :guessId')
            ->setParameter('guessId', Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $evaluations);
        self::assertSame(10, $evaluations[0]->totalPoints);
    }

    public function testLastMatchFlagCompletesTheSource(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 1,
            awayScore: 0,
            isLastMatch: true,
        ));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertTrue($matchSource->isCompleted);
    }

    public function testLastMatchFlagIsIdempotentOnAlreadyCompletedSource(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 1,
            awayScore: 0,
            isLastMatch: true,
        ));

        // Score correction with the flag still ticked must not throw.
        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 2,
            awayScore: 0,
            isLastMatch: true,
        ));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertTrue($matchSource->isCompleted);
    }
}
