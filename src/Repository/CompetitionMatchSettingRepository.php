<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompetitionMatchSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CompetitionMatchSettingRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CompetitionMatchSetting $setting): void
    {
        $this->entityManager->persist($setting);
    }

    public function remove(CompetitionMatchSetting $setting): void
    {
        $this->entityManager->remove($setting);
    }

    /**
     * Every competition's override row for ONE match — the cross-competition
     * view a match-level change (a moved kickoff) has to reconcile.
     *
     * @return list<CompetitionMatchSetting>
     */
    public function listByMatch(Uuid $sportMatchId): array
    {
        /** @var list<CompetitionMatchSetting> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSetting::class, 's')
            ->where('s.sportMatch = :sportMatchId')
            ->setParameter('sportMatchId', $sportMatchId)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findByCompetitionAndMatch(Uuid $competitionId, Uuid $sportMatchId): ?CompetitionMatchSetting
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSetting::class, 's')
            ->where('s.competition = :competitionId')
            ->andWhere('s.sportMatch = :sportMatchId')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('sportMatchId', $sportMatchId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<Uuid> $sportMatchIds
     *
     * @return array<string, CompetitionMatchSetting> keyed by sport match id RFC4122
     */
    public function findByCompetitionAndMatches(Uuid $competitionId, array $sportMatchIds): array
    {
        if ([] === $sportMatchIds) {
            return [];
        }

        /** @var list<CompetitionMatchSetting> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSetting::class, 's')
            ->where('s.competition = :competitionId')
            ->andWhere('s.sportMatch IN (:sportMatchIds)')
            ->setParameter('competitionId', $competitionId)
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
