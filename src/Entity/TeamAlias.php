<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * An alternative spelling of a Team's name („Viktoria Plzen", „FC Viktoria
 * Plzeň") that resolves to the same team. Feeds and imports spell club names
 * their own way; without a mapping the exact-name TeamResolver would silently
 * create a duplicate directory team and split its matches across two
 * identities. Aliases participate in name resolution with the same scope rule
 * as the team itself (global per sport / local per private source) — see
 * TeamResolver.
 *
 * A lookup entity like Team/Player: no domain events, no soft delete.
 * Case-insensitive matching lives in the repository; the DB constraint is
 * case-sensitive per team (house contract, same as team/player names).
 * Cross-team conflicts within a scope are guarded at write time
 * (AddTeamAliasHandler), not by the schema.
 */
#[ORM\Entity]
#[ORM\Table(name: 'team_aliases')]
#[ORM\UniqueConstraint(name: 'UNIQ_team_aliases_team_alias', columns: ['team_id', 'alias'])]
#[ORM\Index(columns: ['alias'], name: 'IDX_team_aliases_alias')]
class TeamAlias
{
    #[ORM\Column(length: Team::NAME_MAX_LENGTH)]
    public private(set) string $alias;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Team::class)]
        #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: false)]
        private(set) Team $team,
        string $alias,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->alias = $alias;
    }
}
