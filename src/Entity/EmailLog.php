<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EmailLogStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One attempt to send one mail — the audit trail behind the admin „Odeslané
 * e-maily" page, answering „did we write to this person, when, what, and did
 * the relay take it". Written only by
 * {@see \App\Service\Email\EmailLogRecorder}; never changed afterwards.
 *
 * A mail that fails and is retried leaves one row per attempt — the retries
 * ARE the story when a relay greylists us.
 *
 * `body` is the plain-text part with every link reduced to where it leads
 * ({@see \App\Service\Email\EmailBodyRedactor}): a log must not become a store
 * of working password-reset and invitation links. It is null for a failed
 * attempt — the mail is rendered only on the way out.
 *
 * Personal data (an address, a subject) kept for debugging: rows older than
 * {@see self::RETENTION_DAYS} days are deleted by `app:email-log:prune`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'email_log')]
#[ORM\Index(columns: ['created_at'], name: 'IDX_email_log_created')]
#[ORM\Index(columns: ['recipient'], name: 'IDX_email_log_recipient')]
class EmailLog
{
    public const int RETENTION_DAYS = 90;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        /** The To address(es), comma-separated — in practice always one. */
        #[ORM\Column(length: 500)]
        private(set) string $recipient,
        #[ORM\Column(type: Types::TEXT)]
        private(set) string $subject,
        /** Which mail it was, e.g. `password_reset` — the template's name without its path and extension. */
        #[ORM\Column(length: 120, nullable: true)]
        private(set) ?string $template,
        #[ORM\Column(enumType: EmailLogStatus::class)]
        private(set) EmailLogStatus $status,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
        /** The account behind the address, when there is one — set to null if that account is deleted. */
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
        private(set) ?User $user = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private(set) ?string $body = null,
        /** Why the attempt failed, as the transport reported it. */
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private(set) ?string $error = null,
        /** The relay's id for an accepted mail — what to quote when asking the provider about it. */
        #[ORM\Column(length: 255, nullable: true)]
        private(set) ?string $messageId = null,
    ) {
    }
}
