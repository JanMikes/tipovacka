<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MatchSourceRuleConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

// Non-final so PHPUnit can stub it in unit tests for services that depend on it.
class MatchSourceRuleConfigurationRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(MatchSourceRuleConfiguration $configuration): void
    {
        $this->entityManager->persist($configuration);
    }

    public function findOne(Uuid $matchSourceId, string $ruleIdentifier): ?MatchSourceRuleConfiguration
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(MatchSourceRuleConfiguration::class, 'c')
            ->where('c.matchSource = :matchSourceId')
            ->andWhere('c.ruleIdentifier = :ruleIdentifier')
            ->setParameter('matchSourceId', $matchSourceId)
            ->setParameter('ruleIdentifier', $ruleIdentifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<MatchSourceRuleConfiguration>
     */
    public function listForMatchSource(Uuid $matchSourceId): array
    {
        /** @var list<MatchSourceRuleConfiguration> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(MatchSourceRuleConfiguration::class, 'c')
            ->where('c.matchSource = :matchSourceId')
            ->setParameter('matchSourceId', $matchSourceId)
            ->orderBy('c.ruleIdentifier', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<string, MatchSourceRuleConfiguration>
     */
    public function getEnabledForMatchSource(Uuid $matchSourceId): array
    {
        /** @var list<MatchSourceRuleConfiguration> $configurations */
        $configurations = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(MatchSourceRuleConfiguration::class, 'c')
            ->where('c.matchSource = :matchSourceId')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('matchSourceId', $matchSourceId)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($configurations as $configuration) {
            $indexed[$configuration->ruleIdentifier] = $configuration;
        }

        return $indexed;
    }
}
