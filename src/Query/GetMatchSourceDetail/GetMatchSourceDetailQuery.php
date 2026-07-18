<?php

declare(strict_types=1);

namespace App\Query\GetMatchSourceDetail;

use App\Entity\MatchSource;
use App\Exception\MatchSourceNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMatchSourceDetailQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetMatchSourceDetail $query): GetMatchSourceDetailResult
    {
        $matchSource = $this->entityManager->createQueryBuilder()
            ->select('t', 'o', 's')
            ->from(MatchSource::class, 't')
            ->innerJoin('t.owner', 'o')
            ->innerJoin('t.sport', 's')
            ->where('t.id = :id')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('id', $query->matchSourceId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$matchSource instanceof MatchSource) {
            throw MatchSourceNotFound::withId($query->matchSourceId);
        }

        return new GetMatchSourceDetailResult(
            id: $matchSource->id,
            name: $matchSource->name,
            description: $matchSource->description,
            kind: $matchSource->kind,
            sportCode: $matchSource->sport->code,
            sportName: $matchSource->sport->name,
            ownerId: $matchSource->owner->id,
            ownerNickname: $matchSource->owner->displayName,
            startAt: $matchSource->startAt,
            endAt: $matchSource->endAt,
            createdAt: $matchSource->createdAt,
            updatedAt: $matchSource->updatedAt,
            completedAt: $matchSource->completedAt,
        );
    }
}
