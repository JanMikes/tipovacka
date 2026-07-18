<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreatePrivateMatchSource\CreatePrivateMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Enum\MatchSourceKind;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CreatePrivateMatchSourceHandlerTest extends IntegrationTestCase
{
    public function testCreatesPrivateMatchSource(): void
    {
        $this->commandBus()->dispatch(new CreatePrivateMatchSourceCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Soukromý pohár',
            description: null,
            startAt: null,
            endAt: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Soukromý pohár')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertSame(MatchSourceKind::Private, $matchSource->kind);
        self::assertFalse($matchSource->isCurated);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $matchSource->owner->id->toRfc4122());
    }
}
