<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Sport;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class AppFixtures extends Fixture
{
    // NOTE: tests/bootstrap.php uses `doctrine:schema:create`, NOT migrations.
    // The football Sport row seeded by the migration is therefore NOT present in the
    // test database. We seed it here too so tests (and any local `doctrine:fixtures:load`)
    // get a consistent baseline. The migration remains the source of truth for prod.

    public const string ADMIN_ID = '01933333-0000-7000-8000-000000000001';
    public const string ADMIN_EMAIL = 'admin@tipovacka.test';
    public const string ADMIN_NICKNAME = 'admin';

    public const string VERIFIED_USER_ID = '01933333-0000-7000-8000-000000000002';
    public const string VERIFIED_USER_EMAIL = 'user@tipovacka.test';
    public const string VERIFIED_USER_NICKNAME = 'tipovac';

    public const string UNVERIFIED_USER_ID = '01933333-0000-7000-8000-000000000003';
    public const string UNVERIFIED_USER_EMAIL = 'unverified@tipovacka.test';
    public const string UNVERIFIED_USER_NICKNAME = 'novy_uzivatel';

    public const string DELETED_USER_ID = '01933333-0000-7000-8000-000000000004';
    public const string DELETED_USER_EMAIL = 'deleted@tipovacka.test';
    public const string DELETED_USER_NICKNAME = 'smazany';

    public const string DEFAULT_PASSWORD = 'password';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        // Seed football Sport for tests/dev (migration seeds the same row in prod via SQL).
        $football = new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
        );
        $manager->persist($football);

        $admin = new User(
            id: Uuid::fromString(self::ADMIN_ID),
            email: self::ADMIN_EMAIL,
            password: null,
            nickname: self::ADMIN_NICKNAME,
            createdAt: $now,
        );
        $admin->changePassword(
            $this->passwordHasher->hashPassword($admin, self::DEFAULT_PASSWORD),
            $now,
        );
        $admin->markAsVerified($now);
        $admin->changeRole(UserRole::ADMIN, $now);
        $admin->popEvents();
        $manager->persist($admin);

        $verified = new User(
            id: Uuid::fromString(self::VERIFIED_USER_ID),
            email: self::VERIFIED_USER_EMAIL,
            password: null,
            nickname: self::VERIFIED_USER_NICKNAME,
            createdAt: $now,
        );
        $verified->changePassword(
            $this->passwordHasher->hashPassword($verified, self::DEFAULT_PASSWORD),
            $now,
        );
        $verified->markAsVerified($now);
        $verified->popEvents();
        $manager->persist($verified);

        $unverified = new User(
            id: Uuid::fromString(self::UNVERIFIED_USER_ID),
            email: self::UNVERIFIED_USER_EMAIL,
            password: null,
            nickname: self::UNVERIFIED_USER_NICKNAME,
            createdAt: $now,
        );
        $unverified->changePassword(
            $this->passwordHasher->hashPassword($unverified, self::DEFAULT_PASSWORD),
            $now,
        );
        $unverified->popEvents();
        $manager->persist($unverified);

        $deleted = new User(
            id: Uuid::fromString(self::DELETED_USER_ID),
            email: self::DELETED_USER_EMAIL,
            password: null,
            nickname: self::DELETED_USER_NICKNAME,
            createdAt: $now,
        );
        $deleted->changePassword(
            $this->passwordHasher->hashPassword($deleted, self::DEFAULT_PASSWORD),
            $now,
        );
        $deleted->markAsVerified($now);
        $deleted->softDelete(new \DateTimeImmutable('2025-06-16 09:00:00 UTC'));
        $deleted->popEvents();
        $manager->persist($deleted);

        $manager->flush();
    }
}
