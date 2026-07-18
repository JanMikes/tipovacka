<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MatchSource;
use App\Entity\Player;
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
     * saves race on the same new name, the unique constraint on
     * (match_source_id, team_name, name) fails one of the transactions; the
     * organizer simply re-submits and the player is found on the next attempt
     * (same pattern as CreditWalletProvider).
     */
    public function findOrCreate(
        MatchSource $matchSource,
        string $teamName,
        string $name,
        ProvideIdentity $identity,
        \DateTimeImmutable $now,
    ): Player {
        $player = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Player::class, 'p')
            ->where('p.matchSource = :matchSourceId')
            ->andWhere('LOWER(p.teamName) = LOWER(:teamName)')
            ->andWhere('LOWER(p.name) = LOWER(:name)')
            ->setParameter('matchSourceId', $matchSource->id)
            ->setParameter('teamName', $teamName)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();

        if ($player instanceof Player) {
            return $player;
        }

        $player = new Player(
            id: $identity->next(),
            matchSource: $matchSource,
            teamName: $teamName,
            name: $name,
            createdAt: $now,
        );

        $this->entityManager->persist($player);

        return $player;
    }

    /**
     * Roster of one team within a source, for the scorer-name autocomplete.
     *
     * @return list<Player>
     */
    public function listBySourceAndTeam(Uuid $matchSourceId, string $teamName): array
    {
        /** @var list<Player> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Player::class, 'p')
            ->where('p.matchSource = :matchSourceId')
            ->andWhere('p.teamName = :teamName')
            ->setParameter('matchSourceId', $matchSourceId)
            ->setParameter('teamName', $teamName)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Case-insensitive name search across the whole source pool.
     *
     * @return list<Player>
     */
    public function searchBySource(Uuid $matchSourceId, string $term = ''): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Player::class, 'p')
            ->where('p.matchSource = :matchSourceId')
            ->setParameter('matchSourceId', $matchSourceId)
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
