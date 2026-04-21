<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Event\UserEmailAssigned;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UserAnonymousTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    public function testConstructsAsAnonymousWhenEmailIsNull(): void
    {
        $user = $this->makeAnonymous();

        self::assertTrue($user->isAnonymous);
        self::assertFalse($user->hasPassword);
        self::assertNull($user->email);
        self::assertNull($user->nickname);
        self::assertFalse($user->isVerified);
    }

    public function testGetUserIdentifierUsesSyntheticValueWhenEmailIsNull(): void
    {
        $user = $this->makeAnonymous();

        self::assertStringStartsWith('anon:', $user->getUserIdentifier());
        self::assertStringContainsString($user->id->toRfc4122(), $user->getUserIdentifier());
    }

    public function testDisplayNameFallsBackToFullNameWhenNicknameIsNull(): void
    {
        $user = $this->makeAnonymous();
        $user->updateProfile(
            firstName: 'František',
            lastName: 'Novák',
            phone: null,
            now: $this->now,
        );

        self::assertSame('František Novák', $user->displayName);
    }

    public function testAssignEmailStampsAndRecordsEvent(): void
    {
        $user = $this->makeAnonymous();
        $user->popEvents();

        $later = $this->now->modify('+1 hour');
        $user->assignEmail('franta@example.com', $later);

        self::assertFalse($user->isAnonymous);
        self::assertSame('franta@example.com', $user->email);
        self::assertSame($later, $user->updatedAt);

        $events = $user->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserEmailAssigned::class, $events[0]);
        self::assertSame('franta@example.com', $events[0]->email);
    }

    public function testAssignEmailRefusesWhenAlreadyHasOne(): void
    {
        $user = new User(
            id: Uuid::v7(),
            email: 'already@set.com',
            password: null,
            nickname: 'someone',
            createdAt: $this->now,
        );

        $this->expectException(\DomainException::class);

        $user->assignEmail('new@example.com', $this->now);
    }

    private function makeAnonymous(): User
    {
        return new User(
            id: Uuid::v7(),
            email: null,
            password: null,
            nickname: null,
            createdAt: $this->now,
        );
    }
}
