<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A member's standing (rank + points) in a competition on a single Prague
 * calendar day — the raw material for leaderboard Δ (movement vs the previous
 * snapshot day). See .docs/DOMAIN.md §Leaderboard delta.
 *
 * Exactly one row per (competition, user, day): re-capturing a day replaces its
 * rows (see {@see \App\Repository\LeaderboardSnapshotRepository::upsertDay}), so
 * a recalculation or a same-day sweep never piles up duplicates.
 */
#[ORM\Entity]
#[ORM\Table(name: 'leaderboard_snapshots')]
#[ORM\Index(columns: ['competition_id', 'day'], name: 'IDX_leaderboard_snapshots_competition_day')]
#[ORM\UniqueConstraint(name: 'UIDX_leaderboard_snapshots_competition_user_day', columns: ['competition_id', 'user_id', 'day'])]
class LeaderboardSnapshot
{
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
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private(set) \DateTimeImmutable $day,
        #[ORM\Column]
        private(set) int $points,
        #[ORM\Column]
        private(set) int $rank,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
    }
}
