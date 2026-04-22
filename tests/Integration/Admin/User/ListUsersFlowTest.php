<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\User;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ListUsersFlowTest extends WebTestCase
{
    public function testNonAdminCannotAccess(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/admin/uzivatele');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanListUsers(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/uzivatele');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString(AppFixtures::VERIFIED_USER_EMAIL, $body);
        self::assertStringContainsString(AppFixtures::UNVERIFIED_USER_EMAIL, $body);
    }

    public function testSearchFilter(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/uzivatele', [
            'search' => AppFixtures::UNVERIFIED_USER_NICKNAME,
            'verified' => 'all',
            'active' => 'all',
        ]);
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString(AppFixtures::UNVERIFIED_USER_EMAIL, $body);
        self::assertStringNotContainsString(AppFixtures::VERIFIED_USER_EMAIL, $body);
    }

    public function testUserColumnRendersNicknameWithFullNameSubtitle(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $verified->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $em->flush();

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/uzivatele');
        self::assertResponseIsSuccessful();

        $crawler = $client->getCrawler();

        self::assertStringContainsString('Uživatel', $crawler->filter('thead')->text(), 'Column header renamed to "Uživatel".');
        self::assertStringNotContainsString('Přezdívka', $crawler->filter('thead')->text());

        // Row with nickname + full name: subtitle <small> wraps fullName.
        $rowWithBoth = $crawler->filter('tbody tr')->reduce(static fn ($node) => str_contains($node->text(), AppFixtures::VERIFIED_USER_NICKNAME));
        self::assertGreaterThan(0, $rowWithBoth->count());
        self::assertCount(1, $rowWithBoth->filter('small'), 'Subtitle <small> appears when both nickname and fullName exist.');
        self::assertSame('Jan Tipař', trim($rowWithBoth->filter('small')->text()));

        // Row with only fullName (anonymous): no <small> subtitle, fullName is primary.
        $rowAnonymousOnly = $crawler->filter('tbody tr')->reduce(static fn ($node) => str_contains($node->text(), AppFixtures::ANONYMOUS_USER_FIRST_NAME));
        self::assertGreaterThan(0, $rowAnonymousOnly->count());
        self::assertCount(0, $rowAnonymousOnly->filter('small'), 'No subtitle when only fullName is present.');

        // Row with only nickname (admin): no subtitle.
        $rowNicknameOnly = $crawler->filter('tbody tr')->reduce(static fn ($node) => str_contains($node->text(), AppFixtures::ADMIN_EMAIL));
        self::assertGreaterThan(0, $rowNicknameOnly->count());
        self::assertCount(0, $rowNicknameOnly->filter('small'), 'No subtitle when only nickname is present.');
    }
}
