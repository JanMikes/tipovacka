<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\VoidGuessesForMatch\VoidGuessesForMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class VoidGuessesForMatchHandlerTest extends IntegrationTestCase
{
    public function testVoidsAllActiveGuessesForMatch(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
        ));

        $this->commandBus()->dispatch(new VoidGuessesForMatchCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<Guess> $active */
        $active = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.sportMatch = :m')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(0, $active);

        // The soft-deleted row still exists.
        /** @var list<Guess> $all */
        $all = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.sportMatch = :m')
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->getQuery()
            ->getResult();
        self::assertCount(1, $all);
        self::assertNotNull($all[0]->deletedAt);
    }

    public function testDoesNotVoidGuessesForOtherMatches(): void
    {
        // Fixture has admin's guess on MATCH_FINISHED_ID (not the scheduled one).
        $this->commandBus()->dispatch(new VoidGuessesForMatchCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $fixtureGuess = $em->find(Guess::class, Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID));
        self::assertInstanceOf(Guess::class, $fixtureGuess);
        self::assertNull($fixtureGuess->deletedAt);
    }
}
