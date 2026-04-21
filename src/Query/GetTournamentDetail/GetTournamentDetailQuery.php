<?php

declare(strict_types=1);

namespace App\Query\GetTournamentDetail;

use App\Entity\Tournament;
use App\Exception\TournamentNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetTournamentDetailQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetTournamentDetail $query): GetTournamentDetailResult
    {
        $tournament = $this->entityManager->createQueryBuilder()
            ->select('t', 'o', 's')
            ->from(Tournament::class, 't')
            ->innerJoin('t.owner', 'o')
            ->innerJoin('t.sport', 's')
            ->where('t.id = :id')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('id', $query->tournamentId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$tournament instanceof Tournament) {
            throw TournamentNotFound::withId($query->tournamentId);
        }

        return new GetTournamentDetailResult(
            id: $tournament->id,
            name: $tournament->name,
            description: $tournament->description,
            visibility: $tournament->visibility,
            sportCode: $tournament->sport->code,
            sportName: $tournament->sport->name,
            ownerId: $tournament->owner->id,
            ownerNickname: $tournament->owner->displayName,
            startAt: $tournament->startAt,
            endAt: $tournament->endAt,
            createdAt: $tournament->createdAt,
            updatedAt: $tournament->updatedAt,
            finishedAt: $tournament->finishedAt,
        );
    }
}
