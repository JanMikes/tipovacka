<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\Command\ReopenMatchSource\ReopenMatchSourceCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Event\MatchSourceCompleted;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * `competition_ended` fires ONLY once a competition is truly over: the source is
 * completed AND every included match is finished+evaluated. VERIFIED_COMPETITION
 * draws from PRIVATE_SOURCE, whose sole match (MATCH_PRIVATE_SCHEDULED) starts
 * Scheduled — so completion alone must NOT end it until that match is evaluated.
 */
final class NotifyCompetitionEndedTest extends IntegrationTestCase
{
    public function testCompletingSourceWithAnUnevaluatedMatchDoesNotNotify(): void
    {
        // Source marked complete while its only match is still Scheduled.
        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
        ));

        self::assertSame(0, $this->endedCount(), 'No standings while a match is unevaluated.');
        self::assertNull($this->competition()->endedNotifiedAt, 'Guard stays unset so it can fire later.');
    }

    public function testCompetitionEndsOnceEveryMatchEvaluatedAfterCompletion(): void
    {
        $this->driveCompetitionToEnd();

        // The winner (only guesser → rank 1) is notified exactly once.
        self::assertSame(1, $this->endedCountForUser(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($this->competition()->endedNotifiedAt);

        $notification = $this->endedNotificationForUser(AppFixtures::VERIFIED_USER_ID);
        self::assertNotNull($notification);
        self::assertStringContainsString('vyhráli', $notification->body);

        // Re-firing the completion event must never double-notify (one-shot guard).
        $this->eventBus()->dispatch(new MatchSourceCompleted(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            occurredOn: \DateTimeImmutable::createFromInterface($this->clock()->now()),
        ));

        self::assertSame(1, $this->endedCountForUser(AppFixtures::VERIFIED_USER_ID));
    }

    public function testReopeningClearsGuardAndDedupThenRecompletionResends(): void
    {
        $this->driveCompetitionToEnd();
        self::assertNotNull($this->competition()->endedNotifiedAt);

        // Reopen: the standing is stale — clear the guard AND drop the sent rows.
        $this->commandBus()->dispatch(new ReopenMatchSourceCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
        ));

        self::assertSame(0, $this->endedCount(), 'competition_ended rows (dedup markers) dropped.');
        self::assertNull($this->competition()->endedNotifiedAt, 'Guard cleared.');

        // Re-completing (the match is already finished+evaluated) re-sends.
        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
        ));

        self::assertSame(1, $this->endedCountForUser(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($this->competition()->endedNotifiedAt);
    }

    /**
     * Guess → complete source (still Scheduled, so no standing yet) → enter the
     * final score. The evaluation drives `competition_ended` post-commit.
     */
    private function driveCompetitionToEnd(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
        ));

        self::assertSame(0, $this->endedCount(), 'Completion alone must not end it.');

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 2,
            awayScore: 1,
        ));
    }

    private function competition(): Competition
    {
        $this->entityManager()->clear();

        return $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ?? throw new \RuntimeException('competition missing');
    }

    private function endedCount(): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.competition = :competitionId')
            ->andWhere('n.type = :type')
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ->setParameter('type', NotificationType::CompetitionEnded)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function endedCountForUser(string $userId): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.competition = :competitionId')
            ->andWhere('n.type = :type')
            ->andWhere('n.user = :userId')
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ->setParameter('type', NotificationType::CompetitionEnded)
            ->setParameter('userId', Uuid::fromString($userId))
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function endedNotificationForUser(string $userId): ?Notification
    {
        return $this->entityManager()->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->where('n.competition = :competitionId')
            ->andWhere('n.type = :type')
            ->andWhere('n.user = :userId')
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ->setParameter('type', NotificationType::CompetitionEnded)
            ->setParameter('userId', Uuid::fromString($userId))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
