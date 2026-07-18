<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Enum\UserRole;
use App\Voter\MatchSourceVoter;
use App\Voter\SportMatchVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class SportMatchVoterTest extends TestCase
{
    private const string NON_OWNER_ID = '01933333-0000-7000-8000-000000000010';

    private SportMatchVoter $voter;
    private \DateTimeImmutable $now;
    private bool $viewAllowed = true;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $this->viewAllowed = true;

        $security = $this->createStub(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(function (string $attribute): bool {
                return MatchSourceVoter::VIEW === $attribute && $this->viewAllowed;
            });

        $this->voter = new SportMatchVoter($security);
    }

    private function makeUser(string $id, bool $isAdmin = false): User
    {
        $user = new User(
            id: Uuid::fromString($id),
            email: 'u'.substr($id, -3).'@test.com',
            password: 'hash',
            nickname: 'u'.substr($id, -3),
            createdAt: $this->now,
        );

        if ($isAdmin) {
            $user->changeRole(UserRole::ADMIN, $this->now);
        }

        $user->popEvents();

        return $user;
    }

    private function makeMatchSource(?User $owner = null, bool $finished = false, bool $deleted = false): MatchSource
    {
        $sport = new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
        );
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: $sport,
            owner: $owner ?? $this->makeUser(AppFixtures::VERIFIED_USER_ID),
            kind: MatchSourceKind::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        if ($finished) {
            $matchSource->markFinished($this->now);
        }
        if ($deleted) {
            $matchSource->softDelete($this->now);
        }
        $matchSource->popEvents();

        return $matchSource;
    }

    private function makeMatch(MatchSource $matchSource, bool $cancelled = false, bool $deleted = false): SportMatch
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
        if ($cancelled) {
            $m->cancel($this->now);
        }
        if ($deleted) {
            $m->softDelete($this->now);
        }
        $m->popEvents();

        return $m;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    public function testViewDelegatesToMatchSourceVoterGranted(): void
    {
        $matchSource = $this->makeMatchSource();
        $match = $this->makeMatch($matchSource);
        $this->viewAllowed = true;

        $result = $this->voter->vote(new NullToken(), $match, [SportMatchVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testViewDelegatesToMatchSourceVoterDenied(): void
    {
        $matchSource = $this->makeMatchSource();
        $match = $this->makeMatch($matchSource);
        $this->viewAllowed = false;

        $result = $this->voter->vote(new NullToken(), $match, [SportMatchVoter::VIEW]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCanCreateOnActiveMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner);

        $result = $this->voter->vote($this->token($owner), $matchSource, [SportMatchVoter::CREATE]);

        self::assertSame(1, $result);
    }

    public function testAdminCanCreate(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $matchSource = $this->makeMatchSource(owner: $owner);

        $result = $this->voter->vote($this->token($admin), $matchSource, [SportMatchVoter::CREATE]);

        self::assertSame(1, $result);
    }

    public function testNonOwnerCannotCreate(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner);

        $result = $this->voter->vote($this->token($other), $matchSource, [SportMatchVoter::CREATE]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCannotCreateOnFinishedMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner, finished: true);

        $result = $this->voter->vote($this->token($owner), $matchSource, [SportMatchVoter::CREATE]);

        self::assertSame(-1, $result);
    }

    public function testAdminCanCreateOnFinishedMatchSource(): void
    {
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $matchSource = $this->makeMatchSource(finished: true);

        $result = $this->voter->vote($this->token($admin), $matchSource, [SportMatchVoter::CREATE]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanEditMatch(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner);
        $match = $this->makeMatch($matchSource);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::EDIT]);

        self::assertSame(1, $result);
    }

    public function testOwnerCannotEditCancelledMatch(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner);
        $match = $this->makeMatch($matchSource, cancelled: true);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCannotEditSoftDeletedMatch(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner);
        $match = $this->makeMatch($matchSource, deleted: true);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testNonOwnerCannotEdit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner);
        $match = $this->makeMatch($matchSource);

        $result = $this->voter->vote($this->token($other), $match, [SportMatchVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCanSetScore(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner);
        $match = $this->makeMatch($matchSource);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::SET_SCORE]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanCancel(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner);
        $match = $this->makeMatch($matchSource);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::CANCEL]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanDelete(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(owner: $owner);
        $match = $this->makeMatch($matchSource);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::DELETE]);

        self::assertSame(1, $result);
    }

    public function testAnonymousCannotEdit(): void
    {
        $matchSource = $this->makeMatchSource();
        $match = $this->makeMatch($matchSource);

        $result = $this->voter->vote(new NullToken(), $match, [SportMatchVoter::EDIT]);

        self::assertSame(-1, $result);
    }
}
