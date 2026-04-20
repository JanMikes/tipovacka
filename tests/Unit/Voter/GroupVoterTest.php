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
use App\Voter\GroupVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class GroupVoterTest extends TestCase
{
    private const string NON_OWNER_ID = '01933333-0000-7000-8000-000000000010';
    private const string MEMBER_ID = '01933333-0000-7000-8000-000000000011';

    private GroupVoter $voter;
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

        $this->voter = new GroupVoter($repository);
    }

    private function markAsMember(User $user, Group $group): void
    {
        $this->membershipLookup[$user->id->toRfc4122().'|'.$group->id->toRfc4122()] = true;
    }

    private function makeUser(string $id, bool $isAdmin = false, bool $verified = true): User
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

        if ($verified) {
            $user->markAsVerified($this->now);
        }

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

    private function makeGroup(User $owner, ?Tournament $tournament = null, bool $deleted = false): Group
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

        if ($deleted) {
            $group->softDelete($this->now);
        }

        $group->popEvents();

        return $group;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    public function testAnonymousAlwaysDenied(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote(new NullToken(), $group, [GroupVoter::VIEW]));
        self::assertSame(-1, $this->voter->vote(new NullToken(), $group, [GroupVoter::JOIN]));
    }

    public function testOwnerCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $group, [GroupVoter::VIEW]));
    }

    public function testAdminCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $group = $this->makeGroup($owner);

        self::assertSame(1, $this->voter->vote($this->token($admin), $group, [GroupVoter::VIEW]));
    }

    public function testMemberCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $group = $this->makeGroup($owner);
        $this->markAsMember($member, $group);

        self::assertSame(1, $this->voter->vote($this->token($member), $group, [GroupVoter::VIEW]));
    }

    public function testNonMemberCannotView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote($this->token($other), $group, [GroupVoter::VIEW]));
    }

    public function testOwnerCanEdit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $group, [GroupVoter::EDIT]));
    }

    public function testOwnerCannotEditWhenTournamentFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($owner, finished: true);
        $group = $this->makeGroup($owner, $tournament);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $group, [GroupVoter::EDIT]));
    }

    public function testOwnerCannotEditWhenDeleted(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner, deleted: true);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $group, [GroupVoter::EDIT]));
    }

    public function testNonOwnerCannotEdit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $group = $this->makeGroup($owner);
        $this->markAsMember($member, $group);

        self::assertSame(-1, $this->voter->vote($this->token($member), $group, [GroupVoter::EDIT]));
    }

    public function testOwnerCanDeleteEvenWhenTournamentFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($owner, finished: true);
        $group = $this->makeGroup($owner, $tournament);

        self::assertSame(1, $this->voter->vote($this->token($owner), $group, [GroupVoter::DELETE]));
    }

    public function testOwnerCanManageMembers(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $group, [GroupVoter::MANAGE_MEMBERS]));
    }

    public function testNonOwnerNonAdminCannotManageMembers(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $group = $this->makeGroup($owner);
        $this->markAsMember($member, $group);

        self::assertSame(-1, $this->voter->vote($this->token($member), $group, [GroupVoter::MANAGE_MEMBERS]));
    }

    public function testVerifiedUserCanJoin(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $user = $this->makeUser(self::NON_OWNER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(1, $this->voter->vote($this->token($user), $group, [GroupVoter::JOIN]));
    }

    public function testUnverifiedUserCannotJoin(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $user = $this->makeUser(self::NON_OWNER_ID, verified: false);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote($this->token($user), $group, [GroupVoter::JOIN]));
    }

    public function testNobodyCanJoinFinishedTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $user = $this->makeUser(self::NON_OWNER_ID);
        $tournament = $this->makeTournament($owner, finished: true);
        $group = $this->makeGroup($owner, $tournament);

        self::assertSame(-1, $this->voter->vote($this->token($user), $group, [GroupVoter::JOIN]));
    }

    public function testMemberCanLeave(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $group = $this->makeGroup($owner);
        $this->markAsMember($member, $group);

        self::assertSame(1, $this->voter->vote($this->token($member), $group, [GroupVoter::LEAVE]));
    }

    public function testOwnerCannotLeave(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $group, [GroupVoter::LEAVE]));
    }

    public function testNonMemberCannotLeave(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote($this->token($other), $group, [GroupVoter::LEAVE]));
    }

    public function testMemberCanInviteMember(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $group = $this->makeGroup($owner);
        $this->markAsMember($member, $group);

        self::assertSame(1, $this->voter->vote($this->token($member), $group, [GroupVoter::INVITE_MEMBER]));
    }

    public function testOwnerCanInviteMember(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $group, [GroupVoter::INVITE_MEMBER]));
    }

    public function testNonMemberCannotInviteMember(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote($this->token($other), $group, [GroupVoter::INVITE_MEMBER]));
    }

    public function testInviteBlockedWhenTournamentFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($owner, finished: true);
        $group = $this->makeGroup($owner, $tournament);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $group, [GroupVoter::INVITE_MEMBER]));
    }

    public function testInviteBlockedWhenGroupDeleted(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $group = $this->makeGroup($owner, deleted: true);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $group, [GroupVoter::INVITE_MEMBER]));
    }

    public function testRequestJoinDeniedForPrivateTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $outsider = $this->makeUser(self::NON_OWNER_ID);
        $group = $this->makeGroup($owner);

        self::assertSame(-1, $this->voter->vote($this->token($outsider), $group, [GroupVoter::REQUEST_JOIN]));
    }

    public function testRequestJoinAllowedForPublicTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $outsider = $this->makeUser(self::NON_OWNER_ID);
        $tournament = $this->makePublicTournament($owner);
        $group = $this->makeGroup($owner, $tournament);

        self::assertSame(1, $this->voter->vote($this->token($outsider), $group, [GroupVoter::REQUEST_JOIN]));
    }

    public function testRequestJoinDeniedForMember(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $tournament = $this->makePublicTournament($owner);
        $group = $this->makeGroup($owner, $tournament);
        $this->markAsMember($member, $group);

        self::assertSame(-1, $this->voter->vote($this->token($member), $group, [GroupVoter::REQUEST_JOIN]));
    }

    public function testRequestJoinDeniedForUnverifiedUser(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $outsider = $this->makeUser(self::NON_OWNER_ID, verified: false);
        $tournament = $this->makePublicTournament($owner);
        $group = $this->makeGroup($owner, $tournament);

        self::assertSame(-1, $this->voter->vote($this->token($outsider), $group, [GroupVoter::REQUEST_JOIN]));
    }

    public function testRequestJoinDeniedWhenTournamentFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $outsider = $this->makeUser(self::NON_OWNER_ID);
        $tournament = $this->makePublicTournament($owner, finished: true);
        $group = $this->makeGroup($owner, $tournament);

        self::assertSame(-1, $this->voter->vote($this->token($outsider), $group, [GroupVoter::REQUEST_JOIN]));
    }

    private function makePublicTournament(User $owner, bool $finished = false): Tournament
    {
        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: TournamentVisibility::Public,
            name: 'Veřejný turnaj',
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
}
