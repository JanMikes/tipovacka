<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\Command\LockCompetitionTips\LockCompetitionTipsCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UnlockCompetitionTips\UnlockCompetitionTipsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Exception\CompetitionTipsCannotBeUnlocked;
use App\Exception\GuessDeadlinePassed;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class LockCompetitionTipsHandlerTest extends IntegrationTestCase
{
    public function testLockPersistsLockMomentAndIsIdempotent(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->commandBus()->dispatch(new LockCompetitionTipsCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertEquals(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'), $competition->tipsLockedAt);

        // Repeated lock keeps the original moment (no-op).
        $clock = $this->clock();
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->modify('+1 hour');

        $this->commandBus()->dispatch(new LockCompetitionTipsCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
        ));

        $em->clear();
        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertEquals(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'), $competition->tipsLockedAt);
    }

    public function testUnlockBeforeFirstKickoffReopensTipping(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        $this->commandBus()->dispatch(new LockCompetitionTipsCommand(
            editorId: $userId,
            competitionId: $competitionId,
        ));

        // Locked ⇒ submitting fails…
        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: $userId,
                competitionId: $competitionId,
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                homeScore: 1,
                awayScore: 0,
            ));
            self::fail('Submit must be blocked while tips are locked.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessDeadlinePassed::class, $e->getPrevious());
        }

        // …unlock (first kickoff 2025-06-20 is still ahead) reopens tipping.
        $this->commandBus()->dispatch(new UnlockCompetitionTipsCommand(
            editorId: $userId,
            competitionId: $competitionId,
        ));

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: $userId,
            competitionId: $competitionId,
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 0,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertNull($competition->tipsLockedAt);

        $guess = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->setParameter('u', $userId)
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);
    }

    public function testUnlockAfterFirstKickoffIsRejected(): void
    {
        // SUBSET_COMPETITION includes MATCH_FINISHED (kickoff 2025-06-10, past)
        // ⇒ its first included kickoff already passed ⇒ the lock is final.
        $competitionId = Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID);
        $ownerId = Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID);

        $this->commandBus()->dispatch(new LockCompetitionTipsCommand(
            editorId: $ownerId,
            competitionId: $competitionId,
        ));

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new UnlockCompetitionTipsCommand(
                editorId: $ownerId,
                competitionId: $competitionId,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionTipsCannotBeUnlocked::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testUnlockOnUnlockedCompetitionIsNoOp(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->commandBus()->dispatch(new UnlockCompetitionTipsCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertNull($competition->tipsLockedAt);
    }

    public function testLatePlayoffMatchCreatedAfterLockStaysTippable(): void
    {
        // "Submit blocked after competition start, but allowed for the late
        // playoff match": lock now, advance the clock, add a playoff match —
        // it entered the competition after the lock moment ⇒ tips until its
        // own kickoff, while the pre-lock match stays locked.
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        $this->commandBus()->dispatch(new LockCompetitionTipsCommand(
            editorId: $userId,
            competitionId: $competitionId,
        ));

        $clock = $this->clock();
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->modify('+30 minutes');

        $envelope = $this->commandBus()->dispatch(new CreateSportMatchCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            editorId: $userId,
            homeTeam: 'Tygři',
            awayTeam: 'Vlci',
            kickoffAt: new \DateTimeImmutable('2025-06-25 18:00:00 UTC'),
            venue: null,
            round: 'Playoff',
            isPlayoff: true,
        ));

        $handled = $envelope->last(\Symfony\Component\Messenger\Stamp\HandledStamp::class);
        self::assertNotNull($handled);
        /** @var \App\Entity\SportMatch $lateMatch */
        $lateMatch = $handled->getResult();

        // The pre-lock match is blocked…
        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: $userId,
                competitionId: $competitionId,
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                homeScore: 1,
                awayScore: 0,
            ));
            self::fail('Pre-lock match must stay locked.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessDeadlinePassed::class, $e->getPrevious());
        }

        // …the late playoff match accepts tips until its own kickoff.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: $userId,
            competitionId: $competitionId,
            sportMatchId: $lateMatch->id,
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
            ->setParameter('u', $userId)
            ->setParameter('m', $lateMatch->id)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);
    }
}
