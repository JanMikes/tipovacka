<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\EmailLog;
use App\Enum\EmailLogStatus;
use App\Repository\EmailLogRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Writes the {@see EmailLog}: one row for every mail the relay accepted and one
 * for every attempt that failed. It hooks the mailer itself, so EVERY mail is
 * covered — account mail, invitations, notifications — without any sender
 * having to remember to log.
 *
 * All mail leaves through the Messenger worker (`SendEmailMessage` → `async`),
 * which shapes where each row can be written:
 *
 *  - **sent** — on the mailer's `SentMessageEvent`, inside the worker's
 *    transaction: the row commits together with the message being handled;
 *  - **failed** — NOT on the mailer's own failure event: that fires inside the
 *    same transaction, which is then rolled back and would take the row with
 *    it. Instead on Messenger's `WorkerMessageFailedEvent`, dispatched after the
 *    rollback, where the row is flushed on its own. (The one place outside
 *    DataFixtures that calls `flush()` — there is no bus middleware around a
 *    worker event to do it.)
 *
 * The template's name is gone by the time a mail counts as sent (rendering
 * clears it), so it is remembered per message object when the transport is
 * about to render it.
 *
 * Logging is a side effect and must never cost a player their mail, nor send
 * it twice: every failure in here is caught and reported, never rethrown.
 */
final class EmailLogRecorder implements EventSubscriberInterface
{
    private const int MAX_ERROR_LENGTH = 2000;

    /** @var \WeakMap<RawMessage, string> template slug per message about to be sent */
    private \WeakMap $templates;

    public function __construct(
        private readonly EmailLogRepository $emailLogRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProvideIdentity $identityProvider,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
        $this->templates = new \WeakMap();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before the body renderer (priority 0), which clears the template name.
            MessageEvent::class => ['rememberTemplate', 100],
            SentMessageEvent::class => 'recordSent',
            WorkerMessageFailedEvent::class => 'recordFailed',
        ];
    }

    public function rememberTemplate(MessageEvent $event): void
    {
        $message = $event->getMessage();

        // `queued` = the copy rendered when a mail is put ON the queue; only the
        // one the transport is about to send is the one that will be reported sent.
        if ($event->isQueued() || !$message instanceof TemplatedEmail) {
            return;
        }

        $template = self::templateSlug($message);

        if (null !== $template) {
            $this->templates[$message] = $template;
        }
    }

    public function recordSent(SentMessageEvent $event): void
    {
        try {
            $sent = $event->getMessage();
            $message = $sent->getOriginalMessage();

            if (!$message instanceof Email) {
                return;
            }

            $body = $message->getTextBody();

            $this->emailLogRepository->save($this->entry(
                $message,
                EmailLogStatus::Sent,
                template: $this->templates[$message] ?? null,
                body: is_string($body) ? EmailBodyRedactor::redact($body) : null,
                messageId: $sent->getMessageId(),
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('E-mail log: could not record a sent mail', ['exception' => $e]);
        }
    }

    public function recordFailed(WorkerMessageFailedEvent $event): void
    {
        $queued = $event->getEnvelope()->getMessage();

        if (!$queued instanceof SendEmailMessage) {
            return;
        }

        try {
            $message = $queued->getMessage();

            if (!$message instanceof Email) {
                return;
            }

            $error = $event->getThrowable();

            if ($error instanceof HandlerFailedException) {
                $error = $error->getPrevious() ?? $error;
            }

            // The handler's transaction was rolled back: nothing it left in the
            // entity manager may ride along with this row.
            $this->entityManager->clear();

            $this->emailLogRepository->save($this->entry(
                $message,
                EmailLogStatus::Failed,
                template: $message instanceof TemplatedEmail ? self::templateSlug($message) : null,
                error: mb_substr(sprintf(
                    '%s%s',
                    $error->getMessage(),
                    $event->willRetry() ? ' (zkusí se znovu)' : ' (vzdáno, další pokus nebude)',
                ), 0, self::MAX_ERROR_LENGTH),
            ));
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->warning('E-mail log: could not record a failed mail', ['exception' => $e]);
        }
    }

    private function entry(
        Email $message,
        EmailLogStatus $status,
        ?string $template,
        ?string $body = null,
        ?string $error = null,
        ?string $messageId = null,
    ): EmailLog {
        $addresses = array_map(static fn (Address $address): string => $address->getAddress(), $message->getTo());

        return new EmailLog(
            id: $this->identityProvider->next(),
            recipient: mb_substr(implode(', ', $addresses), 0, 500),
            subject: (string) $message->getSubject(),
            template: $template,
            status: $status,
            createdAt: \DateTimeImmutable::createFromInterface($this->clock->now()),
            user: 1 === count($addresses) ? $this->userRepository->findByEmail($addresses[0]) : null,
            body: $body,
            error: $error,
            messageId: null !== $messageId ? mb_substr($messageId, 0, 255) : null,
        );
    }

    /** `emails/password_reset.html.twig` → `password_reset`. */
    private static function templateSlug(TemplatedEmail $message): ?string
    {
        $template = $message->getHtmlTemplate() ?? $message->getTextTemplate();

        if (null === $template) {
            return null;
        }

        return mb_substr((string) preg_replace('/\.(html|txt)\.twig$/', '', basename($template)), 0, 120);
    }
}
