<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Enum\UserRole;
use App\Voter\AdminUserManagementVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class AdminUserManagementVoterTest extends TestCase
{
    private AdminUserManagementVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new AdminUserManagementVoter();
    }

    private function makeUser(string $id, bool $admin = false): User
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: Uuid::fromString($id),
            email: 'u'.substr($id, -3).'@test.com',
            password: 'h',
            nickname: 'u'.substr($id, -3),
            createdAt: $now,
        );
        $user->popEvents();

        if ($admin) {
            $user->changeRole(UserRole::ADMIN, $now);
        }

        return $user;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    public function testAdminCanBlock(): void
    {
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, admin: true);
        $target = $this->makeUser(AppFixtures::VERIFIED_USER_ID);

        $result = $this->voter->vote($this->token($admin), $target, [AdminUserManagementVoter::BLOCK]);
        self::assertSame(1, $result);
    }

    public function testAdminCanUnblock(): void
    {
        $admin = $this->makeUser(AppFixtures::ADMIN_ID, admin: true);
        $target = $this->makeUser(AppFixtures::VERIFIED_USER_ID);

        $result = $this->voter->vote($this->token($admin), $target, [AdminUserManagementVoter::UNBLOCK]);
        self::assertSame(1, $result);
    }

    public function testRegularUserCannotBlock(): void
    {
        $regular = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $target = $this->makeUser(AppFixtures::UNVERIFIED_USER_ID);

        $result = $this->voter->vote($this->token($regular), $target, [AdminUserManagementVoter::BLOCK]);
        self::assertSame(-1, $result);
    }

    public function testRegularUserCannotUnblock(): void
    {
        $regular = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $target = $this->makeUser(AppFixtures::UNVERIFIED_USER_ID);

        $result = $this->voter->vote($this->token($regular), $target, [AdminUserManagementVoter::UNBLOCK]);
        self::assertSame(-1, $result);
    }
}
