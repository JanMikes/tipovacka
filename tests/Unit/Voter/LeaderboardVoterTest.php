<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Sport;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Enum\UserRole;
use App\Repository\MembershipRepository;
use App\Voter\LeaderboardVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class LeaderboardVoterTest extends TestCase
{
    private const string NON_MEMBER_ID = '01933333-0000-7000-8000-000000000020';
    private const string MEMBER_ID = '01933333-0000-7000-8000-000000000021';

    private LeaderboardVoter $voter;
    private \DateTimeImmutable $now;

    /**
     * @var array<string, bool>
     */
    private array $membershipLookup = [];

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $this->membershipLookup = [];

        $repository = $this->createStub(MembershipRepository::class);
        $repository->method('hasActiveMembership')
            ->willReturnCallback(function (Uuid $userId, Uuid $groupId): bool {
                return $this->membershipLookup[$userId->toRfc4122().'|'.$groupId->toRfc4122()] ?? false;
            });

        $this->voter = new LeaderboardVoter($repository);
    }

    public function testAnonymousDenied(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote(new NullToken(), $group, [LeaderboardVoter::VIEW]));
        self::assertSame(-1, $this->voter->vote(new NullToken(), $group, [LeaderboardVoter::RESOLVE_TIES]));
    }

    public function testMemberCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $group = $this->makeGroup($owner);
        $this->markAsMember($member, $group);

        self::assertSame(1, $this->voter->vote($this->token($member), $group, [LeaderboardVoter::VIEW]));
    }

    public function testOwnerCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $group, [LeaderboardVoter::VIEW]));
    }

    public function testAdminCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $group = $this->makeGroup($owner);

        self::assertSame(1, $this->voter->vote($this->token($admin), $group, [LeaderboardVoter::VIEW]));
    }

    public function testNonMemberCannotView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_MEMBER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote($this->token($other), $group, [LeaderboardVoter::VIEW]));
    }

    public function testOwnerCannotResolveTiesWhileTournamentActive(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $group, [LeaderboardVoter::RESOLVE_TIES]));
    }

    public function testOwnerCanResolveTiesOnceFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($owner, finished: true);
        $group = $this->makeGroup($owner, $tournament);

        self::assertSame(1, $this->voter->vote($this->token($owner), $group, [LeaderboardVoter::RESOLVE_TIES]));
    }

    public function testNonOwnerMemberCannotResolveTies(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $tournament = $this->makeTournament($owner, finished: true);
        $group = $this->makeGroup($owner, $tournament);
        $this->markAsMember($member, $group);

        self::assertSame(-1, $this->voter->vote($this->token($member), $group, [LeaderboardVoter::RESOLVE_TIES]));
    }

    public function testAdminCanResolveTiesOnceFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $tournament = $this->makeTournament($owner, finished: true);
        $group = $this->makeGroup($owner, $tournament);

        self::assertSame(1, $this->voter->vote($this->token($admin), $group, [LeaderboardVoter::RESOLVE_TIES]));
    }

    private function markAsMember(User $user, Group $group): void
    {
        $this->membershipLookup[$user->id->toRfc4122().'|'.$group->id->toRfc4122()] = true;
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

        $user->markAsVerified($this->now);
        $user->popEvents();

        return $user;
    }

    private function makeTournament(User $owner, bool $finished = false): Tournament
    {
        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: TournamentVisibility::Private,
            name: 'Turnaj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );

        if ($finished) {
            $tournament->markFinished($this->now);
        }

        $tournament->popEvents();

        return $tournament;
    }

    private function makeGroup(User $owner, ?Tournament $tournament = null): Group
    {
        $tournament ??= $this->makeTournament($owner);

        $group = new Group(
            id: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            tournament: $tournament,
            owner: $owner,
            name: 'Skupina',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );

        $group->popEvents();

        return $group;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
