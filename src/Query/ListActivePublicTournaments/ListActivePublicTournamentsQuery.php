<?php

declare(strict_types=1);

namespace App\Query\ListActivePublicTournaments;

use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListActivePublicTournamentsQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<TournamentListItem>
     */
    public function __invoke(ListActivePublicTournaments $query): array
    {
        /** @var Tournament[] $tournaments */
        $tournaments = $this->entityManager->createQueryBuilder()
            ->select('t', 'o')
            ->from(Tournament::class, 't')
            ->innerJoin('t.owner', 'o')
            ->where('t.visibility = :visibility')
            ->andWhere('t.finishedAt IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('visibility', TournamentVisibility::Public)
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
