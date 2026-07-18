<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\MatchSourceVisibility;
use App\Enum\UserRole;
use App\Repository\MembershipRepository;
use App\Voter\CompetitionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class CompetitionVoterTest extends TestCase
{
    private const string NON_OWNER_ID = '01933333-0000-7000-8000-000000000010';
    private const string MEMBER_ID = '01933333-0000-7000-8000-000000000011';

    private CompetitionVoter $voter;
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
            ->willReturnCallback(function (Uuid $userId, Uuid $competitionId): bool {
                return $this->membershipLookup[$userId->toRfc4122().'|'.$competitionId->toRfc4122()] ?? false;
            });

        $this->voter = new CompetitionVoter($repository);
    }

    private function markAsMember(User $user, Competition $competition): void
    {
        $this->membershipLookup[$user->id->toRfc4122().'|'.$competition->id->toRfc4122()] = true;
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

    private function makeMatchSource(User $owner, bool $finished = false): MatchSource
    {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: MatchSourceVisibility::Private,
            name: 'Turnaj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );

        if ($finished) {
            $matchSource->markFinished($this->now);
        }

        $matchSource->popEvents();

        return $matchSource;
    }

    private function makeCompetition(User $owner, ?MatchSource $matchSource = null, bool $deleted = false): Competition
    {
        $matchSource ??= $this->makeMatchSource($owner);

        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $owner,
            name: 'Soutěž',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );

        if ($deleted) {
            $competition->softDelete($this->now);
        }

        $competition->popEvents();

        return $competition;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    public function testAnonymousAlwaysDenied(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote(new NullToken(), $competition, [CompetitionVoter::VIEW]));
        self::assertSame(-1, $this->voter->vote(new NullToken(), $competition, [CompetitionVoter::JOIN]));
    }

    public function testOwnerCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::VIEW]));
    }

    public function testAdminCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $competition = $this->makeCompetition($owner);

        self::assertSame(1, $this->voter->vote($this->token($admin), $competition, [CompetitionVoter::VIEW]));
    }

    public function testMemberCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $competition = $this->makeCompetition($owner);
        $this->markAsMember($member, $competition);

        self::assertSame(1, $this->voter->vote($this->token($member), $competition, [CompetitionVoter::VIEW]));
    }

    public function testNonMemberCannotView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote($this->token($other), $competition, [CompetitionVoter::VIEW]));
    }

    public function testOwnerCanEdit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::EDIT]));
    }

    public function testOwnerCannotEditWhenMatchSourceFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($owner, finished: true);
        $competition = $this->makeCompetition($owner, $matchSource);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::EDIT]));
    }

    public function testOwnerCannotEditWhenDeleted(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner, deleted: true);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::EDIT]));
    }

    public function testNonOwnerCannotEdit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $competition = $this->makeCompetition($owner);
        $this->markAsMember($member, $competition);

        self::assertSame(-1, $this->voter->vote($this->token($member), $competition, [CompetitionVoter::EDIT]));
    }

    public function testOwnerCanDeleteEvenWhenMatchSourceFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($owner, finished: true);
        $competition = $this->makeCompetition($owner, $matchSource);

        self::assertSame(1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::DELETE]));
    }

    public function testOwnerCanManageMembers(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::MANAGE_MEMBERS]));
    }

    public function testNonOwnerNonAdminCannotManageMembers(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $competition = $this->makeCompetition($owner);
        $this->markAsMember($member, $competition);

        self::assertSame(-1, $this->voter->vote($this->token($member), $competition, [CompetitionVoter::MANAGE_MEMBERS]));
    }

    public function testVerifiedUserCanJoin(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $user = $this->makeUser(self::NON_OWNER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(1, $this->voter->vote($this->token($user), $competition, [CompetitionVoter::JOIN]));
    }

    public function testUnverifiedUserCannotJoin(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $user = $this->makeUser(self::NON_OWNER_ID, verified: false);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote($this->token($user), $competition, [CompetitionVoter::JOIN]));
    }

    public function testNobodyCanJoinFinishedMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $user = $this->makeUser(self::NON_OWNER_ID);
        $matchSource = $this->makeMatchSource($owner, finished: true);
        $competition = $this->makeCompetition($owner, $matchSource);

        self::assertSame(-1, $this->voter->vote($this->token($user), $competition, [CompetitionVoter::JOIN]));
    }

    public function testMemberCanLeave(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $competition = $this->makeCompetition($owner);
        $this->markAsMember($member, $competition);

        self::assertSame(1, $this->voter->vote($this->token($member), $competition, [CompetitionVoter::LEAVE]));
    }

    public function testOwnerCannotLeave(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::LEAVE]));
    }

    public function testNonMemberCannotLeave(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote($this->token($other), $competition, [CompetitionVoter::LEAVE]));
    }

    public function testRegularMemberCannotInviteMember(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $competition = $this->makeCompetition($owner);
        $this->markAsMember($member, $competition);

        self::assertSame(-1, $this->voter->vote($this->token($member), $competition, [CompetitionVoter::INVITE_MEMBER]));
    }

    public function testOwnerCanInviteMember(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::INVITE_MEMBER]));
    }

    public function testNonMemberCannotInviteMember(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote($this->token($other), $competition, [CompetitionVoter::INVITE_MEMBER]));
    }

    public function testInviteBlockedWhenMatchSourceFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($owner, finished: true);
        $competition = $this->makeCompetition($owner, $matchSource);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::INVITE_MEMBER]));
    }

    public function testInviteBlockedWhenCompetitionDeleted(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner, deleted: true);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $competition, [CompetitionVoter::INVITE_MEMBER]));
    }

    public function testRequestJoinDeniedForPrivateMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $outsider = $this->makeUser(self::NON_OWNER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote($this->token($outsider), $competition, [CompetitionVoter::REQUEST_JOIN]));
    }

    public function testRequestJoinAllowedForPublicMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $outsider = $this->makeUser(self::NON_OWNER_ID);
        $matchSource = $this->makePublicMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);

        self::assertSame(1, $this->voter->vote($this->token($outsider), $competition, [CompetitionVoter::REQUEST_JOIN]));
    }

    public function testRequestJoinDeniedForMember(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $matchSource = $this->makePublicMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);
        $this->markAsMember($member, $competition);

        self::assertSame(-1, $this->voter->vote($this->token($member), $competition, [CompetitionVoter::REQUEST_JOIN]));
    }

    public function testRequestJoinDeniedForUnverifiedUser(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $outsider = $this->makeUser(self::NON_OWNER_ID, verified: false);
        $matchSource = $this->makePublicMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);

        self::assertSame(-1, $this->voter->vote($this->token($outsider), $competition, [CompetitionVoter::REQUEST_JOIN]));
    }

    public function testRequestJoinDeniedWhenMatchSourceFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $outsider = $this->makeUser(self::NON_OWNER_ID);
        $matchSource = $this->makePublicMatchSource($owner, finished: true);
        $competition = $this->makeCompetition($owner, $matchSource);

        self::assertSame(-1, $this->voter->vote($this->token($outsider), $competition, [CompetitionVoter::REQUEST_JOIN]));
    }

    private function makePublicMatchSource(User $owner, bool $finished = false): MatchSource
    {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: MatchSourceVisibility::Public,
            name: 'Veřejný turnaj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );

        if ($finished) {
            $matchSource->markFinished($this->now);
        }

        $matchSource->popEvents();

        return $matchSource;
    }
}
