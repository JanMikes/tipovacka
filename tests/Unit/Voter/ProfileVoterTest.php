<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Voter\ProfileVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class ProfileVoterTest extends TestCase
{
    private ProfileVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ProfileVoter();
    }

    private function makeUser(string $id): User
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: Uuid::fromString($id),
            email: 'user'.substr($id, -3).'@test.com',
            password: 'hash',
            nickname: 'user'.substr($id, -3),
            createdAt: $now,
        );
        $user->popEvents();

        return $user;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    public function testOwnerCanEdit(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $result = $this->voter->vote($this->token($user), $user, [ProfileVoter::EDIT]);

        self::assertSame(1, $result); // ACCESS_GRANTED
    }

    public function testOwnerCanDelete(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $result = $this->voter->vote($this->token($user), $user, [ProfileVoter::DELETE]);

        self::assertSame(1, $result);
    }

    public function testOtherUserCannotEdit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(AppFixtures::ADMIN_ID);

        $result = $this->voter->vote($this->token($other), $owner, [ProfileVoter::EDIT]);

        self::assertSame(-1, $result); // ACCESS_DENIED
    }

    public function testOtherUserCannotDelete(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(AppFixtures::ADMIN_ID);

        $result = $this->voter->vote($this->token($other), $owner, [ProfileVoter::DELETE]);

        self::assertSame(-1, $result);
    }
}
