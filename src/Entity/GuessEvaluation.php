<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'guess_evaluations')]
class GuessEvaluation
{
    #[ORM\Column]
    public private(set) int $totalPoints = 0;

    /**
     * @var Collection<int, GuessEvaluationRulePoints>
     */
    #[ORM\OneToMany(
        targetEntity: GuessEvaluationRulePoints::class,
        mappedBy: 'evaluation',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    public private(set) Collection $rulePoints;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\OneToOne(targetEntity: Guess::class)]
        #[ORM\JoinColumn(name: 'guess_id', referencedColumnName: 'id', nullable: false, unique: true)]
        private(set) Guess $guess,
        #[ORM\Column]
        private(set) \DateTimeImmutable $evaluatedAt,
    ) {
        $this->rulePoints = new ArrayCollection();
    }

    public function addRulePoints(GuessEvaluationRulePoints $rulePoints): void
    {
        $this->rulePoints->add($rulePoints);
        $this->totalPoints += $rulePoints->points;
    }
}
