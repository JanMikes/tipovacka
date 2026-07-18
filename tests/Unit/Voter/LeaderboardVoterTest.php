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
            ->willReturnCallback(function (Uuid $userId, Uuid $competitionId): bool {
                return $this->membershipLookup[$userId->toRfc4122().'|'.$competitionId->toRfc4122()] ?? false;
            });

        $this->voter = new LeaderboardVoter($repository);
    }

    public function testAnonymousDenied(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote(new NullToken(), $competition, [LeaderboardVoter::VIEW]));
        self::assertSame(-1, $this->voter->vote(new NullToken(), $competition, [LeaderboardVoter::RESOLVE_TIES]));
    }

    public function testMemberCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $competition = $this->makeCompetition($owner);
        $this->markAsMember($member, $competition);

        self::assertSame(1, $this->voter->vote($this->token($member), $competition, [LeaderboardVoter::VIEW]));
    }

    public function testOwnerCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(1, $this->voter->vote($this->token($owner), $competition, [LeaderboardVoter::VIEW]));
    }

    public function testAdminCanView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $competition = $this->makeCompetition($owner);

        self::assertSame(1, $this->voter->vote($this->token($admin), $competition, [LeaderboardVoter::VIEW]));
    }

    public function testNonMemberCannotView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_MEMBER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote($this->token($other), $competition, [LeaderboardVoter::VIEW]));
    }

    public function testOwnerCannotResolveTiesWhileMatchSourceActive(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->makeCompetition($owner);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $competition, [LeaderboardVoter::RESOLVE_TIES]));
    }

    public function testOwnerCanResolveTiesOnceFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($owner, finished: true);
        $competition = $this->makeCompetition($owner, $matchSource);

        self::assertSame(1, $this->voter->vote($this->token($owner), $competition, [LeaderboardVoter::RESOLVE_TIES]));
    }

    public function testNonOwnerMemberCannotResolveTies(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::MEMBER_ID);
        $matchSource = $this->makeMatchSource($owner, finished: true);
        $competition = $this->makeCompetition($owner, $matchSource);
        $this->markAsMember($member, $competition);

        self::assertSame(-1, $this->voter->vote($this->token($member), $competition, [LeaderboardVoter::RESOLVE_TIES]));
    }

    public function testAdminCanResolveTiesOnceFinished(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $matchSource = $this->makeMatchSource($owner, finished: true);
        $competition = $this->makeCompetition($owner, $matchSource);

        self::assertSame(1, $this->voter->vote($this->token($admin), $competition, [LeaderboardVoter::RESOLVE_TIES]));
    }

    private function markAsMember(User $user, Competition $competition): void
    {
        $this->membershipLookup[$user->id->toRfc4122().'|'.$competition->id->toRfc4122()] = true;
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

    private function makeCompetition(User $owner, ?MatchSource $matchSource = null): Competition
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

        $competition->popEvents();

        return $competition;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
