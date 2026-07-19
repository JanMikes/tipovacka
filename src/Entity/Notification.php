<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A single in-app notification for one user. Content (title / body / url) is
 * pre-rendered Czech at creation time — old rows never resolve templates at
 * read time, so copy changes never rewrite history. Created exclusively by
 * {@see \App\Service\Notification\Notifier}.
 */
#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(columns: ['user_id', 'read_at', 'created_at'], name: 'IDX_notifications_user_feed')]
#[ORM\Index(columns: ['user_id', 'type', 'dedup_key'], name: 'IDX_notifications_dedup')]
class Notification
{
    #[ORM\Column(length: 160)]
    public private(set) string $title;

    #[ORM\Column(type: Types::TEXT)]
    public private(set) string $body;

    #[ORM\Column(length: 512, nullable: true)]
    public private(set) ?string $url;

    /**
     * Free-form structured context captured at creation (e.g. competition id,
     * points, rank). Not used for rendering — the Czech copy is already baked
     * into {@see $title} / {@see $body}.
     *
     * @var array<string, scalar|null>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public private(set) ?array $payload;

    /**
     * Idempotency key for repeatable notifications (reminders per deadline-day,
     * balance-low per competition-day, …). When set, the Notifier refuses a
     * second delivery for the same `(user, type, dedupKey)`.
     */
    #[ORM\Column(length: 191, nullable: true)]
    public private(set) ?string $dedupKey;

    /**
     * Whether this row is shown in the bell / center. A {@see \App\Service\Notification\Notifier}
     * writes a row whenever it delivers on ANY channel (so the dedup guard is
     * channel-agnostic) and sets this to the user's in-app preference — an
     * email-only delivery leaves an invisible row that dedups future sends but
     * never surfaces in the feed. The feed / unread-count queries filter on it.
     */
    #[ORM\Column(options: ['default' => true])]
    public private(set) bool $inAppVisible = true;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $readAt = null;

    public bool $isRead {
        get => null !== $this->readAt;
    }

    /**
     * @param array<string, scalar|null>|null $payload
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $user,
        #[ORM\Column(enumType: NotificationType::class)]
        private(set) NotificationType $type,
        string $title,
        string $body,
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
        private(set) ?Competition $competition,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
        ?string $url = null,
        ?array $payload = null,
        ?string $dedupKey = null,
        bool $inAppVisible = true,
    ) {
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
        $this->payload = $payload;
        $this->dedupKey = $dedupKey;
        $this->inAppVisible = $inAppVisible;
    }

    public function markRead(\DateTimeImmutable $now): void
    {
        if (null !== $this->readAt) {
            return;
        }

        $this->readAt = $now;
    }
}
