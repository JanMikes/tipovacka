<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateGuess\UpdateGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessNotFound;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class UpdateGuessHandlerTest extends IntegrationTestCase
{
    public function testUpdatesScoresForOwner(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 1,
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

        $this->commandBus()->dispatch(new UpdateGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            guessId: $guess->id,
            homeScore: 3,
            awayScore: 0,
        ));

        $em->clear();
        $refreshed = $em->find(Guess::class, $guess->id);
        self::assertInstanceOf(Guess::class, $refreshed);
        self::assertSame(3, $refreshed->homeScore);
        self::assertSame(0, $refreshed->awayScore);
    }

    public function testFailsWhenNotOwner(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
        ));

        $em = $this->entityManager();
        $em->clear();

        $guess = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->setParameter('u', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new UpdateGuessCommand(
                userId: Uuid::fromString(AppFixtures::ADMIN_ID),
                guessId: $guess->id,
                homeScore: 5,
                awayScore: 5,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessNotFound::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testFailsWhenMatchNoLongerOpen(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
        ));

        $em = $this->entityManager();

        // Cancel the match — now not open for guesses.
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        $clock = $this->clock();
        $match->cancel(\DateTimeImmutable::createFromInterface($clock->now()));
        $em->flush();

        $em->clear();
        $guess = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->setParameter('u', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new UpdateGuessCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                guessId: $guess->id,
                homeScore: 7,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessDeadlinePassed::class, $e->getPrevious());

            throw $e;
        }
    }
}
