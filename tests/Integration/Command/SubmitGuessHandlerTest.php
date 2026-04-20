<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\InvalidGuessScore;
use App\Exception\NotAMember;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class SubmitGuessHandlerTest extends IntegrationTestCase
{
    public function testMemberCanSubmitGuessOnScheduledMatch(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $em = $this->entityManager();
        $em->clear();

        $guess = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->setParameter('u', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Guess::class, $guess);
        self::assertSame(2, $guess->homeScore);
        self::assertSame(1, $guess->awayScore);
    }

    public function testFailsWhenNotMember(): void
    {
        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('Nejsi členem');

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                homeScore: 1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(NotAMember::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testFailsOnDuplicateGuess(): void
    {
        // Fixture already has admin guessing on MATCH_FINISHED_ID in PUBLIC_GROUP.
        // But MATCH_FINISHED_ID is finished — so instead let's create a fresh guess,
        // then try again.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                homeScore: 3,
                awayScore: 2,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessAlreadyExists::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testFailsAfterKickoffPassed(): void
    {
        // MATCH_LIVE_ID's kickoff is 2025-06-15 11:00 UTC, MockClock is 12:00 UTC.
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::ADMIN_ID),
                groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_LIVE_ID),
                homeScore: 1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessDeadlinePassed::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testRejectsNegativeScores(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                homeScore: -1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvalidGuessScore::class, $e->getPrevious());

            throw $e;
        }
    }
}
