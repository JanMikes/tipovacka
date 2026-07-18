<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Event\CompetitionCreated;
use App\Event\CompetitionDeleted;
use App\Event\CompetitionPinRegenerated;
use App\Event\CompetitionPinRevoked;
use App\Event\CompetitionShareableLinkRegenerated;
use App\Event\CompetitionShareableLinkRevoked;
use App\Event\CompetitionUpdated;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'competitions')]
#[ORM\Index(columns: ['match_source_id', 'deleted_at'], name: 'IDX_competitions_match_source')]
#[ORM\Index(columns: ['owner_id', 'deleted_at'], name: 'IDX_competitions_owner')]
#[ORM\UniqueConstraint(name: 'UIDX_competitions_pin', columns: ['pin'], options: ['where' => '(pin IS NOT NULL)'])]
#[ORM\UniqueConstraint(name: 'UIDX_competitions_shareable_link_token', columns: ['shareable_link_token'], options: ['where' => '(shareable_link_token IS NOT NULL)'])]
class Competition implements EntityWithEvents, SoftDeletable
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
    public private(set) bool $hideOthersTipsBeforeDeadline = false;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $tipsDeadline = null;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public bool $isNotDeleted {
        get => null === $this->deletedAt;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: MatchSource::class)]
        #[ORM\JoinColumn(name: 'match_source_id', referencedColumnName: 'id', nullable: false)]
        private(set) MatchSource $matchSource,
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

        $this->recordThat(new CompetitionCreated(
            competitionId: $this->id,
            matchSourceId: $this->matchSource->id,
            ownerId: $this->owner->id,
            name: $this->name,
            occurredOn: $this->createdAt,
        ));
    }

    public function updateDetails(
        string $name,
        ?string $description,
        bool $hideOthersTipsBeforeDeadline,
        ?\DateTimeImmutable $tipsDeadline,
        \DateTimeImmutable $now,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->hideOthersTipsBeforeDeadline = $hideOthersTipsBeforeDeadline;
        $this->tipsDeadline = $tipsDeadline;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionUpdated(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    public function setPin(string $pin, \DateTimeImmutable $now): void
    {
        $this->pin = $pin;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionPinRegenerated(
            competitionId: $this->id,
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

        $this->recordThat(new CompetitionPinRevoked(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    public function setShareableLinkToken(string $token, \DateTimeImmutable $now): void
    {
        $this->shareableLinkToken = $token;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionShareableLinkRegenerated(
            competitionId: $this->id,
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

        $this->recordThat(new CompetitionShareableLinkRevoked(
            competitionId: $this->id,
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

        $this->recordThat(new CompetitionDeleted(
            competitionId: $this->id,
            ownerId: $this->owner->id,
            name: $this->name,
            occurredOn: $now,
        ));
    }
}
