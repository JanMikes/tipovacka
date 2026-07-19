<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\LockCompetitionTips\LockCompetitionTipsCommand;
use App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\InvalidGuessScore;
use App\Exception\MatchNotInCompetition;
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
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
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
                competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
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
        // Fixture already has admin guessing on MATCH_FINISHED_ID in PUBLIC_COMPETITION.
        // But MATCH_FINISHED_ID is finished — so instead let's create a fresh guess,
        // then try again.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
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
                competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_LIVE_ID),
                homeScore: 1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessDeadlinePassed::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testRejectsWhenTipsManuallyLocked(): void
    {
        // Manual „Uzamknout tipy" locks immediately: tipsLockedAt = now (12:00),
        // so a submit at the very same now is already past the deadline —
        // even though the match kicks off only on 2025-06-20.
        $this->commandBus()->dispatch(new LockCompetitionTipsCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
        ));

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                homeScore: 1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessDeadlinePassed::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testRejectsWhenPerMatchOverrideDeadlinePassed(): void
    {
        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            deadline: new \DateTimeImmutable('2025-06-14 09:00:00'),
        ));

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                homeScore: 1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessDeadlinePassed::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testOverridePushesDeadlinePastNowEvenIfTipsAreLocked(): void
    {
        // Tips manually locked, but a manager per-match override pushes THIS
        // match's deadline to the future (still ≤ kickoff) — the override wins.
        $this->commandBus()->dispatch(new LockCompetitionTipsCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
        ));
        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            deadline: new \DateTimeImmutable('2025-06-20 17:30:00'),
        ));

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
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
    }

    public function testRejectsMatchOutsideSubsetSelection(): void
    {
        // MATCH_PLAYOFF is scheduled + tippable, but the subset competition
        // only selected MATCH_SCHEDULED + MATCH_FINISHED.
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PLAYOFF_ID),
                homeScore: 1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(MatchNotInCompetition::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testAcceptsSelectedMatchInSubsetCompetition(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 2,
        ));

        $em = $this->entityManager();
        $em->clear();

        $guess = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->andWhere('g.competition = :c')
            ->setParameter('u', Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID))
            ->setParameter('c', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Guess::class, $guess);
    }

    public function testRejectsPlayoffMatchWhenCompetitionExcludesPlayoff(): void
    {
        // Flip PUBLIC_COMPETITION (mode All) to includePlayoff = false directly —
        // there is no dedicated command yet (S08 wizard territory).
        $em = $this->entityManager();
        $connection = $em->getConnection();
        $connection->executeStatement(
            'UPDATE competitions SET include_playoff = false WHERE id = :id',
            ['id' => AppFixtures::PUBLIC_COMPETITION_ID],
        );
        $em->clear();

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::ADMIN_ID),
                competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PLAYOFF_ID),
                homeScore: 1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(MatchNotInCompetition::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testRejectsNegativeScores(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
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
