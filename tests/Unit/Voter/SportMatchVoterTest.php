<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Enum\UserRole;
use App\Voter\SportMatchVoter;
use App\Voter\TournamentVoter;
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
                return TournamentVoter::VIEW === $attribute && $this->viewAllowed;
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

    private function makeTournament(?User $owner = null, bool $finished = false, bool $deleted = false): Tournament
    {
        $sport = new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
        );
        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: $sport,
            owner: $owner ?? $this->makeUser(AppFixtures::VERIFIED_USER_ID),
            visibility: TournamentVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        if ($finished) {
            $tournament->markFinished($this->now);
        }
        if ($deleted) {
            $tournament->softDelete($this->now);
        }
        $tournament->popEvents();

        return $tournament;
    }

    private function makeMatch(Tournament $tournament, bool $cancelled = false, bool $deleted = false): SportMatch
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

    public function testViewDelegatesToTournamentVoterGranted(): void
    {
        $tournament = $this->makeTournament();
        $match = $this->makeMatch($tournament);
        $this->viewAllowed = true;

        $result = $this->voter->vote(new NullToken(), $match, [SportMatchVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testViewDelegatesToTournamentVoterDenied(): void
    {
        $tournament = $this->makeTournament();
        $match = $this->makeMatch($tournament);
        $this->viewAllowed = false;

        $result = $this->voter->vote(new NullToken(), $match, [SportMatchVoter::VIEW]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCanCreateOnActiveTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(owner: $owner);

        $result = $this->voter->vote($this->token($owner), $tournament, [SportMatchVoter::CREATE]);

        self::assertSame(1, $result);
    }

    public function testAdminCanCreate(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $tournament = $this->makeTournament(owner: $owner);

        $result = $this->voter->vote($this->token($admin), $tournament, [SportMatchVoter::CREATE]);

        self::assertSame(1, $result);
    }

    public function testNonOwnerCannotCreate(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $tournament = $this->makeTournament(owner: $owner);

        $result = $this->voter->vote($this->token($other), $tournament, [SportMatchVoter::CREATE]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCannotCreateOnFinishedTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(owner: $owner, finished: true);

        $result = $this->voter->vote($this->token($owner), $tournament, [SportMatchVoter::CREATE]);

        self::assertSame(-1, $result);
    }

    public function testAdminCanCreateOnFinishedTournament(): void
    {
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $tournament = $this->makeTournament(finished: true);

        $result = $this->voter->vote($this->token($admin), $tournament, [SportMatchVoter::CREATE]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanEditMatch(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(owner: $owner);
        $match = $this->makeMatch($tournament);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::EDIT]);

        self::assertSame(1, $result);
    }

    public function testOwnerCannotEditCancelledMatch(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(owner: $owner);
        $match = $this->makeMatch($tournament, cancelled: true);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCannotEditSoftDeletedMatch(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(owner: $owner);
        $match = $this->makeMatch($tournament, deleted: true);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testNonOwnerCannotEdit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $tournament = $this->makeTournament(owner: $owner);
        $match = $this->makeMatch($tournament);

        $result = $this->voter->vote($this->token($other), $match, [SportMatchVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCanSetScore(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(owner: $owner);
        $match = $this->makeMatch($tournament);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::SET_SCORE]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanCancel(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(owner: $owner);
        $match = $this->makeMatch($tournament);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::CANCEL]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanDelete(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(owner: $owner);
        $match = $this->makeMatch($tournament);

        $result = $this->voter->vote($this->token($owner), $match, [SportMatchVoter::DELETE]);

        self::assertSame(1, $result);
    }

    public function testAnonymousCannotEdit(): void
    {
        $tournament = $this->makeTournament();
        $match = $this->makeMatch($tournament);

        $result = $this->voter->vote(new NullToken(), $match, [SportMatchVoter::EDIT]);

        self::assertSame(-1, $result);
    }
}
