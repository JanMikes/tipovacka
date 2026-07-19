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
use App\Value\PeriodScores;
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

    // ── S06: period + overtime tip invariants ─────────────────────────────

    public function testConstructorAcceptsPeriodAndOvertimeTips(): void
    {
        $user = $this->makeUser();
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource);

        $guess = new Guess(
            id: Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID),
            user: $user,
            sportMatch: $match,
            competition: $competition,
            homeScore: 1,
            awayScore: 1,
            submittedAt: $this->now,
            periodScores: PeriodScores::fromArray([[1, 0], [0, 1]]),
            overtimeHomeScore: 2,
            overtimeAwayScore: 1,
        );

        self::assertSame([[1, 0], [0, 1]], $guess->periodScores?->toArray());
        self::assertSame(2, $guess->overtimeHomeScore);
        self::assertSame(1, $guess->overtimeAwayScore);
        self::assertTrue($guess->hasOvertimeTip);
    }

    public function testPeriodTipMustMatchSportPeriodCount(): void
    {
        // Football = 2 periods; a 3-period tip is invalid.
        $this->expectException(InvalidGuessScore::class);
        $this->expectExceptionMessageMatches('/2 poločasy/');

        $this->makeGuessWithDetails(2, 1, PeriodScores::fromArray([[1, 0], [1, 1], [0, 0]]));
    }

    public function testPeriodTipSumMustMatchMainTip(): void
    {
        $this->expectException(InvalidGuessScore::class);
        $this->expectExceptionMessage('Součet skóre za jednotlivé části musí odpovídat tipu na základní hrací dobu.');

        // Periods sum to 2:1, main tip says 3:0.
        $this->makeGuessWithDetails(3, 0, PeriodScores::fromArray([[1, 0], [1, 1]]));
    }

    public function testUpdateScoresRejectsPeriodSumMismatch(): void
    {
        $guess = $this->makeGuess();
        $guess->popEvents();

        $this->expectException(InvalidGuessScore::class);
        $this->expectExceptionMessage('Součet skóre za jednotlivé části musí odpovídat tipu na základní hrací dobu.');

        // Periods sum to 1:1, new main tip says 2:1.
        $guess->updateScores(2, 1, $this->later, PeriodScores::fromArray([[1, 0], [0, 1]]));
    }

    public function testOvertimeTipAllowedOnlyOnDrawTip(): void
    {
        $this->expectException(InvalidGuessScore::class);
        $this->expectExceptionMessage('remíze');

        $this->makeGuessWithDetails(2, 1, null, 3, 2);
    }

    public function testOvertimeTipMustNotBeDraw(): void
    {
        $this->expectException(InvalidGuessScore::class);
        $this->expectExceptionMessage('remíza');

        $this->makeGuessWithDetails(1, 1, null, 2, 2);
    }

    public function testOvertimeTipMustNotBeBelowRegularTip(): void
    {
        $this->expectException(InvalidGuessScore::class);
        $this->expectExceptionMessage('nižší');

        $this->makeGuessWithDetails(2, 2, null, 3, 1);
    }

    public function testOvertimeTipRequiresBothValues(): void
    {
        $this->expectException(InvalidGuessScore::class);
        $this->expectExceptionMessage('obě hodnoty');

        $this->makeGuessWithDetails(1, 1, null, 2, null);
    }

    public function testUpdateScoresFullyReplacesAllTipParts(): void
    {
        $guess = $this->makeGuessWithDetails(1, 1, PeriodScores::fromArray([[1, 0], [0, 1]]), 2, 1);
        $guess->popEvents();

        // Omitted parts are CLEARED, not kept (full-replace semantics).
        $guess->updateScores(2, 1, $this->later);

        self::assertSame(2, $guess->homeScore);
        self::assertSame(1, $guess->awayScore);
        self::assertNull($guess->periodScores);
        self::assertNull($guess->overtimeHomeScore);
        self::assertNull($guess->overtimeAwayScore);
        self::assertFalse($guess->hasOvertimeTip);
    }

    public function testUpdateScoresValidatesOvertimeAgainstNewMainTip(): void
    {
        $guess = $this->makeGuess();
        $guess->popEvents();

        $this->expectException(InvalidGuessScore::class);
        $guess->updateScores(2, 1, $this->later, null, 3, 2);
    }

    private function makeGuessWithDetails(
        int $home,
        int $away,
        ?PeriodScores $periods = null,
        ?int $overtimeHome = null,
        ?int $overtimeAway = null,
    ): Guess {
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
            periodScores: $periods,
            overtimeHomeScore: $overtimeHome,
            overtimeAwayScore: $overtimeAway,
        );
    }
}
