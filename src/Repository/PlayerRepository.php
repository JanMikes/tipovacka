<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use App\Entity\Team;
use App\Service\Identity\ProvideIdentity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class PlayerRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Player $player): void
    {
        $this->entityManager->persist($player);
    }

    /**
     * Players are created lazily when an organizer first types their name into the
     * score-entry form. The lookup is case-insensitive („Novák" and „novák" are the
     * same player); the stored row keeps its first-seen casing. If two concurrent
     * saves race on the same new name, the unique constraint on (team_id, name)
     * fails one of the transactions; the organizer simply re-submits and the player
     * is found on the next attempt (same pattern as CreditWalletProvider).
     */
    public function findOrCreate(
        Team $team,
        string $name,
        ProvideIdentity $identity,
        \DateTimeImmutable $now,
    ): Player {
        $player = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Player::class, 'p')
            ->where('p.team = :team')
            ->andWhere('LOWER(p.name) = LOWER(:name)')
            ->setParameter('team', $team->id)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();

        if ($player instanceof Player) {
            return $player;
        }

        $player = new Player(
            id: $identity->next(),
            team: $team,
            name: $name,
            createdAt: $now,
        );

        $this->entityManager->persist($player);

        return $player;
    }

    /**
     * Roster of one team, for the scorer-name autocomplete.
     *
     * @return list<Player>
     */
    public function listByTeam(Uuid $teamId): array
    {
        /** @var list<Player> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Player::class, 'p')
            ->where('p.team = :team')
            ->setParameter('team', $teamId)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Case-insensitive name search within one team's roster.
     *
     * @return list<Player>
     */
    public function searchByTeam(Uuid $teamId, string $term = ''): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Player::class, 'p')
            ->where('p.team = :team')
            ->setParameter('team', $teamId)
            ->orderBy('p.name', 'ASC');

        if ('' !== $term) {
            $qb->andWhere('LOWER(p.name) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($term).'%');
        }

        /** @var list<Player> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
