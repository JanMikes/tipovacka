<?php

declare(strict_types=1);

namespace App\Query\ListMyPrivateTournaments;

use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use App\Query\ListActivePublicTournaments\TournamentListItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyPrivateTournamentsQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<TournamentListItem>
     */
    public function __invoke(ListMyPrivateTournaments $query): array
    {
        /** @var Tournament[] $tournaments */
        $tournaments = $this->entityManager->createQueryBuilder()
            ->select('t', 'o')
            ->from(Tournament::class, 't')
            ->innerJoin('t.owner', 'o')
            ->where('t.owner = :ownerId')
            ->andWhere('t.visibility = :visibility')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('ownerId', $query->ownerId)
            ->setParameter('visibility', TournamentVisibility::Private)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        return array_values(array_map(
            static fn (Tournament $t): TournamentListItem => new TournamentListItem(
                id: $t->id,
                name: $t->name,
                visibility: $t->visibility,
                ownerNickname: $t->owner->displayName,
                createdAt: $t->createdAt,
                startAt: $t->startAt,
                endAt: $t->endAt,
                finishedAt: $t->finishedAt,
            ),
            $tournaments,
        ));
    }
}
