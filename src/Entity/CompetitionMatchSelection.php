<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Explicit layer ↔ match link, used only when the layer's selection mode is
 * Subset. Selection wins over `CompetitionSource::$includePlayoff` — an
 * explicitly selected playoff match counts.
 *
 * The row carries BOTH its layer and its competition: the layer is the
 * semantic owner (it is what the scope filter branches on), the competition is
 * kept denormalised because a match may appear in a competition only once, and
 * that guarantee is a competition-wide unique index.
 */
#[ORM\Entity]
#[ORM\Table(name: 'competition_match_selections')]
#[ORM\UniqueConstraint(name: 'UIDX_competition_match_selections_competition_match', columns: ['competition_id', 'sport_match_id'])]
#[ORM\Index(columns: ['competition_source_id'], name: 'IDX_competition_match_selections_source')]
class CompetitionMatchSelection
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
        #[ORM\ManyToOne(targetEntity: SportMatch::class)]
        #[ORM\JoinColumn(name: 'sport_match_id', referencedColumnName: 'id', nullable: false)]
        private(set) SportMatch $sportMatch,
        #[ORM\Column]
        private(set) \DateTimeImmutable $addedAt,
    ) {
    }
}
