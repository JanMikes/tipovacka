<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Event\GuessesEvaluatedForMatch;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class NotifyMatchEvaluatedTest extends IntegrationTestCase
{
    private const string DEDUP_KEY = 'match_evaluated:'.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID.':'.AppFixtures::VERIFIED_COMPETITION_ID;

    public function testFinishingMatchNotifiesGuesserWithPointsAndRank(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $notification = $this->notificationByDedup(self::DEDUP_KEY);

        self::assertNotNull($notification);
        self::assertSame(NotificationType::MatchEvaluated, $notification->type);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $notification->user->id->toRfc4122());
        // 2:1 exact hit in this fixture competition scores 10 points; guesser is rank 1.
        self::assertStringContainsString('10 b.', $notification->body);
        self::assertStringContainsString('1. v soutěži', $notification->body);
    }

    public function testEvaluationNotificationFiresOncePerCompetition(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        self::assertSame(1, $this->countByDedup(self::DEDUP_KEY));

        // A repeat event (e.g. an async retry) must not create a duplicate.
        $this->eventBus()->dispatch(new GuessesEvaluatedForMatch(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            occurredOn: \DateTimeImmutable::createFromInterface($this->clock()->now()),
        ));

        self::assertSame(1, $this->countByDedup(self::DEDUP_KEY));
    }

    private function notificationByDedup(string $dedupKey): ?Notification
    {
        return $this->entityManager()->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->where('n.dedupKey = :key')
            ->setParameter('key', $dedupKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function countByDedup(string $dedupKey): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.dedupKey = :key')
            ->setParameter('key', $dedupKey)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
