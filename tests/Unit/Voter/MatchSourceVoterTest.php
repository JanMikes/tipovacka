<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Enum\UserRole;
use App\Repository\MembershipRepository;
use App\Voter\MatchSourceVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class MatchSourceVoterTest extends TestCase
{
    private const string NON_OWNER_ID = '01933333-0000-7000-8000-000000000010';

    private MatchSourceVoter $voter;
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
        $repository->method('hasActiveMembershipInMatchSource')
            ->willReturnCallback(function (Uuid $userId, Uuid $matchSourceId): bool {
                return $this->membershipLookup[$userId->toRfc4122().'|'.$matchSourceId->toRfc4122()] ?? false;
            });

        $this->voter = new MatchSourceVoter($repository);
    }

    private function markAsMember(User $user, MatchSource $matchSource): void
    {
        $this->membershipLookup[$user->id->toRfc4122().'|'.$matchSource->id->toRfc4122()] = true;
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
            periodCount: 2,
            periodLabelSingular: 'poločas',
            periodLabelPlural: 'poločasy',
        );
    }

    private function makeMatchSource(
        MatchSourceKind $kind,
        ?User $owner = null,
        bool $finished = false,
        bool $deleted = false,
    ): MatchSource {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: $this->makeSport(),
            owner: $owner ?? $this->makeUser(AppFixtures::VERIFIED_USER_ID),
            kind: $kind,
            name: 'Test',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );

        if ($finished) {
            $matchSource->markCompleted($this->now);
        }

        if ($deleted) {
            $matchSource->softDelete($this->now);
        }

        $matchSource->popEvents();

        return $matchSource;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    public function testAnonymousCanViewPublicMatchSource(): void
    {
        $matchSource = $this->makeMatchSource(MatchSourceKind::Curated);
        $result = $this->voter->vote(new NullToken(), $matchSource, [MatchSourceVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testAnonymousCannotViewPrivateMatchSource(): void
    {
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private);
        $result = $this->voter->vote(new NullToken(), $matchSource, [MatchSourceVoter::VIEW]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCanViewPrivateMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $matchSource, [MatchSourceVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testNonOwnerCannotViewPrivateMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);

        $result = $this->voter->vote($this->token($other), $matchSource, [MatchSourceVoter::VIEW]);

        self::assertSame(-1, $result);
    }

    public function testAdminCanViewPrivateMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);

        $result = $this->voter->vote($this->token($admin), $matchSource, [MatchSourceVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testVerifiedUserCanViewPublicMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Curated, $owner);

        $result = $this->voter->vote($this->token($user), $matchSource, [MatchSourceVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testCompetitionMemberCanViewPrivateMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::NON_OWNER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);
        $this->markAsMember($member, $matchSource);

        $result = $this->voter->vote($this->token($member), $matchSource, [MatchSourceVoter::VIEW]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanEditActiveMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $matchSource, [MatchSourceVoter::EDIT]);

        self::assertSame(1, $result);
    }

    public function testOwnerCannotEditFinishedMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner, finished: true);

        $result = $this->voter->vote($this->token($owner), $matchSource, [MatchSourceVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCannotEditDeletedMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner, deleted: true);

        $result = $this->voter->vote($this->token($owner), $matchSource, [MatchSourceVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testAdminCanEditFinishedMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Curated, $owner, finished: true);

        $result = $this->voter->vote($this->token($admin), $matchSource, [MatchSourceVoter::EDIT]);

        self::assertSame(1, $result);
    }

    public function testNonOwnerVerifiedCannotEdit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::NON_OWNER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Curated, $owner);

        $result = $this->voter->vote($this->token($other), $matchSource, [MatchSourceVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testOwnerCanDeleteActive(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $matchSource, [MatchSourceVoter::DELETE]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanFinishActive(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $matchSource, [MatchSourceVoter::COMPLETE]);

        self::assertSame(1, $result);
    }

    public function testOwnerCanCreateMatchOnActive(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);

        $result = $this->voter->vote($this->token($owner), $matchSource, [MatchSourceVoter::CREATE_MATCH]);

        self::assertSame(1, $result);
    }

    public function testAnonymousDeniedOnEdit(): void
    {
        $matchSource = $this->makeMatchSource(MatchSourceKind::Curated);
        $result = $this->voter->vote(new NullToken(), $matchSource, [MatchSourceVoter::EDIT]);

        self::assertSame(-1, $result);
    }

    public function testMemberCanCreateCompetitionInPrivateMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $member = $this->makeUser(self::NON_OWNER_ID);
        $member->markAsVerified($this->now);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);
        $this->markAsMember($member, $matchSource);

        $result = $this->voter->vote($this->token($member), $matchSource, [MatchSourceVoter::CREATE_COMPETITION]);

        self::assertSame(1, $result);
    }

    public function testNonMemberCannotCreateCompetitionInPrivateMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $stranger = $this->makeUser(self::NON_OWNER_ID);
        $stranger->markAsVerified($this->now);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Private, $owner);

        $result = $this->voter->vote($this->token($stranger), $matchSource, [MatchSourceVoter::CREATE_COMPETITION]);

        self::assertSame(-1, $result);
    }

    public function testVerifiedUserCanCreateCompetitionInPublicMatchSource(): void
    {
        $owner = $this->makeUser(AppFixtures::ADMIN_ID, isAdmin: true);
        $user = $this->makeUser(self::NON_OWNER_ID);
        $user->markAsVerified($this->now);
        $matchSource = $this->makeMatchSource(MatchSourceKind::Curated, $owner);

        $result = $this->voter->vote($this->token($user), $matchSource, [MatchSourceVoter::CREATE_COMPETITION]);

        self::assertSame(1, $result);
    }
}
