<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'competition_match_settings')]
#[ORM\UniqueConstraint(name: 'UIDX_competition_match_settings_competition_match', columns: ['competition_id', 'sport_match_id'])]
class CompetitionMatchSetting
{
    #[ORM\Column]
    public private(set) \DateTimeImmutable $deadline;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

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
        \DateTimeImmutable $deadline,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->deadline = $deadline;
        $this->updatedAt = $this->createdAt;
    }

    public function updateDeadline(\DateTimeImmutable $deadline, \DateTimeImmutable $now): void
    {
        $this->deadline = $deadline;
        $this->updatedAt = $now;
    }
}
