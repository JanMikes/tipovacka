<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\MarkMatchSourceFinished\MarkMatchSourceFinishedCommand;
use App\Command\SoftDeleteMatchSource\SoftDeleteMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Query\ListActivePublicMatchSources\ListActivePublicMatchSources;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListActivePublicMatchSourcesQueryTest extends IntegrationTestCase
{
    public function testReturnsOnlyActivePublicMatchSources(): void
    {
        $result = $this->queryBus()->handle(new ListActivePublicMatchSources());

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::PUBLIC_SOURCE_NAME, $result[0]->name);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $result[0]->ownerNickname);
    }

    public function testExcludesFinishedMatchSources(): void
    {
        $publicId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);
        $this->commandBus()->dispatch(new MarkMatchSourceFinishedCommand(matchSourceId: $publicId));

        $result = $this->queryBus()->handle(new ListActivePublicMatchSources());

        self::assertCount(0, $result);
    }

    public function testExcludesDeletedMatchSources(): void
    {
        $publicId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);
        $this->commandBus()->dispatch(new SoftDeleteMatchSourceCommand(matchSourceId: $publicId));

        $result = $this->queryBus()->handle(new ListActivePublicMatchSources());

        self::assertCount(0, $result);
    }

    public function testExcludesPrivateMatchSources(): void
    {
        $result = $this->queryBus()->handle(new ListActivePublicMatchSources());

        foreach ($result as $item) {
            self::assertNotSame(AppFixtures::PRIVATE_SOURCE_NAME, $item->name);
        }
    }
}
