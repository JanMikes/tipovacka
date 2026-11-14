<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class NotificationSettingsRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(NotificationSettings $settings): void
    {
        $this->entityManager->persist($settings);
    }

    public function findForUser(Uuid $userId): ?NotificationSettings
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(NotificationSettings::class, 's')
            ->where('s.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Every stored settings row keyed by the user's UUID (RFC4122) — the sweep's
     * one-statement lookup. Players absent from the map are on the defaults.
     *
     * @return array<string, NotificationSettings>
     */
    public function mapAll(): array
    {
        /** @var list<NotificationSettings> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(NotificationSettings::class, 's')
            ->getQuery()
            ->getResult();

        $map = [];

        foreach ($rows as $row) {
            $map[$row->user->id->toRfc4122()] = $row;
        }

        return $map;
    }
}
