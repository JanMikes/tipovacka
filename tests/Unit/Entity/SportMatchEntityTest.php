<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Enum\SportMatchState;
use App\Event\SportMatchCancelled;
use App\Event\SportMatchCreated;
use App\Event\SportMatchDeleted;
use App\Event\SportMatchFinished;
use App\Event\SportMatchLive;
use App\Event\SportMatchLiveScoreChanged;
use App\Event\SportMatchPostponed;
use App\Event\SportMatchScoreUpdated;
use App\Event\SportMatchUpdated;
use App\Exception\InvalidScore;
use App\Exception\SportMatchCannotBeEdited;
use App\Exception\SportMatchInvalidTransition;
use App\Value\PeriodScores;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SportMatchEntityTest extends TestCase
{
    private \DateTimeImmutable $now;
    private \DateTimeImmutable $later;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $this->later = new \DateTimeImmutable('2025-06-16 12:00:00 UTC');
    }

    private function makeMatchSource(): MatchSource
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
            password: 'hash',
            nickname: AppFixtures::VERIFIED_USER_NICKNAME,
            createdAt: $this->now,
        );
        $owner->popEvents();

        $sport = new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
            periodCount: 2,
            periodLabelSingular: 'poločas',
            periodLabelPlural: 'poločasy',
        );

        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: $sport,
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

    private function makeMatch(): SportMatch
    {
        return new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            matchSource: $this->makeMatchSource(),
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
            venue: 'Stadium',
            createdAt: $this->now,
        );
    }

    public function testConstructorRecordsCreatedEvent(): void
    {
        $match = $this->makeMatch();

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchCreated::class, $events[0]);
        self::assertTrue($match->isScheduled);
        self::assertTrue($match->isOpenForGuesses);
        self::assertSame(SportMatchState::Scheduled, $match->state);
    }

    public function testRoundDefaultsToNull(): void
    {
        self::assertNull($this->makeMatch()->round);
    }

    public function testConstructorStoresRound(): void
    {
        $match = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            matchSource: $this->makeMatchSource(),
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
            venue: 'Stadium',
            createdAt: $this->now,
            round: 'Čtvrtfinále',
        );

        self::assertSame('Čtvrtfinále', $match->round);
    }

    public function testUpdateDetailsSetsAndClearsRound(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->updateDetails('A', 'B', null, 'Stadium', $this->later, round: 'Semifinále');
        self::assertSame('Semifinále', $match->round);

        // updateDetails replaces round wholesale (like venue) — omitting it clears it.
        $match->updateDetails('A', 'B', null, 'Stadium', $this->later);
        self::assertNull($match->round);
    }

    public function testIsPlayoffDefaultsToFalse(): void
    {
        $match = $this->makeMatch();

        self::assertFalse($match->isPlayoff);

        $events = $match->popEvents();
        self::assertInstanceOf(SportMatchCreated::class, $events[0]);
        self::assertFalse($events[0]->isPlayoff);
    }

    public function testConstructorStoresIsPlayoffAndCarriesItInEvent(): void
    {
        $match = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            matchSource: $this->makeMatchSource(),
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
            venue: 'Stadium',
            createdAt: $this->now,
            round: 'Playoff',
            isPlayoff: true,
        );

        self::assertTrue($match->isPlayoff);

        $events = $match->popEvents();
        self::assertInstanceOf(SportMatchCreated::class, $events[0]);
        self::assertTrue($events[0]->isPlayoff);
    }

    public function testUpdateDetailsSetsAndClearsIsPlayoff(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->updateDetails('A', 'B', null, 'Stadium', $this->later, isPlayoff: true);
        self::assertTrue($match->isPlayoff);

        // Like round/venue, isPlayoff is replaced wholesale — omitting it resets it.
        $match->updateDetails('A', 'B', null, 'Stadium', $this->later);
        self::assertFalse($match->isPlayoff);
    }

    public function testUpdateDetailsAppliesOnlyNonNullFields(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->updateDetails(
            homeTeam: 'X',
            awayTeam: null,
            kickoffAt: null,
            venue: 'New',
            now: $this->later,
        );

        self::assertSame('X', $match->homeTeam);
        self::assertSame('B', $match->awayTeam);
        self::assertSame('New', $match->venue);
        self::assertSame($this->later, $match->updatedAt);

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchUpdated::class, $events[0]);
    }

    public function testUpdateDetailsCanNullVenue(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->updateDetails(null, null, null, null, $this->later);

        self::assertNull($match->venue);
    }

    public function testUpdateDetailsThrowsWhenCancelled(): void
    {
        $match = $this->makeMatch();
        $match->cancel($this->now);

        $this->expectException(SportMatchCannotBeEdited::class);
        $match->updateDetails('X', 'Y', null, null, $this->later);
    }

    public function testUpdateDetailsThrowsWhenSoftDeleted(): void
    {
        $match = $this->makeMatch();
        $match->softDelete($this->now);

        $this->expectException(SportMatchCannotBeEdited::class);
        $match->updateDetails('X', 'Y', null, null, $this->later);
    }

    public function testUpdateAllowedWhenFinished(): void
    {
        $match = $this->makeMatch();
        $match->setFinalScore(1, 0, null, null, null, $this->now);
        $match->popEvents();

        $match->updateDetails('X', 'Y', null, null, $this->later);

        self::assertSame('X', $match->homeTeam);
        self::assertTrue($match->isFinished);
    }

    public function testUpdateAllowedWhenPostponed(): void
    {
        $match = $this->makeMatch();
        $match->postponeTo(new \DateTimeImmutable('2025-07-01 18:00'), $this->now);
        $match->popEvents();

        $match->updateDetails('X', null, null, null, $this->later);

        self::assertSame('X', $match->homeTeam);
        self::assertTrue($match->isPostponed);
    }

    public function testBeginLiveFromScheduled(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->beginLive($this->later);

        self::assertTrue($match->isLive);
        self::assertFalse($match->isOpenForGuesses);

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchLive::class, $events[0]);
    }

    public function testBeginLiveThrowsFromFinished(): void
    {
        $match = $this->makeMatch();
        $match->setFinalScore(1, 0, null, null, null, $this->now);

        $this->expectException(SportMatchInvalidTransition::class);
        $match->beginLive($this->later);
    }

    public function testBeginLiveThrowsFromPostponed(): void
    {
        $match = $this->makeMatch();
        $match->postponeTo(new \DateTimeImmutable('2025-07-01 18:00'), $this->now);

        $this->expectException(SportMatchInvalidTransition::class);
        $match->beginLive($this->later);
    }

    public function testSetFinalScoreFromScheduled(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->setFinalScore(2, 1, null, null, null, $this->later);

        self::assertTrue($match->isFinished);
        self::assertSame(2, $match->homeScore);
        self::assertSame(1, $match->awayScore);

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchFinished::class, $events[0]);
    }

    public function testSetFinalScoreFromLive(): void
    {
        $match = $this->makeMatch();
        $match->beginLive($this->now);
        $match->popEvents();

        $match->setFinalScore(3, 0, null, null, null, $this->later);

        self::assertTrue($match->isFinished);

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchFinished::class, $events[0]);
    }

    public function testReSettingScoreFiresScoreUpdatedEvent(): void
    {
        $match = $this->makeMatch();
        $match->setFinalScore(1, 0, null, null, null, $this->now);
        $match->popEvents();

        $match->setFinalScore(2, 2, null, null, null, $this->later);

        self::assertSame(2, $match->homeScore);
        self::assertSame(2, $match->awayScore);

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchScoreUpdated::class, $events[0]);
    }

    public function testSetFinalScoreRejectsNegative(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidScore::class);
        $match->setFinalScore(-1, 0, null, null, null, $this->later);
    }

    public function testSetFinalScoreThrowsWhenCancelled(): void
    {
        $match = $this->makeMatch();
        $match->cancel($this->now);

        $this->expectException(SportMatchCannotBeEdited::class);
        $match->setFinalScore(1, 1, null, null, null, $this->later);
    }

    public function testPostponeFromScheduled(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();
        $newKickoff = new \DateTimeImmutable('2025-07-01 18:00');

        $match->postponeTo($newKickoff, $this->later);

        self::assertTrue($match->isPostponed);
        self::assertEquals($newKickoff, $match->kickoffAt);

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchPostponed::class, $events[0]);
    }

    public function testPostponeAgainWhilePostponed(): void
    {
        $match = $this->makeMatch();
        $match->postponeTo(new \DateTimeImmutable('2025-07-01 18:00'), $this->now);
        $match->popEvents();

        $match->postponeTo(new \DateTimeImmutable('2025-07-10 18:00'), $this->later);

        self::assertTrue($match->isPostponed);
    }

    public function testPostponeThrowsFromFinished(): void
    {
        $match = $this->makeMatch();
        $match->setFinalScore(1, 0, null, null, null, $this->now);

        $this->expectException(SportMatchInvalidTransition::class);
        $match->postponeTo(new \DateTimeImmutable('2025-07-01 18:00'), $this->later);
    }

    public function testRescheduleFromPostponed(): void
    {
        $match = $this->makeMatch();
        $match->postponeTo(new \DateTimeImmutable('2025-07-01 18:00'), $this->now);
        $match->popEvents();
        $newKickoff = new \DateTimeImmutable('2025-07-05 18:00');

        $match->reschedule($newKickoff, $this->later);

        self::assertTrue($match->isScheduled);
        self::assertEquals($newKickoff, $match->kickoffAt);
    }

    public function testRescheduleThrowsFromScheduled(): void
    {
        $match = $this->makeMatch();

        $this->expectException(SportMatchInvalidTransition::class);
        $match->reschedule(new \DateTimeImmutable('2025-07-01 18:00'), $this->later);
    }

    public function testCancelFromScheduled(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->cancel($this->later);

        self::assertTrue($match->isCancelled);
        self::assertFalse($match->isOpenForGuesses);

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchCancelled::class, $events[0]);
    }

    public function testCancelFromLive(): void
    {
        $match = $this->makeMatch();
        $match->beginLive($this->now);
        $match->popEvents();

        $match->cancel($this->later);

        self::assertTrue($match->isCancelled);
    }

    public function testCancelFromPostponed(): void
    {
        $match = $this->makeMatch();
        $match->postponeTo(new \DateTimeImmutable('2025-07-01 18:00'), $this->now);
        $match->popEvents();

        $match->cancel($this->later);

        self::assertTrue($match->isCancelled);
    }

    public function testCancelThrowsWhenFinished(): void
    {
        $match = $this->makeMatch();
        $match->setFinalScore(1, 0, null, null, null, $this->now);

        $this->expectException(SportMatchInvalidTransition::class);
        $match->cancel($this->later);
    }

    public function testCancelIsIdempotent(): void
    {
        $match = $this->makeMatch();
        $match->cancel($this->now);
        $match->popEvents();

        $match->cancel($this->later);

        self::assertCount(0, $match->popEvents());
    }

    public function testSoftDeleteMarksAndFiresEvent(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->softDelete($this->later);

        self::assertTrue($match->isDeleted());
        self::assertFalse($match->isOpenForGuesses);

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchDeleted::class, $events[0]);
    }

    public function testSoftDeleteIsIdempotent(): void
    {
        $match = $this->makeMatch();
        $match->softDelete($this->now);
        $match->popEvents();

        $match->softDelete($this->later);

        self::assertCount(0, $match->popEvents());
    }

    // ── S05: period scores ──────────────────────────────────────────────

    public function testSetFinalScoreStoresPeriodScores(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->setFinalScore(2, 1, PeriodScores::fromArray([[1, 0], [1, 1]]), null, null, $this->now);

        self::assertNotNull($match->periodScores);
        self::assertSame([[1, 0], [1, 1]], $match->periodScores->toArray());
        self::assertNull($match->overtimeHomeScore);
        self::assertFalse($match->hasOvertimeScore);
    }

    public function testSetFinalScoreRejectsWrongPeriodCountForSport(): void
    {
        // Football has 2 periods — 3 pairs must be rejected.
        $match = $this->makeMatch();

        $this->expectException(InvalidScore::class);
        $this->expectExceptionMessage('Zápas musí mít zadané skóre pro 2 poločasy.');
        $match->setFinalScore(2, 1, PeriodScores::fromArray([[1, 0], [1, 1], [0, 0]]), null, null, $this->now);
    }

    public function testSetFinalScoreRejectsPeriodSumsNotMatchingFinalScore(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidScore::class);
        $this->expectExceptionMessage('Součet gólů za jednotlivé části zápasu musí odpovídat konečnému skóre.');
        $match->setFinalScore(2, 1, PeriodScores::fromArray([[1, 0], [0, 1]]), null, null, $this->now);
    }

    public function testCorrectionMayClearPeriodScores(): void
    {
        $match = $this->makeMatch();
        $match->setFinalScore(2, 1, PeriodScores::fromArray([[1, 0], [1, 1]]), null, null, $this->now);
        $match->popEvents();

        $match->setFinalScore(2, 1, null, null, null, $this->later);

        self::assertNull($match->periodScores);
        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchScoreUpdated::class, $events[0]);
    }

    // ── S05: overtime ───────────────────────────────────────────────────

    public function testOvertimeStoredOnDraw(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->setFinalScore(2, 2, PeriodScores::fromArray([[1, 1], [1, 1]]), 3, 2, $this->now);

        self::assertSame(2, $match->homeScore);
        self::assertSame(2, $match->awayScore);
        self::assertSame(3, $match->overtimeHomeScore);
        self::assertSame(2, $match->overtimeAwayScore);
        self::assertTrue($match->hasOvertimeScore);
    }

    public function testOvertimeRejectedWhenRegularScoreIsNotDraw(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidScore::class);
        $this->expectExceptionMessage('Skóre po prodloužení lze zadat jen při remíze v základní hrací době.');
        $match->setFinalScore(2, 1, null, 3, 1, $this->now);
    }

    public function testOvertimeRequiresBothValues(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidScore::class);
        $this->expectExceptionMessage('Zadejte prosím obě hodnoty skóre po prodloužení.');
        $match->setFinalScore(2, 2, null, 3, null, $this->now);
    }

    public function testOvertimeCannotBeADraw(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidScore::class);
        $this->expectExceptionMessage('Skóre po prodloužení nemůže být remíza.');
        $match->setFinalScore(2, 2, null, 3, 3, $this->now);
    }

    public function testOvertimeCannotBeBelowRegularScore(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidScore::class);
        $this->expectExceptionMessage('Skóre po prodloužení nemůže být nižší než skóre v základní hrací době.');
        $match->setFinalScore(2, 2, null, 3, 1, $this->now);
    }

    // ── S05: live score updates ─────────────────────────────────────────

    public function testUpdateLiveScoreFromScheduledTransitionsToLiveAndRecordsOnlyLiveScoreChanged(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->updateLiveScore(1, 0, PeriodScores::fromArray([[1, 0]]), $this->now);

        self::assertTrue($match->isLive);
        self::assertSame(1, $match->homeScore);
        self::assertSame(0, $match->awayScore);
        self::assertNotNull($match->periodScores);
        self::assertSame([[1, 0]], $match->periodScores->toArray());

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchLiveScoreChanged::class, $events[0]);
    }

    public function testUpdateLiveScoreAllowedWhileLive(): void
    {
        $match = $this->makeMatch();
        $match->beginLive($this->now);
        $match->popEvents();

        $match->updateLiveScore(2, 1, null, $this->later);

        self::assertTrue($match->isLive);
        self::assertSame(2, $match->homeScore);
        self::assertSame(1, $match->awayScore);

        $events = $match->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SportMatchLiveScoreChanged::class, $events[0]);
    }

    public function testUpdateLiveScoreRejectedWhenFinished(): void
    {
        $match = $this->makeMatch();
        $match->setFinalScore(1, 0, null, null, null, $this->now);

        $this->expectException(SportMatchInvalidTransition::class);
        $match->updateLiveScore(2, 0, null, $this->later);
    }

    public function testUpdateLiveScoreRejectedWhenPostponed(): void
    {
        $match = $this->makeMatch();
        $match->postponeTo(new \DateTimeImmutable('2025-07-01 18:00:00 UTC'), $this->now);

        $this->expectException(SportMatchInvalidTransition::class);
        $match->updateLiveScore(1, 0, null, $this->later);
    }

    public function testUpdateLiveScoreRejectedWhenCancelled(): void
    {
        $match = $this->makeMatch();
        $match->cancel($this->now);

        $this->expectException(SportMatchCannotBeEdited::class);
        $match->updateLiveScore(1, 0, null, $this->later);
    }

    public function testUpdateLiveScoreRejectedWhenDeleted(): void
    {
        $match = $this->makeMatch();
        $match->softDelete($this->now);

        $this->expectException(SportMatchCannotBeEdited::class);
        $match->updateLiveScore(1, 0, null, $this->later);
    }

    public function testUpdateLiveScoreRejectsNegativeScore(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidScore::class);
        $match->updateLiveScore(-1, 0, null, $this->now);
    }

    public function testUpdateLiveScoreRejectsMorePeriodsThanSportAllows(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidScore::class);
        $this->expectExceptionMessage('Zápas nemůže mít více než 2 poločasy.');
        $match->updateLiveScore(3, 0, PeriodScores::fromArray([[1, 0], [1, 0], [1, 0]]), $this->now);
    }

    public function testUpdateLiveScoreAllowsPartialPeriods(): void
    {
        $match = $this->makeMatch();
        $match->popEvents();

        $match->updateLiveScore(1, 1, PeriodScores::fromArray([[1, 1]]), $this->now);

        self::assertNotNull($match->periodScores);
        self::assertCount(1, $match->periodScores);
    }
}
