<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Guess;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
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

    private function makeTournament(User $owner): Tournament
    {
        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: TournamentVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $tournament->popEvents();

        return $tournament;
    }

    private function makeGroup(User $owner, Tournament $tournament): Group
    {
        $group = new Group(
            id: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            tournament: $tournament,
            owner: $owner,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $group->popEvents();

        return $group;
    }

    private function makeMatch(Tournament $tournament): SportMatch
    {
        $m = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            tournament: $tournament,
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
        $tournament = $this->makeTournament($user);
        $group = $this->makeGroup($user, $tournament);
        $match = $this->makeMatch($tournament);

        return new Guess(
            id: Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID),
            user: $user,
            sportMatch: $match,
            group: $group,
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
