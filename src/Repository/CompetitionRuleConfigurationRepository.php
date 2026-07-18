<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompetitionRuleConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

// Non-final so PHPUnit can stub it in unit tests for services that depend on it.
class CompetitionRuleConfigurationRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CompetitionRuleConfiguration $configuration): void
    {
        $this->entityManager->persist($configuration);
    }

    public function findOne(Uuid $competitionId, string $ruleIdentifier): ?CompetitionRuleConfiguration
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(CompetitionRuleConfiguration::class, 'c')
            ->where('c.competition = :competitionId')
            ->andWhere('c.ruleIdentifier = :ruleIdentifier')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('ruleIdentifier', $ruleIdentifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * All stored rows for the competition (enabled AND disabled), indexed by rule
     * identifier. Stored rows always win over the rule's `enabledByDefault` fallback,
     * so both the evaluator and the configuration query need the full map.
     *
     * @return array<string, CompetitionRuleConfiguration>
     */
    public function mapForCompetition(Uuid $competitionId): array
    {
        /** @var list<CompetitionRuleConfiguration> $configurations */
        $configurations = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(CompetitionRuleConfiguration::class, 'c')
            ->where('c.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('c.ruleIdentifier', 'ASC')
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($configurations as $configuration) {
            $indexed[$configuration->ruleIdentifier] = $configuration;
        }

        return $indexed;
    }
}
