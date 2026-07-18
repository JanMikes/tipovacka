<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\JoinRequestDecision;
use App\Event\JoinRequestCreated;
use App\Event\JoinRequestRejected;
use App\Exception\CompetitionJoinRequestAlreadyDecided;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'competition_join_requests')]
#[ORM\Index(columns: ['competition_id', 'decided_at'], name: 'IDX_join_requests_competition_decided')]
#[ORM\Index(columns: ['user_id', 'decided_at'], name: 'IDX_join_requests_user_decided')]
#[ORM\UniqueConstraint(name: 'UIDX_join_requests_pending', columns: ['competition_id', 'user_id'], options: ['where' => '(decided_at IS NULL)'])]
class CompetitionJoinRequest implements EntityWithEvents
{
    use HasEvents;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $decidedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'decided_by_id', referencedColumnName: 'id', nullable: true)]
    public private(set) ?User $decidedBy = null;

    #[ORM\Column(enumType: JoinRequestDecision::class, nullable: true)]
    public private(set) ?JoinRequestDecision $decision = null;

    public bool $isDecided {
        get => null !== $this->decidedAt;
    }

    public bool $isApproved {
        get => JoinRequestDecision::Approved === $this->decision;
    }

    public bool $isRejected {
        get => JoinRequestDecision::Rejected === $this->decision;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $user,
        #[ORM\Column]
        private(set) \DateTimeImmutable $requestedAt,
    ) {
        $this->recordThat(new JoinRequestCreated(
            requestId: $this->id,
            competitionId: $this->competition->id,
            userId: $this->user->id,
            occurredOn: $this->requestedAt,
        ));
    }

    public function approve(User $decidedBy, \DateTimeImmutable $now): void
    {
        if ($this->isDecided) {
            throw CompetitionJoinRequestAlreadyDecided::create();
        }

        $this->decidedAt = $now;
        $this->decidedBy = $decidedBy;
        $this->decision = JoinRequestDecision::Approved;
    }

    public function reject(User $decidedBy, \DateTimeImmutable $now): void
    {
        if ($this->isDecided) {
            throw CompetitionJoinRequestAlreadyDecided::create();
        }

        $this->decidedAt = $now;
        $this->decidedBy = $decidedBy;
        $this->decision = JoinRequestDecision::Rejected;

        $this->recordThat(new JoinRequestRejected(
            requestId: $this->id,
            occurredOn: $now,
        ));
    }
}
