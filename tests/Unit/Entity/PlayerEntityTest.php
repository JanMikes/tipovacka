<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Player;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PlayerEntityTest extends TestCase
{
    public function testConstructsRosterEntry(): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
            password: 'hash',
            nickname: AppFixtures::VERIFIED_USER_NICKNAME,
            createdAt: $now,
        );
        $owner->popEvents();

        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $now,
        );
        $matchSource->popEvents();

        $id = Uuid::v7();
        $player = new Player(
            id: $id,
            matchSource: $matchSource,
            teamName: 'Tygři',
            name: 'Jan Novák',
            createdAt: $now,
        );

        self::assertTrue($id->equals($player->id));
        self::assertSame($matchSource, $player->matchSource);
        self::assertSame('Tygři', $player->teamName);
        self::assertSame('Jan Novák', $player->name);
        self::assertSame($now, $player->createdAt);
    }
}
