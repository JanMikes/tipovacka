<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class MarkMatchSourceCompletedHandlerTest extends IntegrationTestCase
{
    public function testMarksMatchSourceAsFinished(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);

        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(matchSourceId: $matchSourceId));

        $em = $this->entityManager();
        $em->clear();

        $matchSource = $em->find(MatchSource::class, $matchSourceId);
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertTrue($matchSource->isCompleted);
        self::assertNotNull($matchSource->completedAt);
    }

    public function testThrowsWhenAlreadyFinished(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);

        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(matchSourceId: $matchSourceId));

        $this->expectException(HandlerFailedException::class);
        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(matchSourceId: $matchSourceId));
    }
}
