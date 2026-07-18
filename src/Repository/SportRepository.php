<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Sport;
use App\Exception\SportNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class SportRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Sport $sport): void
    {
        $this->entityManager->persist($sport);
    }

    public function find(Uuid $id): ?Sport
    {
        return $this->entityManager->find(Sport::class, $id);
    }

    public function get(Uuid $id): Sport
    {
        return $this->find($id) ?? throw SportNotFound::withId($id);
    }

    /**
     * @return list<Sport>
     */
    public function listAll(): array
    {
        /** @var list<Sport> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Sport::class, 's')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findByCode(string $code): ?Sport
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Sport::class, 's')
            ->where('s.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getByCode(string $code): Sport
    {
        return $this->findByCode($code) ?? throw SportNotFound::withCode($code);
    }
}
