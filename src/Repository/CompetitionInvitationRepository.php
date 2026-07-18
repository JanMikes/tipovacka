<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompetitionInvitation;
use App\Exception\CompetitionInvitationNotFound;
use App\Exception\InvalidInvitationToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CompetitionInvitationRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CompetitionInvitation $invitation): void
    {
        $this->entityManager->persist($invitation);
    }

    public function find(Uuid $id): ?CompetitionInvitation
    {
        return $this->entityManager->createQueryBuilder()
            ->select('i', 'g', 'inviter')
            ->from(CompetitionInvitation::class, 'i')
            ->innerJoin('i.competition', 'g')
            ->innerJoin('i.inviter', 'inviter')
            ->where('i.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): CompetitionInvitation
    {
        return $this->find($id) ?? throw CompetitionInvitationNotFound::withId($id);
    }

    public function getByToken(string $token): CompetitionInvitation
    {
        $invitation = $this->entityManager->createQueryBuilder()
            ->select('i', 'g', 't', 'inviter')
            ->from(CompetitionInvitation::class, 'i')
            ->innerJoin('i.competition', 'g')
            ->innerJoin('g.matchSource', 't')
            ->innerJoin('i.inviter', 'inviter')
            ->where('i.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$invitation instanceof CompetitionInvitation) {
            throw InvalidInvitationToken::forToken($token);
        }

        return $invitation;
    }

    /**
     * @return list<CompetitionInvitation>
     */
    public function findPendingByCompetition(Uuid $competitionId, \DateTimeImmutable $now): array
    {
        /** @var list<CompetitionInvitation> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('i', 'inviter')
            ->from(CompetitionInvitation::class, 'i')
            ->innerJoin('i.inviter', 'inviter')
            ->where('i.competition = :competitionId')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.revokedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('now', $now)
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
