<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CompetitionMatchSelectionMode;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One „vrstva" of a competition's match scope: a zdroj zápasů plus the rule
 * saying WHICH of its matches the competition takes. A competition's scope is
 * the UNION of its layers, so a soutěž can mix „všechny zápasy Chance Ligy",
 * „jen Spartiny zápasy v LM" and a handful of its own custom matches.
 *
 * The mode lives here and NOT on {@see Competition} because it is a per-source
 * answer: the same competition may take everything from one source and three
 * hand-picked fixtures from the next. {@see CompetitionMatchSelection} and
 * {@see CompetitionTeamFilter} rows hang off a layer for the same reason — two
 * `Teams` layers sharing a global directory team would otherwise filter each
 * other's matches.
 *
 * `addedAt` is the late-add anchor for a WHOLE layer, mirroring the per-row
 * `addedAt` of a subset selection: a source attached after the competition
 * locked gives its matches their own deadlines
 * ({@see \App\Service\EffectiveTipDeadlineResolver}).
 */
#[ORM\Entity]
#[ORM\Table(name: 'competition_sources')]
#[ORM\UniqueConstraint(name: 'UIDX_competition_sources_competition_source', columns: ['competition_id', 'match_source_id'])]
#[ORM\Index(columns: ['match_source_id'], name: 'IDX_competition_sources_match_source')]
class CompetitionSource
{
    /**
     * All ⇒ every match of this source; Subset ⇒ only the layer's
     * {@see CompetitionMatchSelection} rows; Teams ⇒ every match of this source
     * where a {@see CompetitionTeamFilter} team plays.
     */
    #[ORM\Column(enumType: CompetitionMatchSelectionMode::class)]
    public private(set) CompetitionMatchSelectionMode $selectionMode;

    /** Only meaningful in All mode — Subset/Teams always keep playoff matches. */
    #[ORM\Column]
    public private(set) bool $includePlayoff;

    /** Display + „primary source" order; the lowest position is the headline source. */
    #[ORM\Column]
    public private(set) int $position;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Competition::class, inversedBy: 'sourceLinks')]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
        #[ORM\ManyToOne(targetEntity: MatchSource::class)]
        #[ORM\JoinColumn(name: 'match_source_id', referencedColumnName: 'id', nullable: false)]
        private(set) MatchSource $matchSource,
        #[ORM\Column]
        private(set) \DateTimeImmutable $addedAt,
        CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All,
        bool $includePlayoff = true,
        int $position = 0,
    ) {
        $this->selectionMode = $selectionMode;
        // Outside All mode the flag does nothing (an explicitly picked or
        // team-matched playoff fixture always counts), so it is normalised here
        // rather than left to every read surface to remember.
        $this->includePlayoff = CompetitionMatchSelectionMode::All === $selectionMode ? $includePlayoff : true;
        $this->position = $position;
    }

    public function changeScope(CompetitionMatchSelectionMode $selectionMode, bool $includePlayoff): void
    {
        $this->selectionMode = $selectionMode;
        $this->includePlayoff = CompetitionMatchSelectionMode::All === $selectionMode ? $includePlayoff : true;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }
}
