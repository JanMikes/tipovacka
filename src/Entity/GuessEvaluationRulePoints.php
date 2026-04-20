<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'guess_evaluation_rule_points')]
#[ORM\UniqueConstraint(name: 'UIDX_eval_rule_points', columns: ['evaluation_id', 'rule_identifier'])]
class GuessEvaluationRulePoints
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: GuessEvaluation::class, inversedBy: 'rulePoints')]
        #[ORM\JoinColumn(name: 'evaluation_id', referencedColumnName: 'id', nullable: false)]
        private(set) GuessEvaluation $evaluation,
        #[ORM\Column(length: 64)]
        private(set) string $ruleIdentifier,
        #[ORM\Column]
        private(set) int $points,
    ) {
    }
}
