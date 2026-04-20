<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tournament_rule_configurations')]
#[ORM\UniqueConstraint(name: 'UIDX_rule_config_tournament_rule', columns: ['tournament_id', 'rule_identifier'])]
class TournamentRuleConfiguration
{
    #[ORM\Column]
    public private(set) bool $enabled;

    #[ORM\Column]
    public private(set) int $points;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Tournament::class)]
        #[ORM\JoinColumn(name: 'tournament_id', referencedColumnName: 'id', nullable: false)]
        private(set) Tournament $tournament,
        #[ORM\Column(length: 64)]
        private(set) string $ruleIdentifier,
        bool $enabled,
        int $points,
        \DateTimeImmutable $now,
    ) {
        $this->enabled = $enabled;
        $this->points = $points;
        $this->updatedAt = $now;
    }

    public function enable(int $points, \DateTimeImmutable $now): void
    {
        $this->enabled = true;
        $this->points = $points;
        $this->updatedAt = $now;
    }

    public function disable(\DateTimeImmutable $now): void
    {
        $this->enabled = false;
        $this->updatedAt = $now;
    }

    public function updatePoints(int $points, \DateTimeImmutable $now): void
    {
        $this->points = $points;
        $this->updatedAt = $now;
    }
}
