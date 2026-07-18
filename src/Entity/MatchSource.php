<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Enum\MatchSourceKind;
use App\Event\MatchSourceCreated;
use App\Event\MatchSourceDeleted;
use App\Event\MatchSourceFinished;
use App\Event\MatchSourceUpdated;
use App\Exception\MatchSourceAlreadyFinished;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'match_sources')]
#[ORM\Index(columns: ['kind', 'finished_at', 'deleted_at'], name: 'IDX_match_sources_kind_active')]
#[ORM\Index(columns: ['owner_id', 'kind', 'deleted_at'], name: 'IDX_match_sources_owner_kind')]
class MatchSource implements EntityWithEvents, SoftDeletable
{
    use HasEvents;
    use SoftDeletes;

    #[ORM\Column(length: 160)]
    public private(set) string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public private(set) ?string $description;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $startAt;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $endAt;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $finishedAt = null;

    public bool $isCurated {
        get => MatchSourceKind::Curated === $this->kind;
    }

    public bool $isFinished {
        get => null !== $this->finishedAt;
    }

    public bool $isActive {
        get => null === $this->finishedAt && null === $this->deletedAt;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Sport::class)]
        #[ORM\JoinColumn(name: 'sport_id', referencedColumnName: 'id', nullable: false)]
        private(set) Sport $sport,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $owner,
        #[ORM\Column(enumType: MatchSourceKind::class)]
        private(set) MatchSourceKind $kind,
        string $name,
        ?string $description,
        ?\DateTimeImmutable $startAt,
        ?\DateTimeImmutable $endAt,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
        $this->updatedAt = $this->createdAt;

        $this->recordThat(new MatchSourceCreated(
            matchSourceId: $this->id,
            ownerId: $this->owner->id,
            kind: $this->kind,
            name: $this->name,
            occurredOn: $this->createdAt,
        ));
    }

    public function updateDetails(
        string $name,
        ?string $description,
        ?\DateTimeImmutable $startAt,
        ?\DateTimeImmutable $endAt,
        \DateTimeImmutable $now,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
        $this->updatedAt = $now;

        $this->recordThat(new MatchSourceUpdated(
            matchSourceId: $this->id,
            occurredOn: $now,
        ));
    }

    public function markFinished(\DateTimeImmutable $now): void
    {
        if ($this->isFinished) {
            throw MatchSourceAlreadyFinished::withId($this->id);
        }

        $this->finishedAt = $now;
        $this->updatedAt = $now;

        $this->recordThat(new MatchSourceFinished(
            matchSourceId: $this->id,
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

        $this->recordThat(new MatchSourceDeleted(
            matchSourceId: $this->id,
            ownerId: $this->owner->id,
            name: $this->name,
            occurredOn: $now,
        ));
    }
}
