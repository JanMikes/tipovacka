<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GroupInvitation;
use App\Exception\GroupInvitationNotFound;
use App\Exception\InvalidInvitationToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class GroupInvitationRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(GroupInvitation $invitation): void
    {
        $this->entityManager->persist($invitation);
    }

    public function find(Uuid $id): ?GroupInvitation
    {
        return $this->entityManager->createQueryBuilder()
            ->select('i', 'g', 'inviter')
            ->from(GroupInvitation::class, 'i')
            ->innerJoin('i.group', 'g')
            ->innerJoin('i.inviter', 'inviter')
            ->where('i.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): GroupInvitation
    {
        return $this->find($id) ?? throw GroupInvitationNotFound::withId($id);
    }

    public function getByToken(string $token): GroupInvitation
    {
        $invitation = $this->entityManager->createQueryBuilder()
            ->select('i', 'g', 't', 'inviter')
            ->from(GroupInvitation::class, 'i')
            ->innerJoin('i.group', 'g')
            ->innerJoin('g.tournament', 't')
            ->innerJoin('i.inviter', 'inviter')
            ->where('i.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$invitation instanceof GroupInvitation) {
            throw InvalidInvitationToken::forToken($token);
        }

        return $invitation;
    }

    /**
     * @return list<GroupInvitation>
     */
    public function findPendingByGroup(Uuid $groupId, \DateTimeImmutable $now): array
    {
        /** @var list<GroupInvitation> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('i', 'inviter')
            ->from(GroupInvitation::class, 'i')
            ->innerJoin('i.inviter', 'inviter')
            ->where('i.group = :groupId')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.revokedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('groupId', $groupId)
            ->setParameter('now', $now)
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
