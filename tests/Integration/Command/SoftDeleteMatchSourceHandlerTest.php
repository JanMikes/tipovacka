<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SoftDeleteMatchSource\SoftDeleteMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class SoftDeleteMatchSourceHandlerTest extends IntegrationTestCase
{
    public function testSoftDeletesMatchSource(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);

        $this->commandBus()->dispatch(new SoftDeleteMatchSourceCommand(matchSourceId: $matchSourceId));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->find(MatchSource::class, $matchSourceId);
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertTrue($matchSource->isDeleted());
        self::assertNotNull($matchSource->deletedAt);
    }

    public function testIsIdempotent(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);

        $this->commandBus()->dispatch(new SoftDeleteMatchSourceCommand(matchSourceId: $matchSourceId));
        // Second dispatch should not throw
        $this->commandBus()->dispatch(new SoftDeleteMatchSourceCommand(matchSourceId: $matchSourceId));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->find(MatchSource::class, $matchSourceId);
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertTrue($matchSource->isDeleted());
    }
}
