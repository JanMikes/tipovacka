<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationPreference;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class NotificationPreferenceRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(NotificationPreference $preference): void
    {
        $this->entityManager->persist($preference);
    }

    public function findOne(Uuid $userId, NotificationType $type): ?NotificationPreference
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(NotificationPreference::class, 'p')
            ->where('p.user = :userId')
            ->andWhere('p.type = :type')
            ->setParameter('userId', $userId)
            ->setParameter('type', $type)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * All the user's explicit preference rows, keyed by the type's string value.
     * Types absent from the map fall back to their {@see NotificationType} defaults.
     *
     * @return array<string, NotificationPreference>
     */
    public function mapForUser(Uuid $userId): array
    {
        /** @var list<NotificationPreference> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(NotificationPreference::class, 'p')
            ->where('p.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();

        $map = [];

        foreach ($rows as $row) {
            $map[$row->type->value] = $row;
        }

        return $map;
    }
}
