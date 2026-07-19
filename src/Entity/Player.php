<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Per-source roster pool. Players are created implicitly when an organizer types
 * a new scorer name in the score-entry form; there is no standalone roster CRUD.
 */
#[ORM\Entity]
#[ORM\Table(name: 'players')]
#[ORM\UniqueConstraint(name: 'UNIQ_players_source_team_name', columns: ['match_source_id', 'team_name', 'name'])]
class Player
{
    /** Shared cap for player names — column length AND every input path (forms, guess scorer tips). */
    public const int NAME_MAX_LENGTH = 120;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: MatchSource::class)]
        #[ORM\JoinColumn(name: 'match_source_id', referencedColumnName: 'id', nullable: false)]
        private(set) MatchSource $matchSource,
        #[ORM\Column(length: self::NAME_MAX_LENGTH)]
        private(set) string $teamName,
        #[ORM\Column(length: self::NAME_MAX_LENGTH)]
        private(set) string $name,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
    }
}
