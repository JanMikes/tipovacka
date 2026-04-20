<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Enum\TournamentVisibility;
use App\Event\TournamentCreated;
use App\Event\TournamentDeleted;
use App\Event\TournamentFinished;
use App\Event\TournamentRulesChanged;
use App\Event\TournamentUpdated;
use App\Exception\TournamentAlreadyFinished;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tournaments')]
#[ORM\Index(columns: ['visibility', 'finished_at', 'deleted_at'], name: 'IDX_tournaments_public_active')]
#[ORM\Index(columns: ['owner_id', 'visibility', 'deleted_at'], name: 'IDX_tournaments_owner_visibility')]
class Tournament implements EntityWithEvents, SoftDeletable
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

    public bool $isPublic {
        get => TournamentVisibility::Public === $this->visibility;
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
        #[ORM\Column(enumType: TournamentVisibility::class)]
        private(set) TournamentVisibility $visibility,
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

        $this->recordThat(new TournamentCreated(
            tournamentId: $this->id,
            ownerId: $this->owner->id,
            visibility: $this->visibility,
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

        $this->recordThat(new TournamentUpdated(
            tournamentId: $this->id,
            occurredOn: $now,
        ));
    }

    public function markFinished(\DateTimeImmutable $now): void
    {
        if ($this->isFinished) {
            throw TournamentAlreadyFinished::withId($this->id);
        }

        $this->finishedAt = $now;
        $this->updatedAt = $now;

        $this->recordThat(new TournamentFinished(
            tournamentId: $this->id,
            occurredOn: $now,
        ));
    }

    public function recordRulesChanged(User $editor, \DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;

        $this->recordThat(new TournamentRulesChanged(
            tournamentId: $this->id,
            changedByUserId: $editor->id,
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

        $this->recordThat(new TournamentDeleted(
            tournamentId: $this->id,
            ownerId: $this->owner->id,
            name: $this->name,
            occurredOn: $now,
        ));
    }
}
