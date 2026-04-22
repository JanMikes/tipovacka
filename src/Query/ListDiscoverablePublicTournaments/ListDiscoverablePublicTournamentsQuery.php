<?php

declare(strict_types=1);

namespace App\Query\ListDiscoverablePublicTournaments;

use App\Entity\Group;
use App\Entity\Membership;
use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListDiscoverablePublicTournamentsQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<DiscoverableTournamentItem>
     */
    public function __invoke(ListDiscoverablePublicTournaments $query): array
    {
        // Sub-query: IDs of tournaments where user already has an active membership
        // (via any active group in that tournament).
        $membershipSubquery = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(mg.tournament)')
            ->from(Membership::class, 'um')
            ->innerJoin('um.group', 'mg')
            ->where('um.user = :userId')
            ->andWhere('um.leftAt IS NULL')
            ->andWhere('mg.deletedAt IS NULL')
            ->getDQL();

        /** @var list<Tournament> $tournaments */
        $tournaments = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Tournament::class, 't')
            ->where('t.visibility = :visibility')
            ->andWhere('t.finishedAt IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.owner != :userId')
            ->andWhere(sprintf('t.id NOT IN (%s)', $membershipSubquery))
            ->setParameter('visibility', TournamentVisibility::Public)
            ->setParameter('userId', $query->userId)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($tournaments as $tournament) {
            $groupCount = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(g.id)')
                ->from(Group::class, 'g')
                ->where('g.tournament = :tournamentId')
                ->andWhere('g.deletedAt IS NULL')
                ->setParameter('tournamentId', $tournament->id)
                ->getQuery()
                ->getSingleScalarResult();

            $memberCount = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(m.id)')
                ->from(Membership::class, 'm')
                ->innerJoin('m.group', 'g')
                ->where('g.tournament = :tournamentId')
                ->andWhere('g.deletedAt IS NULL')
                ->andWhere('m.leftAt IS NULL')
                ->setParameter('tournamentId', $tournament->id)
                ->getQuery()
                ->getSingleScalarResult();

            $items[] = new DiscoverableTournamentItem(
                tournamentId: $tournament->id,
                name: $tournament->name,
                startAt: $tournament->startAt,
                endAt: $tournament->endAt,
                groupCount: $groupCount,
                memberCount: $memberCount,
            );
        }

        return $items;
    }
}
