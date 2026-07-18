<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateLiveScore\UpdateLiveScoreCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Entity\MatchEvent;
use App\Entity\SportMatch;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Enum\SportMatchState;
use App\Exception\SportMatchInvalidTransition;
use App\Tests\Support\IntegrationTestCase;
use App\Value\MatchEventInput;
use App\Value\PeriodScores;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class UpdateLiveScoreHandlerTest extends IntegrationTestCase
{
    public function testTransitionsScheduledMatchToLiveAndStoresScore(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new UpdateLiveScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 1,
            awayScore: 0,
            periodScores: PeriodScores::fromArray([[1, 0]]),
            events: [
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 21, 'Rychlý Střelec'),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Live, $match->state);
        self::assertSame(1, $match->homeScore);
        self::assertSame(0, $match->awayScore);
        self::assertNotNull($match->periodScores);
        self::assertSame([[1, 0]], $match->periodScores->toArray());

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
        self::assertSame('Rychlý Střelec', $events[0]->player->name);
    }

    public function testLiveUpdateDoesNotEvaluateButFinishingDoes(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: $matchId,
            homeScore: 2,
            awayScore: 1,
        ));

        $this->commandBus()->dispatch(new UpdateLiveScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $em = $this->entityManager();
        $em->clear();

        $evaluationsCount = fn (): int => (int) $em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.sportMatch = :matchId')
            ->setParameter('matchId', $matchId)
            ->getQuery()
            ->getSingleScalarResult();

        self::assertSame(0, $evaluationsCount(), 'Live score change must NOT trigger evaluation.');

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 2,
            awayScore: 1,
        ));
        $em->clear();

        self::assertSame(1, $evaluationsCount(), 'Finishing the match must evaluate.');
    }

    public function testRejectsLiveUpdateOnFinishedMatch(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_FINISHED_ID);

        try {
            $this->commandBus()->dispatch(new UpdateLiveScoreCommand(
                sportMatchId: $matchId,
                editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
                homeScore: 1,
                awayScore: 1,
            ));
            self::fail('Expected SportMatchInvalidTransition.');
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(SportMatchInvalidTransition::class, $this->firstWrappedException($exception));
        }
    }
}
