<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\Command\ReopenMatchSource\ReopenMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ReopenMatchSourceHandlerTest extends IntegrationTestCase
{
    public function testReopensCompletedMatchSource(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);

        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(matchSourceId: $matchSourceId));
        $this->commandBus()->dispatch(new ReopenMatchSourceCommand(matchSourceId: $matchSourceId));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->find(MatchSource::class, $matchSourceId);
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertFalse($matchSource->isCompleted);
        self::assertNull($matchSource->completedAt);
        self::assertTrue($matchSource->isActive);
    }

    public function testReopenOnActiveSourceIsNoOp(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);

        $this->commandBus()->dispatch(new ReopenMatchSourceCommand(matchSourceId: $matchSourceId));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->find(MatchSource::class, $matchSourceId);
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertFalse($matchSource->isCompleted);
    }
}
