<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use App\Exception\TeamNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Team lookups. Name matching is case-insensitive at the application layer (the
 * DB unique indexes are case-sensitive, same contract as PlayerRepository): the
 * lookup finds „Sparta" for „sparta", the stored row keeps its first-seen casing.
 */
final class TeamRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Team $team): void
    {
        $this->entityManager->persist($team);
    }

    public function find(Uuid $id): ?Team
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t', 's', 'ms')
            ->from(Team::class, 't')
            ->join('t.sport', 's')
            ->leftJoin('t.matchSource', 'ms')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): Team
    {
        return $this->find($id) ?? throw TeamNotFound::withId($id);
    }

    /** A global directory team (matchSource IS NULL) for a sport, by case-insensitive name. */
    public function findGlobalByName(Uuid $sportId, string $name): ?Team
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->where('t.sport = :sport')
            ->andWhere('t.matchSource IS NULL')
            ->andWhere('LOWER(t.name) = LOWER(:name)')
            ->setParameter('sport', $sportId)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** A local team of a private source, by case-insensitive name. */
    public function findLocalByName(Uuid $matchSourceId, string $name): ?Team
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->where('t.matchSource = :source')
            ->andWhere('LOWER(t.name) = LOWER(:name)')
            ->setParameter('source', $matchSourceId)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The whole global directory for a sport, alphabetised (admin directory list).
     *
     * @return list<Team>
     */
    public function listGlobalBySport(Uuid $sportId): array
    {
        /** @var list<Team> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->where('t.sport = :sport')
            ->andWhere('t.matchSource IS NULL')
            ->setParameter('sport', $sportId)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Global-directory autocomplete for the team picker on a curated source.
     *
     * @return list<Team>
     */
    public function searchGlobalBySport(Uuid $sportId, string $term = ''): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->where('t.sport = :sport')
            ->andWhere('t.matchSource IS NULL')
            ->setParameter('sport', $sportId)
            ->orderBy('t.name', 'ASC')
            ->setMaxResults(20);

        if ('' !== $term) {
            $qb->andWhere('LOWER(t.name) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($term).'%');
        }

        /** @var list<Team> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Local-team autocomplete for the team picker on a private source.
     *
     * @return list<Team>
     */
    public function searchLocalBySource(Uuid $matchSourceId, string $term = ''): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->where('t.matchSource = :source')
            ->setParameter('source', $matchSourceId)
            ->orderBy('t.name', 'ASC')
            ->setMaxResults(20);

        if ('' !== $term) {
            $qb->andWhere('LOWER(t.name) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($term).'%');
        }

        /** @var list<Team> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
