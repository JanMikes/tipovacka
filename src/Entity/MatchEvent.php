<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Exception\InvalidMatchEvent;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A single timeline event of a match (goal / yellow card / red card).
 *
 * Goal events are the scorer source of truth for the `scorer_hit` rule; cards
 * feed the timeline and the future fantasy features. Intentionally NO unique
 * constraint — a player can score (or be booked) repeatedly in one match.
 */
#[ORM\Entity]
#[ORM\Table(name: 'match_events')]
#[ORM\Index(columns: ['sport_match_id'], name: 'IDX_match_events_sport_match')]
class MatchEvent
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: SportMatch::class)]
        #[ORM\JoinColumn(name: 'sport_match_id', referencedColumnName: 'id', nullable: false)]
        private(set) SportMatch $sportMatch,
        #[ORM\Column(enumType: MatchEventType::class)]
        private(set) MatchEventType $type,
        #[ORM\Column(enumType: MatchSide::class)]
        private(set) MatchSide $side,
        #[ORM\Column(nullable: true)]
        private(set) ?int $minute,
        #[ORM\ManyToOne(targetEntity: Player::class)]
        #[ORM\JoinColumn(name: 'player_id', referencedColumnName: 'id', nullable: false)]
        private(set) Player $player,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        if (null !== $minute && ($minute < 0 || $minute > 150)) {
            throw InvalidMatchEvent::minuteOutOfRange($minute);
        }
    }
}
