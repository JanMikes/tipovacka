<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'leaderboard_tie_resolutions')]
#[ORM\UniqueConstraint(name: 'UIDX_tie_resolution', columns: ['group_id', 'user_id'])]
class LeaderboardTieResolution
{
    #[ORM\Column]
    public private(set) int $rank;

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
        int $rank,
        #[ORM\Column]
        private(set) \DateTimeImmutable $resolvedAt,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'resolved_by_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $resolvedBy,
    ) {
        $this->rank = $rank;
    }
}
