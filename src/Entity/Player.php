<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Per-team roster pool. Players are created implicitly when an organizer types a
 * new scorer name in the score-entry form (or a tipper picks one); there is no
 * standalone roster CRUD. A player belongs to a Team, so the roster is global
 * for a global (curated-directory) team and local for a private source's team —
 * whichever the match's team resolves to.
 */
#[ORM\Entity]
#[ORM\Table(name: 'players')]
#[ORM\UniqueConstraint(name: 'UNIQ_players_team_name', columns: ['team_id', 'name'])]
class Player
{
    /** Shared cap for player names — column length AND every input path (forms, guess scorer tips). */
    public const int NAME_MAX_LENGTH = 120;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Team::class)]
        #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: false)]
        private(set) Team $team,
        #[ORM\Column(length: self::NAME_MAX_LENGTH)]
        private(set) string $name,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
    }
}
