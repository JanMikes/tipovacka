<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GroupMatchSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class GroupMatchSettingRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(GroupMatchSetting $setting): void
    {
        $this->entityManager->persist($setting);
    }

    public function remove(GroupMatchSetting $setting): void
    {
        $this->entityManager->remove($setting);
    }

    public function findByGroupAndMatch(Uuid $groupId, Uuid $sportMatchId): ?GroupMatchSetting
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(GroupMatchSetting::class, 's')
            ->where('s.group = :groupId')
            ->andWhere('s.sportMatch = :sportMatchId')
            ->setParameter('groupId', $groupId)
            ->setParameter('sportMatchId', $sportMatchId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<Uuid> $sportMatchIds
     *
     * @return array<string, GroupMatchSetting> keyed by sport match id RFC4122
     */
    public function findByGroupAndMatches(Uuid $groupId, array $sportMatchIds): array
    {
        if ([] === $sportMatchIds) {
            return [];
        }

        /** @var list<GroupMatchSetting> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(GroupMatchSetting::class, 's')
            ->where('s.group = :groupId')
            ->andWhere('s.sportMatch IN (:sportMatchIds)')
            ->setParameter('groupId', $groupId)
            ->setParameter('sportMatchIds', $sportMatchIds)
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($rows as $row) {
            $result[$row->sportMatch->id->toRfc4122()] = $row;
        }

        return $result;
    }
}
