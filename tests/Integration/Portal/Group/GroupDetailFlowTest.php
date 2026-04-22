<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Group;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class GroupDetailFlowTest extends WebTestCase
{
    public function testOwnerCanViewOwnGroup(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', AppFixtures::VERIFIED_GROUP_NAME);
    }

    public function testAdminCanViewAnyGroup(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);
        self::assertResponseIsSuccessful();
    }

    public function testMembersListShowsFullNameSubtitleWhenBothPresent(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $owner->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $em->flush();

        $client->loginUser($owner);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);

        // Verified owner has nickname + fullName → subtitle rendered.
        self::assertStringContainsString(AppFixtures::VERIFIED_USER_NICKNAME, $body);
        self::assertMatchesRegularExpression(
            '#<small[^>]*>\s*Jan Tipař\s*</small>#u',
            $body,
            'Nickname+fullName user shows <small>Jan Tipař</small> subtitle.',
        );

        // Anonymous member (no nickname) shows fullName as primary, no <small> subtitle for them.
        self::assertStringContainsString(AppFixtures::ANONYMOUS_USER_FIRST_NAME, $body);
        self::assertDoesNotMatchRegularExpression(
            '#<small[^>]*>\s*'.preg_quote(AppFixtures::ANONYMOUS_USER_FIRST_NAME.' '.AppFixtures::ANONYMOUS_USER_LAST_NAME, '#').'\s*</small>#u',
            $body,
            'No subtitle for fullName-only member.',
        );
    }

    public function testNonMemberReceivesForbidden(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $stranger = new User(
            id: Uuid::v7(),
            email: 'stranger@tipovacka.test',
            password: null,
            nickname: 'stranger_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        $client->loginUser($stranger);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);
        self::assertResponseStatusCodeSame(403);
    }
}
