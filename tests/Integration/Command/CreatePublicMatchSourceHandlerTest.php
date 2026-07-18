<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreatePublicMatchSource\CreatePublicMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Enum\MatchSourceVisibility;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CreatePublicMatchSourceHandlerTest extends IntegrationTestCase
{
    public function testCreatesPublicMatchSource(): void
    {
        $this->commandBus()->dispatch(new CreatePublicMatchSourceCommand(
            adminId: Uuid::fromString(AppFixtures::ADMIN_ID),
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
        self::assertSame(MatchSourceVisibility::Public, $matchSource->visibility);
        self::assertTrue($matchSource->isPublic);
        self::assertFalse($matchSource->isFinished);
        self::assertFalse($matchSource->isDeleted());
        self::assertSame(AppFixtures::ADMIN_ID, $matchSource->owner->id->toRfc4122());
        self::assertSame('football', $matchSource->sport->code);
    }
}
