<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Explicit competition ↔ match link, used only when the competition's
 * selection mode is Subset. Selection wins over `Competition::$includePlayoff`
 * — an explicitly selected playoff match counts.
 */
#[ORM\Entity]
#[ORM\Table(name: 'competition_match_selections')]
#[ORM\UniqueConstraint(name: 'UIDX_competition_match_selections_competition_match', columns: ['competition_id', 'sport_match_id'])]
class CompetitionMatchSelection
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
        #[ORM\ManyToOne(targetEntity: SportMatch::class)]
        #[ORM\JoinColumn(name: 'sport_match_id', referencedColumnName: 'id', nullable: false)]
        private(set) SportMatch $sportMatch,
        #[ORM\Column]
        private(set) \DateTimeImmutable $addedAt,
    ) {
    }
}
