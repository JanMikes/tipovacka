<?php

declare(strict_types=1);

namespace App\Entity;

use App\Event\MemberJoinedGroup;
use App\Event\MemberLeftGroup;
use App\Event\MemberRemoved;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'memberships')]
#[ORM\Index(columns: ['group_id', 'left_at'], name: 'IDX_memberships_group_active')]
#[ORM\Index(columns: ['user_id', 'left_at'], name: 'IDX_memberships_user_active')]
class Membership implements EntityWithEvents
{
    use HasEvents;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $leftAt = null;

    public bool $isActive {
        get => null === $this->leftAt;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Group::class)]
        #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false)]
        private(set) Group $group,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $user,
        #[ORM\Column]
        private(set) \DateTimeImmutable $joinedAt,
    ) {
        $this->recordThat(new MemberJoinedGroup(
            membershipId: $this->id,
            groupId: $this->group->id,
            userId: $this->user->id,
            occurredOn: $this->joinedAt,
        ));
    }

    public function leave(\DateTimeImmutable $now): void
    {
        if (null !== $this->leftAt) {
            return;
        }

        $this->leftAt = $now;

        $this->recordThat(new MemberLeftGroup(
            membershipId: $this->id,
            groupId: $this->group->id,
            userId: $this->user->id,
            occurredOn: $now,
        ));
    }

    public function removeBy(Uuid $removedByUserId, \DateTimeImmutable $now): void
    {
        if (null !== $this->leftAt) {
            return;
        }

        $this->leftAt = $now;

        $this->recordThat(new MemberRemoved(
            membershipId: $this->id,
            groupId: $this->group->id,
            userId: $this->user->id,
            removedByUserId: $removedByUserId,
            occurredOn: $now,
        ));
    }
}
