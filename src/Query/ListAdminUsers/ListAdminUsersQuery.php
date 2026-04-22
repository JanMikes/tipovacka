<?php

declare(strict_types=1);

namespace App\Query\ListAdminUsers;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListAdminUsersQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<AdminUserItem>
     */
    public function __invoke(ListAdminUsers $query): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.createdAt', 'DESC')
            ->addOrderBy('u.id', 'DESC');

        if (null !== $query->search && '' !== $query->search) {
            $qb->andWhere('LOWER(u.email) LIKE :search OR LOWER(u.nickname) LIKE :search')
                ->setParameter('search', '%'.strtolower($query->search).'%');
        }

        if (null !== $query->verified) {
            $qb->andWhere('u.isVerified = :verified')
                ->setParameter('verified', $query->verified);
        }

        if (null !== $query->active) {
            $qb->andWhere('u.isActive = :active')
                ->setParameter('active', $query->active);
        }

        /** @var User[] $users */
        $users = $qb->getQuery()->getResult();

        return array_values(array_map(
            static fn (User $u): AdminUserItem => new AdminUserItem(
                id: $u->id,
                email: $u->email,
                nickname: $u->nickname,
                fullName: '' !== $u->fullName ? $u->fullName : null,
                roles: array_values($u->getRoles()),
                isVerified: $u->isVerified,
                isActive: $u->isActive,
                isDeleted: null !== $u->deletedAt,
                createdAt: $u->createdAt,
                updatedAt: $u->updatedAt,
            ),
            $users,
        ));
    }
}
