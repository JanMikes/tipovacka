<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A team the competition is filtered to, used only when the competition's
 * selection mode is Teams. The competition then includes every source match
 * where a filter team plays (home OR away) — dynamically, so a team match
 * imported later (e.g. a playoff fixture) auto-joins. Mirrors
 * {@see CompetitionMatchSelection}, but keyed on the team identity instead of a
 * single match.
 */
#[ORM\Entity]
#[ORM\Table(name: 'competition_team_filters')]
#[ORM\UniqueConstraint(name: 'UIDX_competition_team_filters_competition_team', columns: ['competition_id', 'team_id'])]
class CompetitionTeamFilter
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
        #[ORM\ManyToOne(targetEntity: Team::class)]
        #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: false)]
        private(set) Team $team,
        #[ORM\Column]
        private(set) \DateTimeImmutable $addedAt,
    ) {
    }
}
