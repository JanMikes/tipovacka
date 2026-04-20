<?php

declare(strict_types=1);

namespace App\Query\GetSportMatchDetail;

use App\Entity\SportMatch;
use App\Exception\SportMatchNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetSportMatchDetailQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetSportMatchDetail $query): SportMatchDetailResult
    {
        $sportMatch = $this->entityManager->createQueryBuilder()
            ->select('m', 't')
            ->from(SportMatch::class, 'm')
            ->innerJoin('m.tournament', 't')
            ->where('m.id = :id')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('id', $query->sportMatchId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$sportMatch instanceof SportMatch) {
            throw SportMatchNotFound::withId($query->sportMatchId);
        }

        return new SportMatchDetailResult(
            id: $sportMatch->id,
            tournamentId: $sportMatch->tournament->id,
            tournamentName: $sportMatch->tournament->name,
            homeTeam: $sportMatch->homeTeam,
            awayTeam: $sportMatch->awayTeam,
            kickoffAt: $sportMatch->kickoffAt,
            venue: $sportMatch->venue,
            state: $sportMatch->state,
            homeScore: $sportMatch->homeScore,
            awayScore: $sportMatch->awayScore,
            isOpenForGuesses: $sportMatch->isOpenForGuesses,
        );
    }
}
