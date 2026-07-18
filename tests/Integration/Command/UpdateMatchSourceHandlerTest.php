<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateMatchSource\UpdateMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateMatchSourceHandlerTest extends IntegrationTestCase
{
    public function testUpdatesMatchSourceDetails(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);
        $startAt = new \DateTimeImmutable('2025-08-01 18:00:00 UTC');

        $this->commandBus()->dispatch(new UpdateMatchSourceCommand(
            matchSourceId: $matchSourceId,
            name: 'Upravený název',
            description: 'Nový popis',
            startAt: $startAt,
            endAt: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->find(MatchSource::class, $matchSourceId);
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertSame('Upravený název', $matchSource->name);
        self::assertSame('Nový popis', $matchSource->description);
        self::assertEquals($startAt, $matchSource->startAt);
    }
}
