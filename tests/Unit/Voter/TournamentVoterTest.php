<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Enum\UserRole;
use App\Repository\MembershipRepository;
use App\Voter\TournamentVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class TournamentVoterTest extends TestCase
{
    private const string NON_OWNER_ID = '01933333-0000-7000-8000-000000000010';

    private TournamentVoter $voter;
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
        $repository->method('hasActiveMembershipInTournament')
            ->willReturnCallback(function (Uuid $userId, Uuid $tournamentId): bool {
                return $this->membershipLookup[$userId->toRfc4122().'|'.$tournamentId->toRfc4122()] ?? false;
            });

        $this->voter = new TournamentVoter($repository);
    }

    private function markAsMember(User $user, Tournament $tournament): void
    {
        $this->membershipLookup[$user->id->toRfc4122().'|'.$tournament->id->toRfc4122()] = true;
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

    private function makeSport(): Sport
    {
        return new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
        );
    }

    private function makeTournament(
        TournamentVisibility $visibility,
        ?User $owner = null,
        bool $finished = false,
        bool $deleted = false,
    ): Tournament {
        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: $this->makeSport(),
            owner: $owner ?? $this->makeUser(AppFixtures::VERIFIED_USER_ID),
            visibility: $visibility,
            name: 'Test',
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

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    public function testAnonymousCanViewPublicTournament(): void
    {
        $tournament = $this->makeTournament(TournamentVisibility::Public);
        $result = $this->voter->vote(new NullToken(), $tournament, [TournamentVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testAnonymousCannotViewPrivateTournament(): void
    {
        $tournament = $this->makeTournament(TournamentVisibility::Private);
        $result = $this->voter->vote(new NullToken(), $tournament, [TournamentVoter::VIEW]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCanViewPrivateTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $tournament, [TournamentVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testNonOwnerCannotViewPrivateTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);

        $result = $this->voter->vote($this->token($other), $tournament, [TournamentVoter::VIEW]);

        self::assertSame(-1, $result);
    }

    public function testAdminCanViewPrivateTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);

        $result = $this->voter->vote($this->token($admin), $tournament, [TournamentVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testVerifiedUserCanViewPublicTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Public, $owner);

        $result = $this->voter->vote($this->token($user), $tournament, [TournamentVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testGroupMemberCanViewPrivateTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::NON_OWNER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);
        $this->markAsMember($member, $tournament);

        $result = $this->voter->vote($this->token($member), $tournament, [TournamentVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanEditActiveTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $tournament, [TournamentVoter::EDIT]);

        self::assertSame(1, $result);
    }

    public function testOwnerCannotEditFinishedTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner, finished: true);

        $result = $this->voter->vote($this->token($owner), $tournament, [TournamentVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCannotEditDeletedTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner, deleted: true);

        $result = $this->voter->vote($this->token($owner), $tournament, [TournamentVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testAdminCanEditFinishedTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $tournament = $this->makeTournament(TournamentVisibility::Public, $owner, finished: true);

        $result = $this->voter->vote($this->token($admin), $tournament, [TournamentVoter::EDIT]);

        self::assertSame(1, $result);
    }

    public function testNonOwnerVerifiedCannotEdit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Public, $owner);

        $result = $this->voter->vote($this->token($other), $tournament, [TournamentVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCanDeleteActive(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $tournament, [TournamentVoter::DELETE]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanFinishActive(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $tournament, [TournamentVoter::FINISH]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanCreateMatchOnActive(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $tournament, [TournamentVoter::CREATE_MATCH]);

        self::assertSame(1, $result);
    }

    public function testAnonymousDeniedOnEdit(): void
    {
        $tournament = $this->makeTournament(TournamentVisibility::Public);
        $result = $this->voter->vote(new NullToken(), $tournament, [TournamentVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testMemberCanCreateGroupInPrivateTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::NON_OWNER_ID);
        $member->markAsVerified($this->now);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);
        $this->markAsMember($member, $tournament);

        $result = $this->voter->vote($this->token($member), $tournament, [TournamentVoter::CREATE_GROUP]);

        self::assertSame(1, $result);
    }

    public function testNonMemberCannotCreateGroupInPrivateTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $stranger = $this->makeUser(self::NON_OWNER_ID);
        $stranger->markAsVerified($this->now);
        $tournament = $this->makeTournament(TournamentVisibility::Private, $owner);

        $result = $this->voter->vote($this->token($stranger), $tournament, [TournamentVoter::CREATE_GROUP]);

        self::assertSame(-1, $result);
    }

    public function testVerifiedUserCanCreateGroupInPublicTournament(): void
    {
        $owner = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $user = $this->makeUser(self::NON_OWNER_ID);
        $user->markAsVerified($this->now);
        $tournament = $this->makeTournament(TournamentVisibility::Public, $owner);

        $result = $this->voter->vote($this->token($user), $tournament, [TournamentVoter::CREATE_GROUP]);

        self::assertSame(1, $result);
    }
}
