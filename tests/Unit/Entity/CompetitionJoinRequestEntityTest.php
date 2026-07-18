<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionJoinRequest;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\JoinRequestDecision;
use App\Enum\MatchSourceVisibility;
use App\Event\JoinRequestCreated;
use App\Event\JoinRequestRejected;
use App\Exception\CompetitionJoinRequestAlreadyDecided;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionJoinRequestEntityTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makeUser(string $suffix = 'a'): User
    {
        $user = new User(
            id: Uuid::v7(),
            email: 'u'.$suffix.'@test.com',
            password: 'hash',
            nickname: 'u'.$suffix,
            createdAt: $this->now,
        );
        $user->markAsVerified($this->now);
        $user->popEvents();

        return $user;
    }

    private function makeRequest(): CompetitionJoinRequest
    {
        $owner = $this->makeUser('owner');
        $requester = $this->makeUser('req');

        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: MatchSourceVisibility::Public,
            name: 'Turnaj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $matchSource->popEvents();

        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $owner,
            name: 'Soutěž',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $competition->popEvents();

        return new CompetitionJoinRequest(
            id: Uuid::v7(),
            competition: $competition,
            user: $requester,
            requestedAt: $this->now,
        );
    }

    public function testConstructorRecordsCreatedEvent(): void
    {
        $request = $this->makeRequest();

        $events = $request->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(JoinRequestCreated::class, $events[0]);
    }

    public function testFreshRequestIsNotDecided(): void
    {
        $request = $this->makeRequest();

        self::assertFalse($request->isDecided);
        self::assertFalse($request->isApproved);
        self::assertFalse($request->isRejected);
    }

    public function testApproveSetsState(): void
    {
        $request = $this->makeRequest();
        $request->popEvents();
        $decider = $this->makeUser('dec');

        $request->approve($decider, $this->now);

        self::assertTrue($request->isDecided);
        self::assertTrue($request->isApproved);
        self::assertFalse($request->isRejected);
        self::assertSame(JoinRequestDecision::Approved, $request->decision);
        self::assertSame($decider, $request->decidedBy);
        self::assertSame($this->now, $request->decidedAt);
    }

    public function testCannotApproveTwice(): void
    {
        $request = $this->makeRequest();
        $decider = $this->makeUser('dec');
        $request->approve($decider, $this->now);

        $this->expectException(CompetitionJoinRequestAlreadyDecided::class);

        $request->approve($decider, $this->now);
    }

    public function testRejectRecordsEvent(): void
    {
        $request = $this->makeRequest();
        $request->popEvents();
        $decider = $this->makeUser('dec');

        $request->reject($decider, $this->now);

        self::assertTrue($request->isRejected);
        self::assertFalse($request->isApproved);

        $events = $request->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(JoinRequestRejected::class, $events[0]);
    }

    public function testCannotRejectAfterApprove(): void
    {
        $request = $this->makeRequest();
        $decider = $this->makeUser('dec');
        $request->approve($decider, $this->now);

        $this->expectException(CompetitionJoinRequestAlreadyDecided::class);

        $request->reject($decider, $this->now);
    }

    public function testCannotRejectTwice(): void
    {
        $request = $this->makeRequest();
        $decider = $this->makeUser('dec');
        $request->reject($decider, $this->now);

        $this->expectException(CompetitionJoinRequestAlreadyDecided::class);

        $request->reject($decider, $this->now);
    }
}
