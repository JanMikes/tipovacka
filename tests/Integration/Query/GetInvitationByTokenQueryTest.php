<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Exception\InvalidInvitationToken;
use App\Query\GetInvitationByToken\GetInvitationByToken;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class GetInvitationByTokenQueryTest extends IntegrationTestCase
{
    public function testReturnsInvitationMetadata(): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $result = $this->queryBus()->handle(new GetInvitationByToken(
            token: AppFixtures::PENDING_INVITATION_TOKEN,
            now: $now,
        ));

        self::assertSame(AppFixtures::PUBLIC_GROUP_NAME, $result->groupName);
        self::assertSame(AppFixtures::PUBLIC_TOURNAMENT_NAME, $result->tournamentName);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $result->inviterNickname);
        self::assertFalse($result->isAccepted);
        self::assertFalse($result->isRevoked);
        self::assertFalse($result->isExpired);
    }

    public function testExpiredFlagReflectsNow(): void
    {
        $futureNow = new \DateTimeImmutable('2030-01-01 00:00:00 UTC');

        $result = $this->queryBus()->handle(new GetInvitationByToken(
            token: AppFixtures::PENDING_INVITATION_TOKEN,
            now: $futureNow,
        ));

        self::assertTrue($result->isExpired);
    }

    public function testInvalidTokenThrows(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->queryBus()->handle(new GetInvitationByToken(
                token: str_repeat('0', 64),
                now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvalidInvitationToken::class, $e->getPrevious());

            throw $e;
        }
    }
}
