<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TournamentRuleConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

// Non-final so PHPUnit can stub it in unit tests for services that depend on it.
class TournamentRuleConfigurationRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(TournamentRuleConfiguration $configuration): void
    {
        $this->entityManager->persist($configuration);
    }

    public function findOne(Uuid $tournamentId, string $ruleIdentifier): ?TournamentRuleConfiguration
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(TournamentRuleConfiguration::class, 'c')
            ->where('c.tournament = :tournamentId')
            ->andWhere('c.ruleIdentifier = :ruleIdentifier')
            ->setParameter('tournamentId', $tournamentId)
            ->setParameter('ruleIdentifier', $ruleIdentifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<TournamentRuleConfiguration>
     */
    public function listForTournament(Uuid $tournamentId): array
    {
        /** @var list<TournamentRuleConfiguration> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(TournamentRuleConfiguration::class, 'c')
            ->where('c.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournamentId)
            ->orderBy('c.ruleIdentifier', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<string, TournamentRuleConfiguration>
     */
    public function getEnabledForTournament(Uuid $tournamentId): array
    {
        /** @var list<TournamentRuleConfiguration> $configurations */
        $configurations = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(TournamentRuleConfiguration::class, 'c')
            ->where('c.tournament = :tournamentId')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('tournamentId', $tournamentId)
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
