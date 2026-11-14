<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Command\PruneEmailLog\PruneEmailLogCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\EmailLog;
use App\Enum\EmailFrequency;
use App\Enum\EmailLogStatus;
use App\Enum\NotificationType;
use App\Event\PasswordResetRequested;
use App\Repository\EmailLogRepository;
use App\Service\Notification\Notifier;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Uid\Uuid;

/**
 * The e-mail audit log is written by the mailer's own events, so these tests
 * do what production does: a mail is queued, then the queue is consumed (the
 * `ReceivedStamp` makes the bus HANDLE the message — i.e. actually send it
 * through the test transport — instead of queueing it again).
 */
final class EmailLogRecorderTest extends IntegrationTestCase
{
    public function testAQueuedMailIsNotLoggedUntilItActuallyLeaves(): void
    {
        $this->requestPasswordReset();

        self::assertSame(0, $this->log()->count(null, null));

        $this->consumeQueuedMails();

        self::assertSame(1, $this->log()->count(null, null));
    }

    public function testASentMailIsLoggedWithItsRecipientKindAndRedactedText(): void
    {
        $this->requestPasswordReset('very-secret-reset-token-0123456789abcdef');
        $this->consumeQueuedMails();

        $entry = $this->log()->search(null, null, 10, 0)[0];

        self::assertSame(EmailLogStatus::Sent, $entry->status);
        self::assertSame(AppFixtures::VERIFIED_USER_EMAIL, $entry->recipient);
        self::assertSame('Nastavte si nové heslo k Wtips', $entry->subject);
        self::assertSame('password_reset', $entry->template);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $entry->user?->id->toRfc4122());
        self::assertNull($entry->error);

        // What the mail said is kept; the link that would take over the account is not.
        self::assertNotNull($entry->body);
        self::assertStringContainsString('Nastavit nové heslo', $entry->body);
        self::assertStringNotContainsString('very-secret-reset-token', $entry->body);
        self::assertStringContainsString('/…', $entry->body);
    }

    public function testANotificationMailIsLoggedLikeAnyOther(): void
    {
        $this->setEmailFrequency(AppFixtures::VERIFIED_USER_ID, EmailFrequency::Immediate);
        $user = $this->entityManager()->find(\App\Entity\User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);

        self::getContainer()->get(Notifier::class)->notify($user, NotificationType::GuessReminder, 'Chybí vám tipy', 'Tělo.', url: '/zapasy');
        $this->entityManager()->flush();
        $this->consumeQueuedMails();

        $entry = $this->log()->search('chybí vám', null, 10, 0)[0] ?? null;

        self::assertNotNull($entry);
        self::assertSame('notification', $entry->template);
        // The never-expiring unsubscribe token is not kept either.
        self::assertStringContainsString('/e-maily/…', (string) $entry->body);
    }

    public function testAFailedAttemptIsLoggedWithTheReasonAndWhetherItWillBeRetried(): void
    {
        $email = (new TemplatedEmail())
            ->to('nekdo@example.com')
            ->subject('Potvrďte svůj e-mail a můžete tipovat')
            ->htmlTemplate('emails/verify_email.html.twig');
        $failure = static fn (Envelope $envelope): HandlerFailedException => new HandlerFailedException(
            $envelope,
            [new TransportException('Expected response code "250" but got code "421", with message "421 Try again later".')],
        );

        // First attempt: Messenger's own retry listener (which runs before the
        // recorder) schedules a retry.
        $first = new Envelope(new SendEmailMessage($email));
        $this->dispatcher()->dispatch(new WorkerMessageFailedEvent($first, 'async', $failure($first)));

        // Last attempt: the retries (max_retries: 4) are used up.
        $last = new Envelope(new SendEmailMessage($email), [new RedeliveryStamp(4)]);
        $this->dispatcher()->dispatch(new WorkerMessageFailedEvent($last, 'async', $failure($last)));

        $entries = $this->log()->search(null, EmailLogStatus::Failed, 10, 0);
        self::assertCount(2, $entries);

        $errors = array_map(static fn (EmailLog $entry): string => (string) $entry->error, $entries);
        sort($errors);

        self::assertStringContainsString('421 Try again later', $errors[0]);
        self::assertStringContainsString('(vzdáno, další pokus nebude)', $errors[0]);
        self::assertStringContainsString('(zkusí se znovu)', $errors[1]);

        self::assertSame('nekdo@example.com', $entries[0]->recipient);
        self::assertSame('verify_email', $entries[0]->template);
        self::assertNull($entries[0]->user, 'no account behind this address');
        self::assertNull($entries[0]->body, 'a failed mail was never rendered');
    }

    public function testAFailureOfSomethingThatIsNotAMailIsIgnored(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->dispatcher()->dispatch(new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException('boom')));

        self::assertSame(0, $this->log()->count(null, null));
    }

    public function testSearchMatchesAddressOrSubjectAndPruningDropsOnlyOldRows(): void
    {
        $this->requestPasswordReset();
        $this->consumeQueuedMails();

        self::assertSame(1, $this->log()->count('USER@tipovacka', null));
        self::assertSame(1, $this->log()->count('nové heslo', null));
        self::assertSame(0, $this->log()->count('nic takového', null));
        self::assertSame(0, $this->log()->count(null, EmailLogStatus::Failed));

        // Today's row survives a prune…
        $this->commandBus()->dispatch(new PruneEmailLogCommand());
        self::assertSame(1, $this->log()->count(null, null));

        // …and is gone once it is older than the retention.
        $clock = $this->clock();
        self::assertInstanceOf(\Symfony\Component\Clock\MockClock::class, $clock);
        $clock->modify(sprintf('+%d days', EmailLog::RETENTION_DAYS + 1));

        $this->commandBus()->dispatch(new PruneEmailLogCommand());
        self::assertSame(0, $this->log()->count(null, null));
    }

    private function requestPasswordReset(string $token = 'token'): void
    {
        $this->eventBus()->dispatch(new PasswordResetRequested(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
            resetToken: $token,
            occurredOn: \DateTimeImmutable::createFromInterface($this->clock()->now()),
        ));
    }

    /** What the worker does: hand every queued mail to the bus as RECEIVED, so it is sent. */
    private function consumeQueuedMails(): void
    {
        $transport = $this->messengerAsyncTransport();

        foreach ($transport->getSent() as $envelope) {
            if ($envelope->getMessage() instanceof SendEmailMessage) {
                $this->commandBus()->dispatch(new Envelope($envelope->getMessage(), [new ReceivedStamp('async')]));
            }
        }

        $transport->reset();
        $this->entityManager()->clear();
    }

    private function dispatcher(): EventDispatcherInterface
    {
        /* @var EventDispatcherInterface */
        return self::getContainer()->get('event_dispatcher');
    }

    private function log(): EmailLogRepository
    {
        /* @var EmailLogRepository */
        return self::getContainer()->get(EmailLogRepository::class);
    }
}
