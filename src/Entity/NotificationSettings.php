<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EmailFrequency;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A player's e-mail delivery settings — how often ({@see EmailFrequency}) and,
 * for the daily summary, at what hour. One row per user, written the first time
 * they change anything or the first time a summary is sent to them; a missing
 * row means {@see EmailFrequency::default()} at {@see self::DEFAULT_DIGEST_HOUR}.
 *
 * Separate from {@see NotificationPreference} on purpose: that table answers
 * „which types, on which channel", this one answers „how often may we write at
 * all" — a player who silences everything keeps their per-type switches intact
 * for the day they turn e-mail back on.
 */
#[ORM\Entity]
#[ORM\Table(name: 'notification_settings')]
#[ORM\UniqueConstraint(name: 'UIDX_notification_settings_user', columns: ['user_id'])]
class NotificationSettings
{
    /** 08:00 Europe/Prague — early enough that a summary still helps with that day's matches. */
    public const int DEFAULT_DIGEST_HOUR = 8;

    #[ORM\Column(enumType: EmailFrequency::class)]
    public private(set) EmailFrequency $emailFrequency;

    /** Hour of day (0–23, Europe/Prague) from which the daily summary may be sent. */
    #[ORM\Column]
    public private(set) int $digestHour;

    /**
     * When the last daily summary went out. The sweep sends at most one per
     * Prague calendar day, so this is the whole idempotency guard of the digest.
     */
    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $lastDigestAt = null;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private(set) User $user,
        EmailFrequency $emailFrequency,
        int $digestHour,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->emailFrequency = $emailFrequency;
        $this->digestHour = self::clampHour($digestHour);
        $this->updatedAt = $this->createdAt;
    }

    public function changeEmailDelivery(EmailFrequency $frequency, int $digestHour, \DateTimeImmutable $now): void
    {
        $this->emailFrequency = $frequency;
        $this->digestHour = self::clampHour($digestHour);
        $this->updatedAt = $now;
    }

    public function markDigestSent(\DateTimeImmutable $now): void
    {
        $this->lastDigestAt = $now;
    }

    private static function clampHour(int $hour): int
    {
        return max(0, min(23, $hour));
    }
}
