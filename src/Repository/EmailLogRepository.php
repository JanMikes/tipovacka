<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailLog;
use App\Enum\EmailLogStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final class EmailLogRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(EmailLog $entry): void
    {
        $this->entityManager->persist($entry);
    }

    public function find(Uuid $id): ?EmailLog
    {
        return $this->entityManager->find(EmailLog::class, $id);
    }

    /**
     * Newest first. `$search` matches the address or the subject, case-insensitively.
     *
     * @return list<EmailLog>
     */
    public function search(?string $search, ?EmailLogStatus $status, int $limit, int $offset): array
    {
        /** @var list<EmailLog> $result */
        $result = $this->filtered($search, $status)
            ->select('l', 'u')
            ->leftJoin('l.user', 'u')
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function count(?string $search, ?EmailLogStatus $status): int
    {
        /** @var int $count */
        $count = $this->filtered($search, $status)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /** Deletes every row older than the cutoff; returns how many went. */
    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        /** @var int $affected */
        $affected = $this->entityManager->createQueryBuilder()
            ->delete(EmailLog::class, 'l')
            ->where('l.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        return $affected;
    }

    private function filtered(?string $search, ?EmailLogStatus $status): QueryBuilder
    {
        $builder = $this->entityManager->createQueryBuilder()->from(EmailLog::class, 'l');

        if (null !== $search && '' !== trim($search)) {
            $builder
                ->andWhere('LOWER(l.recipient) LIKE :search OR LOWER(l.subject) LIKE :search')
                ->setParameter('search', '%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%');
        }

        if (null !== $status) {
            $builder->andWhere('l.status = :status')->setParameter('status', $status);
        }

        return $builder;
    }
}
