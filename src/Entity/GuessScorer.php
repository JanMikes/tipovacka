<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MatchSide;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One guessed scorer of a guess (v1 „trefený střelec" rule). Players come from
 * the source's roster pool — free-typed names create a Player the same way the
 * organizer's score-entry sheet does. A player can be tipped at most once per
 * guess (unique constraint); the scorer_hit rule counts correct ones.
 *
 * The submitted side is PERSISTED (like MatchEvent), never re-derived from the
 * player's team name — a team rename or casing drift must not flip the side.
 */
#[ORM\Entity]
#[ORM\Table(name: 'guess_scorers')]
#[ORM\UniqueConstraint(name: 'UIDX_guess_scorers_guess_player', columns: ['guess_id', 'player_id'])]
class GuessScorer
{
    /** Business cap: at most 5 scorer tips per guess (enforced on the command path). */
    public const int MAX_PER_GUESS = 5;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Guess::class, inversedBy: 'scorers')]
        #[ORM\JoinColumn(name: 'guess_id', referencedColumnName: 'id', nullable: false)]
        private(set) Guess $guess,
        #[ORM\ManyToOne(targetEntity: Player::class)]
        #[ORM\JoinColumn(name: 'player_id', referencedColumnName: 'id', nullable: false)]
        private(set) Player $player,
        #[ORM\Column(enumType: MatchSide::class)]
        private(set) MatchSide $side,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
    }
}
