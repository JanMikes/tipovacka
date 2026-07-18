<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Event\GuessSubmitted;
use App\Event\GuessUpdated;
use App\Event\GuessVoided;
use App\Exception\InvalidGuessScore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GuessEntityTest extends TestCase
{
    private \DateTimeImmutable $now;
    private \DateTimeImmutable $later;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $this->later = new \DateTimeImmutable('2025-06-15 13:00:00 UTC');
    }

    private function makeUser(string $id = AppFixtures::VERIFIED_USER_ID): User
    {
        $user = new User(
            id: Uuid::fromString($id),
            email: 'u@test.com',
            password: 'hash',
            nickname: 'u',
            createdAt: $this->now,
        );
        $user->popEvents();

        return $user;
    }

    private function makeMatchSource(User $owner): MatchSource
    {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $matchSource->popEvents();

        return $matchSource;
    }

    private function makeCompetition(User $owner, MatchSource $matchSource): Competition
    {
        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $owner,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $competition->popEvents();

        return $competition;
    }

    private function makeMatch(MatchSource $matchSource): SportMatch
    {
        $m = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            matchSource: $matchSource,
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00'),
            venue: null,
            createdAt: $this->now,
        );
        $m->popEvents();

        return $m;
    }

    private function makeGuess(int $home = 2, int $away = 1): Guess
    {
        $user = $this->makeUser();
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource);

        return new Guess(
            id: Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID),
            user: $user,
            sportMatch: $match,
            competition: $competition,
            homeScore: $home,
            awayScore: $away,
            submittedAt: $this->now,
        );
    }

    public function testConstructorRecordsSubmittedEvent(): void
    {
        $guess = $this->makeGuess();

        $events = $guess->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GuessSubmitted::class, $events[0]);
        self::assertSame(2, $guess->homeScore);
        self::assertSame(1, $guess->awayScore);
        self::assertSame($this->now, $guess->submittedAt);
        self::assertSame($this->now, $guess->updatedAt);
    }

    public function testConstructorRejectsNegativeScores(): void
    {
        $this->expectException(InvalidGuessScore::class);
        $this->makeGuess(-1, 0);
    }

    public function testConstructorRejectsNegativeAwayScore(): void
    {
        $this->expectException(InvalidGuessScore::class);
        $this->makeGuess(0, -2);
    }

    public function testUpdateScoresAppliesAndRecordsEvent(): void
    {
        $guess = $this->makeGuess();
        $guess->popEvents();

        $guess->updateScores(3, 2, $this->later);

        self::assertSame(3, $guess->homeScore);
        self::assertSame(2, $guess->awayScore);
        self::assertSame($this->later, $guess->updatedAt);

        $events = $guess->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GuessUpdated::class, $events[0]);
    }

    public function testUpdateScoresRejectsNegative(): void
    {
        $guess = $this->makeGuess();
        $guess->popEvents();

        $this->expectException(InvalidGuessScore::class);
        $guess->updateScores(-1, 0, $this->later);
    }

    public function testVoidGuessMarksDeletedAndRecordsEvent(): void
    {
        $guess = $this->makeGuess();
        $guess->popEvents();

        $guess->voidGuess($this->later);

        self::assertTrue($guess->isDeleted());
        self::assertSame($this->later, $guess->updatedAt);

        $events = $guess->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GuessVoided::class, $events[0]);
    }

    public function testVoidGuessIsIdempotent(): void
    {
        $guess = $this->makeGuess();
        $guess->voidGuess($this->now);
        $guess->popEvents();

        $guess->voidGuess($this->later);

        self::assertCount(0, $guess->popEvents());
    }
}
