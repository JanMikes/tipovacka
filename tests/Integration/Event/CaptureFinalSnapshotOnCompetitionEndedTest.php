<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\CaptureLeaderboardSnapshots\CaptureLeaderboardSnapshotsCommand;
use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\LeaderboardSnapshot;
use App\Service\PragueCalendar;
use App\Tests\Support\IntegrationTestCase;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Uuid;

/**
 * When a competition is detected as truly over, the `CompetitionEnded` domain
 * event drives an immediate FINAL leaderboard snapshot for today (Prague),
 * decoupled from the S11 final-standing notifications.
 */
final class CaptureFinalSnapshotOnCompetitionEndedTest extends IntegrationTestCase
{
    public function testEndingCompetitionCapturesFinalSnapshotForToday(): void
    {
        $this->messengerAsyncTransport()->reset();

        // VERIFIED_USER tips the exact 2:1; entering that final score both evaluates
        // (10 pts: all four rules hit) and — the source being complete — ends the
        // competition, recording CompetitionEnded.
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
        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $handled = $this->handleAsyncCaptures();
        self::assertGreaterThanOrEqual(1, $handled, 'CompetitionEnded must dispatch a snapshot capture.');

        // Today's (2025-06-15) snapshot now reflects the FINAL standing, replacing
        // the seeded all-zeros rows: VERIFIED_USER 1st with 10 pts.
        $today = $this->snapshotFor(AppFixtures::VERIFIED_COMPETITION_ID, AppFixtures::VERIFIED_USER_ID, '2025-06-15');
        self::assertNotNull($today);
        self::assertSame(1, $today->rank);
        self::assertSame(10, $today->points);

        // Yesterday's seeded baseline snapshot is untouched (history preserved) —
        // the coherent all-zeros standing before any match finished.
        $yesterday = $this->snapshotFor(AppFixtures::VERIFIED_COMPETITION_ID, AppFixtures::VERIFIED_USER_ID, '2025-06-14');
        self::assertNotNull($yesterday);
        self::assertSame(1, $yesterday->rank);
        self::assertSame(0, $yesterday->points);
    }

    private function handleAsyncCaptures(): int
    {
        $handled = 0;

        foreach ($this->messengerAsyncTransport()->getSent() as $envelope) {
            $message = $envelope->getMessage();

            if ($message instanceof CaptureLeaderboardSnapshotsCommand) {
                $this->commandBus()->dispatch(new Envelope($message, [new ReceivedStamp('async')]));
                ++$handled;
            }
        }

        return $handled;
    }

    private function snapshotFor(string $competitionId, string $userId, string $day): ?LeaderboardSnapshot
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->createQueryBuilder()
            ->select('s')
            ->from(LeaderboardSnapshot::class, 's')
            ->where('s.competition = :competitionId')
            ->andWhere('s.user = :userId')
            ->andWhere('s.day = :day')
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('day', new \DateTimeImmutable($day.' 00:00:00', PragueCalendar::timezone()), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
