<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class NotificationRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Notification $notification): void
    {
        $this->entityManager->persist($notification);
    }

    public function find(Uuid $id): ?Notification
    {
        return $this->entityManager->find(Notification::class, $id);
    }

    /**
     * A single notification scoped to its owner — the read-through / mark-read
     * controllers must never touch another user's row.
     */
    public function findForUser(Uuid $id, Uuid $userId): ?Notification
    {
        return $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->where('n.id = :id')
            ->andWhere('n.user = :userId')
            ->setParameter('id', $id)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The user's feed, newest first (unread float naturally by createdAt). Only
     * in-app-visible rows appear — email-only dedup rows stay hidden.
     *
     * @return list<Notification>
     */
    public function listForUser(Uuid $userId, int $limit, int $offset = 0): array
    {
        /** @var list<Notification> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.inAppVisible = true')
            ->setParameter('userId', $userId)
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** Total feed size (visible rows only) — powers center pagination. */
    public function countForUser(Uuid $userId): int
    {
        /** @var int $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.inAppVisible = true')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function countUnreadForUser(Uuid $userId): int
    {
        /** @var int $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.inAppVisible = true')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * Whether a deduped notification already exists for this user/type/key —
     * the Notifier's idempotency guard.
     */
    /**
     * Notifications of one type written since a moment, newest last — the input
     * of the e-mail digests, which turn many recent in-app rows into one mail.
     * Only rows carrying a dedup key are returned: a keyless row cannot be
     * identified in a digest and would be re-sent on every sweep.
     *
     * @return list<Notification>
     */
    public function listSince(NotificationType $type, \DateTimeImmutable $since): array
    {
        /** @var list<Notification> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('n', 'u', 'c')
            ->from(Notification::class, 'n')
            ->join('n.user', 'u')
            ->leftJoin('n.competition', 'c')
            ->where('n.type = :type')
            ->andWhere('n.createdAt >= :since')
            ->andWhere('n.dedupKey IS NOT NULL')
            ->setParameter('type', $type)
            ->setParameter('since', $since)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function existsForDedup(Uuid $userId, NotificationType $type, string $dedupKey): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.type = :type')
            ->andWhere('n.dedupKey = :dedupKey')
            ->setParameter('userId', $userId)
            ->setParameter('type', $type)
            ->setParameter('dedupKey', $dedupKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    /**
     * The subset of the given dedup keys that already have a row for this
     * user/type — the batch companion of {@see existsForDedup} for sweeps whose
     * ONE delivery covers several logical reminders at once.
     *
     * @param list<string> $dedupKeys
     *
     * @return list<string>
     */
    public function existingDedupKeys(Uuid $userId, NotificationType $type, array $dedupKeys): array
    {
        if ([] === $dedupKeys) {
            return [];
        }

        /** @var list<array{dedupKey: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT n.dedupKey AS dedupKey')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.type = :type')
            ->andWhere('n.dedupKey IN (:dedupKeys)')
            ->setParameter('userId', $userId)
            ->setParameter('type', $type)
            ->setParameter('dedupKeys', $dedupKeys)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'dedupKey');
    }

    public function markAllRead(Uuid $userId, \DateTimeImmutable $now): int
    {
        /** @var int $affected */
        $affected = $this->entityManager->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.readAt', ':now')
            ->where('n.user = :userId')
            ->andWhere('n.inAppVisible = true')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();

        return $affected;
    }

    /**
     * Drops every notification of a type tied to a competition — used when a
     * match source is reopened so a stale `competition_ended` standing (and its
     * dedup marker) is cleared and can re-send after re-completion.
     */
    public function deleteByCompetitionAndType(Uuid $competitionId, NotificationType $type): int
    {
        /** @var int $affected */
        $affected = $this->entityManager->createQueryBuilder()
            ->delete(Notification::class, 'n')
            ->where('n.competition = :competitionId')
            ->andWhere('n.type = :type')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('type', $type)
            ->getQuery()
            ->execute();

        return $affected;
    }
}
