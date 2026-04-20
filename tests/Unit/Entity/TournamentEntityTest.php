<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Event\TournamentCreated;
use App\Event\TournamentDeleted;
use App\Event\TournamentFinished;
use App\Event\TournamentUpdated;
use App\Exception\TournamentAlreadyFinished;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TournamentEntityTest extends TestCase
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

    private function makeTournament(TournamentVisibility $visibility = TournamentVisibility::Private): Tournament
    {
        return new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: $this->makeSport(),
            owner: $this->makeOwner(),
            visibility: $visibility,
            name: 'Test Turnaj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
    }

    public function testConstructorRecordsCreatedEvent(): void
    {
        $tournament = $this->makeTournament();

        $events = $tournament->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TournamentCreated::class, $events[0]);
        self::assertSame($tournament->id, $events[0]->tournamentId);
        self::assertSame(TournamentVisibility::Private, $events[0]->visibility);
    }

    public function testIsActiveWhenFreshlyCreated(): void
    {
        $tournament = $this->makeTournament();

        self::assertTrue($tournament->isActive);
        self::assertFalse($tournament->isFinished);
        self::assertFalse($tournament->isDeleted());
    }

    public function testIsPublicReflectsVisibility(): void
    {
        $public = $this->makeTournament(TournamentVisibility::Public);
        $private = $this->makeTournament(TournamentVisibility::Private);

        self::assertTrue($public->isPublic);
        self::assertFalse($private->isPublic);
    }

    public function testUpdateDetailsRecordsEventAndUpdatesFields(): void
    {
        $tournament = $this->makeTournament();
        $tournament->popEvents();

        $later = new \DateTimeImmutable('2025-06-16 12:00:00 UTC');
        $tournament->updateDetails(
            name: 'Nový název',
            description: 'Popis',
            startAt: $later,
            endAt: null,
            now: $later,
        );

        self::assertSame('Nový název', $tournament->name);
        self::assertSame('Popis', $tournament->description);
        self::assertSame($later, $tournament->startAt);
        self::assertSame($later, $tournament->updatedAt);

        $events = $tournament->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TournamentUpdated::class, $events[0]);
    }

    public function testMarkFinishedSetsFinishedAtAndRecordsEvent(): void
    {
        $tournament = $this->makeTournament();
        $tournament->popEvents();

        $finishAt = new \DateTimeImmutable('2025-06-17 10:00:00 UTC');
        $tournament->markFinished($finishAt);

        self::assertTrue($tournament->isFinished);
        self::assertFalse($tournament->isActive);
        self::assertSame($finishAt, $tournament->finishedAt);
        self::assertSame($finishAt, $tournament->updatedAt);

        $events = $tournament->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TournamentFinished::class, $events[0]);
    }

    public function testMarkFinishedThrowsWhenAlreadyFinished(): void
    {
        $tournament = $this->makeTournament();
        $tournament->markFinished($this->now);

        $this->expectException(TournamentAlreadyFinished::class);
        $tournament->markFinished($this->now);
    }

    public function testSoftDeleteMarksDeletedAndRecordsEvent(): void
    {
        $tournament = $this->makeTournament();
        $tournament->popEvents();

        $tournament->softDelete($this->now);

        self::assertTrue($tournament->isDeleted());
        self::assertFalse($tournament->isActive);
        self::assertSame($this->now, $tournament->deletedAt);

        $events = $tournament->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TournamentDeleted::class, $events[0]);
    }

    public function testSoftDeleteIsIdempotent(): void
    {
        $tournament = $this->makeTournament();
        $firstDelete = new \DateTimeImmutable('2025-06-16 09:00:00 UTC');
        $tournament->softDelete($firstDelete);
        $tournament->popEvents();

        // Second softDelete should be no-op (no event recorded, timestamp unchanged)
        $tournament->softDelete(new \DateTimeImmutable('2025-06-17 09:00:00 UTC'));

        self::assertSame($firstDelete, $tournament->deletedAt);
        self::assertCount(0, $tournament->popEvents());
    }
}
