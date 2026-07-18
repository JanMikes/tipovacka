<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Event\MatchSourceCreated;
use App\Event\MatchSourceDeleted;
use App\Event\MatchSourceFinished;
use App\Event\MatchSourceUpdated;
use App\Exception\MatchSourceAlreadyFinished;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MatchSourceEntityTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makeOwner(): User
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
            password: 'hash',
            nickname: AppFixtures::VERIFIED_USER_NICKNAME,
            createdAt: $this->now,
        );
        $owner->popEvents();

        return $owner;
    }

    private function makeSport(): Sport
    {
        return new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
        );
    }

    private function makeMatchSource(MatchSourceKind $kind = MatchSourceKind::Private): MatchSource
    {
        return new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: $this->makeSport(),
            owner: $this->makeOwner(),
            kind: $kind,
            name: 'Test Turnaj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
    }

    public function testConstructorRecordsCreatedEvent(): void
    {
        $matchSource = $this->makeMatchSource();

        $events = $matchSource->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MatchSourceCreated::class, $events[0]);
        self::assertSame($matchSource->id, $events[0]->matchSourceId);
        self::assertSame(MatchSourceKind::Private, $events[0]->kind);
    }

    public function testIsActiveWhenFreshlyCreated(): void
    {
        $matchSource = $this->makeMatchSource();

        self::assertTrue($matchSource->isActive);
        self::assertFalse($matchSource->isFinished);
        self::assertFalse($matchSource->isDeleted());
    }

    public function testIsCuratedReflectsKind(): void
    {
        $curated = $this->makeMatchSource(MatchSourceKind::Curated);
        $private = $this->makeMatchSource(MatchSourceKind::Private);

        self::assertTrue($curated->isCurated);
        self::assertFalse($private->isCurated);
        self::assertSame('curated', $curated->kind->value);
        self::assertSame('private', $private->kind->value);
    }

    public function testUpdateDetailsRecordsEventAndUpdatesFields(): void
    {
        $matchSource = $this->makeMatchSource();
        $matchSource->popEvents();

        $later = new \DateTimeImmutable('2025-06-16 12:00:00 UTC');
        $matchSource->updateDetails(
            name: 'Nový název',
            description: 'Popis',
            startAt: $later,
            endAt: null,
            now: $later,
        );

        self::assertSame('Nový název', $matchSource->name);
        self::assertSame('Popis', $matchSource->description);
        self::assertSame($later, $matchSource->startAt);
        self::assertSame($later, $matchSource->updatedAt);

        $events = $matchSource->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MatchSourceUpdated::class, $events[0]);
    }

    public function testMarkFinishedSetsFinishedAtAndRecordsEvent(): void
    {
        $matchSource = $this->makeMatchSource();
        $matchSource->popEvents();

        $finishAt = new \DateTimeImmutable('2025-06-17 10:00:00 UTC');
        $matchSource->markFinished($finishAt);

        self::assertTrue($matchSource->isFinished);
        self::assertFalse($matchSource->isActive);
        self::assertSame($finishAt, $matchSource->finishedAt);
        self::assertSame($finishAt, $matchSource->updatedAt);

        $events = $matchSource->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MatchSourceFinished::class, $events[0]);
    }

    public function testMarkFinishedThrowsWhenAlreadyFinished(): void
    {
        $matchSource = $this->makeMatchSource();
        $matchSource->markFinished($this->now);

        $this->expectException(MatchSourceAlreadyFinished::class);
        $matchSource->markFinished($this->now);
    }

    public function testSoftDeleteMarksDeletedAndRecordsEvent(): void
    {
        $matchSource = $this->makeMatchSource();
        $matchSource->popEvents();

        $matchSource->softDelete($this->now);

        self::assertTrue($matchSource->isDeleted());
        self::assertFalse($matchSource->isActive);
        self::assertSame($this->now, $matchSource->deletedAt);

        $events = $matchSource->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MatchSourceDeleted::class, $events[0]);
    }

    public function testSoftDeleteIsIdempotent(): void
    {
        $matchSource = $this->makeMatchSource();
        $firstDelete = new \DateTimeImmutable('2025-06-16 09:00:00 UTC');
        $matchSource->softDelete($firstDelete);
        $matchSource->popEvents();

        // Second softDelete should be no-op (no event recorded, timestamp unchanged)
        $matchSource->softDelete(new \DateTimeImmutable('2025-06-17 09:00:00 UTC'));

        self::assertSame($firstDelete, $matchSource->deletedAt);
        self::assertCount(0, $matchSource->popEvents());
    }
}
