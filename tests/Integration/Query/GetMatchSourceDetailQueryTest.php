<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Enum\MatchSourceVisibility;
use App\Query\GetMatchSourceDetail\GetMatchSourceDetail;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class GetMatchSourceDetailQueryTest extends IntegrationTestCase
{
    public function testReturnsDetailForExistingMatchSource(): void
    {
        $result = $this->queryBus()->handle(new GetMatchSourceDetail(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
        ));

        self::assertSame(AppFixtures::PRIVATE_SOURCE_NAME, $result->name);
        self::assertSame(MatchSourceVisibility::Private, $result->visibility);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $result->ownerNickname);
        self::assertSame('football', $result->sportCode);
        self::assertSame('Fotbal', $result->sportName);
    }

    public function testThrowsWhenMatchSourceNotFound(): void
    {
        $this->expectException(HandlerFailedException::class);

        $this->queryBus()->handle(new GetMatchSourceDetail(
            matchSourceId: Uuid::fromString('019bbbbb-0000-7000-8000-000000000099'),
        ));
    }
}
