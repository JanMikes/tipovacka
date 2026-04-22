<?php

declare(strict_types=1);

namespace App\Query\ListMyAccessiblePrivateTournaments;

use App\Entity\Membership;
use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use App\Query\ListActivePublicTournaments\TournamentListItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyAccessiblePrivateTournamentsQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<TournamentListItem>
     */
    public function __invoke(ListMyAccessiblePrivateTournaments $query): array
    {
        $membershipSubquery = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(mg.tournament)')
            ->from(Membership::class, 'um')
            ->innerJoin('um.group', 'mg')
            ->where('um.user = :userId')
            ->andWhere('um.leftAt IS NULL')
            ->andWhere('mg.deletedAt IS NULL')
            ->getDQL();

        /** @var Tournament[] $tournaments */
        $tournaments = $this->entityManager->createQueryBuilder()
            ->select('t', 'o')
            ->from(Tournament::class, 't')
            ->innerJoin('t.owner', 'o')
            ->where('t.visibility = :visibility')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere(sprintf('(t.owner = :userId OR t.id IN (%s))', $membershipSubquery))
            ->setParameter('visibility', TournamentVisibility::Private)
            ->setParameter('userId', $query->userId)
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
