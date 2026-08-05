<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A team the layer is filtered to, used only when the layer's selection mode is
 * Teams. The competition then includes every match OF THAT LAYER'S SOURCE where
 * a filter team plays (home OR away) — dynamically, so a team match imported
 * later (e.g. a playoff fixture) auto-joins. Mirrors
 * {@see CompetitionMatchSelection}, but keyed on the team identity instead of a
 * single match.
 *
 * Uniqueness is per LAYER, not per competition: one global directory team plays
 * in several curated zdroje (Sparta in Chance Lize and in Lize mistrů), and
 * filtering both is the point of a multi-source soutěž.
 */
#[ORM\Entity]
#[ORM\Table(name: 'competition_team_filters')]
#[ORM\UniqueConstraint(name: 'UIDX_competition_team_filters_source_team', columns: ['competition_source_id', 'team_id'])]
#[ORM\Index(columns: ['competition_id'], name: 'IDX_competition_team_filters_competition')]
class CompetitionTeamFilter
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
        #[ORM\ManyToOne(targetEntity: CompetitionSource::class)]
        #[ORM\JoinColumn(name: 'competition_source_id', referencedColumnName: 'id', nullable: false)]
        private(set) CompetitionSource $competitionSource,
        #[ORM\ManyToOne(targetEntity: Team::class)]
        #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: false)]
        private(set) Team $team,
        #[ORM\Column]
        private(set) \DateTimeImmutable $addedAt,
    ) {
    }
}
