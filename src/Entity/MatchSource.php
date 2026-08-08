<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Enum\FeedProvider;
use App\Enum\MatchSourceKind;
use App\Event\MatchSourceCompleted;
use App\Event\MatchSourceCreated;
use App\Event\MatchSourceDeleted;
use App\Event\MatchSourceReopened;
use App\Event\MatchSourceUpdated;
use App\Exception\MatchSourceAlreadyCompleted;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'match_sources')]
#[ORM\Index(columns: ['kind', 'completed_at', 'deleted_at'], name: 'IDX_match_sources_kind_active')]
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
    public private(set) ?\DateTimeImmutable $completedAt = null;

    /** Which external feed maintains this source's matches; null = manual entry. */
    #[ORM\Column(nullable: true, enumType: FeedProvider::class)]
    public private(set) ?FeedProvider $feedProvider = null;

    /**
     * The provider's competition reference — FAČR soutěž code („2026001A1A"),
     * vendor league/season id, or a JSON path for FeedProvider::Fixture.
     */
    #[ORM\Column(length: 160, nullable: true)]
    public private(set) ?string $feedRef = null;

    /**
     * When this source was last fetched from its provider. Drives the poll
     * cadence (see App\Service\Feed\FeedPollPolicy) so a source with nothing
     * playing is fetched once a day instead of every five minutes — null means
     * never polled, which is always due.
     */
    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $feedPolledAt = null;

    public bool $isCurated {
        get => MatchSourceKind::Curated === $this->kind;
    }

    public bool $hasFeed {
        get => null !== $this->feedProvider && null !== $this->feedRef;
    }

    public bool $isCompleted {
        get => null !== $this->completedAt;
    }

    public bool $isActive {
        get => null === $this->completedAt && null === $this->deletedAt;
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

    /** Bind (or re-point) this source to an external feed. Curated sources only — the caller enforces it. */
    public function bindFeed(FeedProvider $provider, string $ref, \DateTimeImmutable $now): void
    {
        // Pointing a source at a DIFFERENT feed makes everything the old one
        // taught us stale, the poll stamp included: the next fetch is a first
        // fetch, which is what tells an adapter to look at the whole season
        // instead of a window (SportmonksMatchDataProvider) — and the whole
        // season is what app:matches:adopt-external-ids has to see to bridge it.
        if ($provider !== $this->feedProvider || $ref !== $this->feedRef) {
            $this->feedPolledAt = null;
        }

        $this->feedProvider = $provider;
        $this->feedRef = $ref;
        $this->updatedAt = $now;

        $this->recordThat(new MatchSourceUpdated(
            matchSourceId: $this->id,
            occurredOn: $now,
        ));
    }

    /**
     * Stamp a completed fetch. Deliberately does NOT touch `updatedAt` or record
     * a domain event — polling is bookkeeping, not a change to the source.
     */
    public function markFeedPolled(\DateTimeImmutable $now): void
    {
        $this->feedPolledAt = $now;
    }

    /** Back to manual maintenance; existing matches keep their externalId links. */
    public function unbindFeed(\DateTimeImmutable $now): void
    {
        if (null === $this->feedProvider && null === $this->feedRef) {
            return;
        }

        $this->feedProvider = null;
        $this->feedRef = null;
        $this->updatedAt = $now;

        $this->recordThat(new MatchSourceUpdated(
            matchSourceId: $this->id,
            occurredOn: $now,
        ));
    }

    public function markCompleted(\DateTimeImmutable $now): void
    {
        if ($this->isCompleted) {
            throw MatchSourceAlreadyCompleted::withId($this->id);
        }

        $this->completedAt = $now;
        $this->updatedAt = $now;

        $this->recordThat(new MatchSourceCompleted(
            matchSourceId: $this->id,
            occurredOn: $now,
        ));
    }

    /**
     * Undo markCompleted() — e.g. when the „poslední zápas" checkbox was ticked
     * by mistake or a playoff match was added later. No-op when not completed.
     */
    public function reopen(\DateTimeImmutable $now): void
    {
        if (!$this->isCompleted) {
            return;
        }

        $this->completedAt = null;
        $this->updatedAt = $now;

        $this->recordThat(new MatchSourceReopened(
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
