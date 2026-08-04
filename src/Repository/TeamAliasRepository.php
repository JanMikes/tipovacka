<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamAlias;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Alias lookups mirror TeamRepository's scope rule: an alias resolves in the
 * GLOBAL directory of a sport or among the LOCAL teams of one private source,
 * case-insensitively at the application layer.
 */
final class TeamAliasRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(TeamAlias $alias): void
    {
        $this->entityManager->persist($alias);
    }

    /** The global-directory team (matchSource IS NULL) an alias points to, if any. */
    public function findGlobalTeamByAlias(Uuid $sportId, string $alias): ?Team
    {
        /** @var ?TeamAlias $row */
        $row = $this->entityManager->createQueryBuilder()
            ->select('a', 't')
            ->from(TeamAlias::class, 'a')
            ->join('a.team', 't')
            ->where('t.sport = :sport')
            ->andWhere('t.matchSource IS NULL')
            ->andWhere('LOWER(a.alias) = LOWER(:alias)')
            ->setParameter('sport', $sportId)
            ->setParameter('alias', $alias)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row?->team;
    }

    /** The local team of one private source an alias points to, if any. */
    public function findLocalTeamByAlias(Uuid $matchSourceId, string $alias): ?Team
    {
        /** @var ?TeamAlias $row */
        $row = $this->entityManager->createQueryBuilder()
            ->select('a', 't')
            ->from(TeamAlias::class, 'a')
            ->join('a.team', 't')
            ->where('t.matchSource = :source')
            ->andWhere('LOWER(a.alias) = LOWER(:alias)')
            ->setParameter('source', $matchSourceId)
            ->setParameter('alias', $alias)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row?->team;
    }

    /**
     * Aliases of one team, alphabetised (admin team detail).
     *
     * @return list<TeamAlias>
     */
    public function listByTeam(Uuid $teamId): array
    {
        /** @var list<TeamAlias> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(TeamAlias::class, 'a')
            ->where('a.team = :team')
            ->setParameter('team', $teamId)
            ->orderBy('a.alias', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
