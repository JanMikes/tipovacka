<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Event\GroupCreated;
use App\Event\GroupDeleted;
use App\Event\GroupPinRegenerated;
use App\Event\GroupPinRevoked;
use App\Event\GroupShareableLinkRegenerated;
use App\Event\GroupShareableLinkRevoked;
use App\Event\GroupUpdated;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'user_groups')]
#[ORM\Index(columns: ['tournament_id', 'deleted_at'], name: 'IDX_user_groups_tournament')]
#[ORM\Index(columns: ['owner_id', 'deleted_at'], name: 'IDX_user_groups_owner')]
#[ORM\UniqueConstraint(name: 'UIDX_user_groups_pin', columns: ['pin'], options: ['where' => '(pin IS NOT NULL)'])]
#[ORM\UniqueConstraint(name: 'UIDX_user_groups_shareable_link_token', columns: ['shareable_link_token'], options: ['where' => '(shareable_link_token IS NOT NULL)'])]
class Group implements EntityWithEvents, SoftDeletable
{
    use HasEvents;
    use SoftDeletes;

    #[ORM\Column(length: 160)]
    public private(set) string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public private(set) ?string $description;

    #[ORM\Column(length: 8, nullable: true)]
    public private(set) ?string $pin;

    #[ORM\Column(length: 48, nullable: true)]
    public private(set) ?string $shareableLinkToken;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public bool $isNotDeleted {
        get => null === $this->deletedAt;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Tournament::class)]
        #[ORM\JoinColumn(name: 'tournament_id', referencedColumnName: 'id', nullable: false)]
        private(set) Tournament $tournament,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $owner,
        string $name,
        ?string $description,
        ?string $pin,
        ?string $shareableLinkToken,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->pin = $pin;
        $this->shareableLinkToken = $shareableLinkToken;
        $this->updatedAt = $this->createdAt;

        $this->recordThat(new GroupCreated(
            groupId: $this->id,
            tournamentId: $this->tournament->id,
            ownerId: $this->owner->id,
            name: $this->name,
            occurredOn: $this->createdAt,
        ));
    }

    public function updateDetails(string $name, ?string $description, \DateTimeImmutable $now): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = $now;

        $this->recordThat(new GroupUpdated(
            groupId: $this->id,
            occurredOn: $now,
        ));
    }

    public function setPin(string $pin, \DateTimeImmutable $now): void
    {
        $this->pin = $pin;
        $this->updatedAt = $now;

        $this->recordThat(new GroupPinRegenerated(
            groupId: $this->id,
            occurredOn: $now,
        ));
    }

    public function revokePin(\DateTimeImmutable $now): void
    {
        if (null === $this->pin) {
            return;
        }

        $this->pin = null;
        $this->updatedAt = $now;

        $this->recordThat(new GroupPinRevoked(
            groupId: $this->id,
            occurredOn: $now,
        ));
    }

    public function setShareableLinkToken(string $token, \DateTimeImmutable $now): void
    {
        $this->shareableLinkToken = $token;
        $this->updatedAt = $now;

        $this->recordThat(new GroupShareableLinkRegenerated(
            groupId: $this->id,
            occurredOn: $now,
        ));
    }

    public function revokeShareableLinkToken(\DateTimeImmutable $now): void
    {
        if (null === $this->shareableLinkToken) {
            return;
        }

        $this->shareableLinkToken = null;
        $this->updatedAt = $now;

        $this->recordThat(new GroupShareableLinkRevoked(
            groupId: $this->id,
            occurredOn: $now,
        ));
    }

    public function softDelete(\DateTimeImmutable $now): void
    {
        if (null !== $this->deletedAt) {
            return;
        }

        $this->markDeleted($now);
        $this->updatedAt = $now;

        $this->recordThat(new GroupDeleted(
            groupId: $this->id,
            ownerId: $this->owner->id,
            name: $this->name,
            occurredOn: $now,
        ));
    }
}
