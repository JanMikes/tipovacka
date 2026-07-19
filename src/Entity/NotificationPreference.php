<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A user's per-type channel preference. A missing row means "use the type's
 * {@see NotificationType::defaultInApp} / {@see NotificationType::defaultEmail}
 * defaults" — rows exist only for types the user has explicitly changed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'notification_preferences')]
#[ORM\UniqueConstraint(name: 'UIDX_notification_preferences_user_type', columns: ['user_id', 'type'])]
class NotificationPreference
{
    #[ORM\Column]
    public private(set) bool $inApp;

    #[ORM\Column]
    public private(set) bool $email;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $user,
        #[ORM\Column(enumType: NotificationType::class)]
        private(set) NotificationType $type,
        bool $inApp,
        bool $email,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->inApp = $inApp;
        $this->email = $email;
        $this->updatedAt = $this->createdAt;
    }

    public function change(bool $inApp, bool $email, \DateTimeImmutable $now): void
    {
        $this->inApp = $inApp;
        $this->email = $email;
        $this->updatedAt = $now;
    }
}
