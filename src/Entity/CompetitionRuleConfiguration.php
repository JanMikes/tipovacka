<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'competition_rule_configurations')]
#[ORM\UniqueConstraint(name: 'UIDX_rule_config_competition_rule', columns: ['competition_id', 'rule_identifier'])]
class CompetitionRuleConfiguration
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
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
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
