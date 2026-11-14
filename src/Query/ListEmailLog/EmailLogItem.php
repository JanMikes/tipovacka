<?php

declare(strict_types=1);

namespace App\Query\ListEmailLog;

use App\Entity\EmailLog;
use App\Enum\EmailLogStatus;
use Symfony\Component\Uid\Uuid;

/** One logged mail attempt, as the admin pages show it. */
final readonly class EmailLogItem
{
    public function __construct(
        public Uuid $id,
        public \DateTimeImmutable $createdAt,
        public string $recipient,
        public string $subject,
        public ?string $template,
        public EmailLogStatus $status,
        public ?string $error,
        public ?string $messageId,
        /** The plain-text part with its links redacted; null for a failed attempt. */
        public ?string $body,
        public ?Uuid $userId,
        public ?string $userName,
    ) {
    }

    public static function fromEntry(EmailLog $entry): self
    {
        return new self(
            id: $entry->id,
            createdAt: $entry->createdAt,
            recipient: $entry->recipient,
            subject: $entry->subject,
            template: $entry->template,
            status: $entry->status,
            error: $entry->error,
            messageId: $entry->messageId,
            body: $entry->body,
            userId: $entry->user?->id,
            userName: $entry->user?->displayName,
        );
    }
}
