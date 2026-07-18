<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreatePrivateMatchSource\CreatePrivateMatchSourceCommand;
use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Exception\InvalidScore;
use App\Tests\Support\IntegrationTestCase;
use App\Value\PeriodScores;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * Hockey end-to-end: create a hockey source, add a match and record a final
 * score with three third-period scores; two periods must be rejected.
 */
final class HockeyMatchSourceEndToEndTest extends IntegrationTestCase
{
    private function createHockeyMatch(): SportMatch
    {
        $this->commandBus()->dispatch(new CreatePrivateMatchSourceCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            sportId: Uuid::fromString(Sport::HOCKEY_ID),
            name: 'Hokejová liga',
            description: null,
            startAt: null,
            endAt: null,
        ));

        $em = $this->entityManager();

        $matchSource = $em->createQueryBuilder()
            ->select('t', 's')
            ->from(MatchSource::class, 't')
            ->innerJoin('t.sport', 's')
            ->where('t.name = :name')
            ->setParameter('name', 'Hokejová liga')
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertSame('hockey', $matchSource->sport->code);
        self::assertSame(3, $matchSource->sport->periodCount);

        $this->commandBus()->dispatch(new CreateSportMatchCommand(
            matchSourceId: $matchSource->id,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeTeam: 'Kometa Brno',
            awayTeam: 'Sparta Praha',
            kickoffAt: new \DateTimeImmutable('2025-06-25 17:00:00 UTC'),
            venue: null,
        ));

        $match = $em->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.matchSource = :sourceId')
            ->setParameter('sourceId', $matchSource->id)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(SportMatch::class, $match);

        return $match;
    }

    public function testHockeyFinalScoreRequiresThreeThirds(): void
    {
        $match = $this->createHockeyMatch();

        try {
            $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
                sportMatchId: $match->id,
                editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                homeScore: 3,
                awayScore: 1,
                periodScores: PeriodScores::fromArray([[2, 0], [1, 1]]),
            ));
            self::fail('Expected InvalidScore for two periods on a hockey match.');
        } catch (HandlerFailedException $exception) {
            $wrapped = $this->firstWrappedException($exception);
            self::assertInstanceOf(InvalidScore::class, $wrapped);
            self::assertSame('Zápas musí mít zadané skóre pro 3 třetiny.', $wrapped->getMessage());
        }
    }

    public function testHockeyFinalScoreWithThreeThirdsSucceeds(): void
    {
        $match = $this->createHockeyMatch();

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $match->id,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 3,
            awayScore: 1,
            periodScores: PeriodScores::fromArray([[2, 0], [0, 1], [1, 0]]),
        ));

        $em = $this->entityManager();
        $matchId = $match->id;
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertNotNull($match->periodScores);
        self::assertSame([[2, 0], [0, 1], [1, 0]], $match->periodScores->toArray());
    }
}
