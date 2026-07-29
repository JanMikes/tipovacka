<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionMatchProgress;

use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Repository\CompetitionRepository;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * One aggregate statement — never a per-match loop.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionMatchProgressQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionMatchProvider $matchProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetCompetitionMatchProgress $query): CompetitionMatchProgressResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);

        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'COUNT(m.id) AS matchCount',
                'SUM(CASE WHEN m.state = :mp_finished THEN 1 ELSE 0 END) AS finishedMatchCount',
                'SUM(CASE WHEN m.state = :mp_live THEN 1 ELSE 0 END) AS liveMatchCount',
            )
            ->from(SportMatch::class, 'm')
            ->andWhere('m.state != :mp_cancelled')
            ->setParameter('mp_finished', SportMatchState::Finished)
            ->setParameter('mp_live', SportMatchState::Live)
            ->setParameter('mp_cancelled', SportMatchState::Cancelled);

        $this->matchProvider->applyCompetitionMatchFilter($qb, 'm', $competition);

        /** @var array{matchCount: int|string, finishedMatchCount: int|string|null, liveMatchCount: int|string|null} $row */
        $row = $qb->getQuery()->getSingleResult();

        return new CompetitionMatchProgressResult(
            matchCount: (int) $row['matchCount'],
            finishedMatchCount: (int) ($row['finishedMatchCount'] ?? 0),
            liveMatchCount: (int) ($row['liveMatchCount'] ?? 0),
        );
    }
}
