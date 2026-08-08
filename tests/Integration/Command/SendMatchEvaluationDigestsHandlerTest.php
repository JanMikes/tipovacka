<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SendMatchEvaluationDigests\SendMatchEvaluationDigestsCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Notification;
use App\Entity\NotificationPreference;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The burst problem: an automated feed finishes a whole round in one pass, so
 * mailing on the per-match trigger would send one mail per zápas — something
 * manual result entry never produced, because a human enters results one at a
 * time. The in-app feed keeps its per-match rows; the mail is one digest.
 */
final class SendMatchEvaluationDigestsHandlerTest extends IntegrationTestCase
{
    public function testEvaluatingAMatchWritesAnInAppRowButSendsNoMailOnItsOwn(): void
    {
        $this->enableEvaluationEmails();
        $this->guessAndFinish();

        self::assertNotNull($this->notificationByDedup($this->perMatchKey()));
        self::assertCount(0, self::getMailerMessages(), 'the event handler is in-app only');
    }

    public function testDigestMailsOnceForTheEvaluatedMatches(): void
    {
        $this->enableEvaluationEmails();
        $this->guessAndFinish();

        $this->commandBus()->dispatch(new SendMatchEvaluationDigestsCommand());

        self::assertCount(1, self::getMailerMessages());
    }

    public function testASecondSweepDoesNotMailTheSameMatchAgain(): void
    {
        $this->enableEvaluationEmails();
        $this->guessAndFinish();

        $this->commandBus()->dispatch(new SendMatchEvaluationDigestsCommand());
        $this->commandBus()->dispatch(new SendMatchEvaluationDigestsCommand());

        self::assertCount(1, self::getMailerMessages(), 'the digest stamps every key it covered');
    }

    /** A user who has not asked for evaluation e-mails still gets none. */
    public function testDigestRespectsTheUsersEmailPreference(): void
    {
        $this->guessAndFinish();

        $this->commandBus()->dispatch(new SendMatchEvaluationDigestsCommand());

        self::assertCount(0, self::getMailerMessages());
    }

    private function guessAndFinish(): void
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
    }

    /** `match_evaluated` defaults to in-app only; the digest needs email ON. */
    private function enableEvaluationEmails(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-01 09:00:00 UTC');
        $preferences = self::getContainer()->get(NotificationPreferenceRepository::class);

        $existing = $preferences->findOne(
            Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            NotificationType::MatchEvaluated,
        );

        if ($existing instanceof NotificationPreference) {
            $existing->change(inApp: true, email: true, now: $now);
            $em->flush();

            return;
        }

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertInstanceOf(User::class, $user);

        $em->persist(new NotificationPreference(
            id: Uuid::v7(),
            user: $user,
            type: NotificationType::MatchEvaluated,
            inApp: true,
            email: true,
            createdAt: $now,
        ));
        $em->flush();
    }

    private function perMatchKey(): string
    {
        return sprintf(
            'match_evaluated:%s:%s',
            AppFixtures::MATCH_PRIVATE_SCHEDULED_ID,
            AppFixtures::VERIFIED_COMPETITION_ID,
        );
    }

    private function notificationByDedup(string $dedupKey): ?Notification
    {
        $repository = self::getContainer()->get(NotificationRepository::class);

        foreach ($repository->listSince(NotificationType::MatchEvaluated, new \DateTimeImmutable('2025-06-01 00:00:00 UTC')) as $notification) {
            if ($notification->dedupKey === $dedupKey) {
                return $notification;
            }
        }

        return null;
    }
}
