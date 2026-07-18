<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateCuratedMatchSource\CreateCuratedMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Enum\MatchSourceKind;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateCuratedMatchSourceHandlerTest extends IntegrationTestCase
{
    public function testCreatesPublicMatchSource(): void
    {
        $this->commandBus()->dispatch(new CreateCuratedMatchSourceCommand(
            adminId: Uuid::fromString(AppFixtures::ADMIN_ID),
            sportId: Uuid::fromString(Sport::FOOTBALL_ID),
            name: 'Nový veřejný turnaj',
            description: 'Popis',
            startAt: null,
            endAt: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Nový veřejný turnaj')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertSame(MatchSourceKind::Curated, $matchSource->kind);
        self::assertTrue($matchSource->isCurated);
        self::assertFalse($matchSource->isCompleted);
        self::assertFalse($matchSource->isDeleted());
        self::assertSame(AppFixtures::ADMIN_ID, $matchSource->owner->id->toRfc4122());
        self::assertSame('football', $matchSource->sport->code);
    }
}
