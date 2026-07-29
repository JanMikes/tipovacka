<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Leaderboard;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class CompetitionLeaderboardFlowTest extends WebTestCase
{
    public function testMemberCanViewLeaderboard(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/zebricek?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Žebříček');
    }

    /**
     * The board of a NON-global competition is members-only. Item 05 made `/zebricek`
     * public, so the refusal is no longer a 403 — the page silently falls back to a
     * soutěž the viewer may actually see. What must hold either way: nothing of the
     * private competition leaks.
     */
    public function testNonMemberNeverSeesAPrivateCompetitionsBoard(): void
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
            email: 'leaderboard-stranger@tipovacka.test',
            password: null,
            nickname: 'lb_stranger_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        $client->loginUser($stranger);

        $client->request('GET', '/zebricek?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            AppFixtures::PUBLIC_COMPETITION_NAME,
            (string) $client->getResponse()->getContent(),
        );
        self::assertSelectorNotExists('a[href="/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'"]');
    }

    public function testMemberBreakdownPageRenders(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request(
            'GET',
            '/zebricek/clen/'.AppFixtures::ADMIN_ID.'?soutez='.AppFixtures::PUBLIC_COMPETITION_ID,
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', AppFixtures::ADMIN_NICKNAME);
    }

    public function testMatrixViewRendersFullNameSubtitle(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $verified->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: $now);
        $em->flush();

        $client->loginUser($verified);

        $client->request('GET', '/zebricek/matice?soutez='.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertMatchesRegularExpression(
            '#<small[^>]*>\s*Jan Tipař\s*</small>#u',
            $body,
            'Matrix row header renders fullName as <small> subtitle when both nickname and fullName are set.',
        );
    }

    public function testLeaderboardRendersFullNameSubtitleWhenMemberHasBoth(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $verified->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: $now);

        $publicCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($publicCompetition);

        $membership = new Membership(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/zebricek?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString(AppFixtures::VERIFIED_USER_NICKNAME, $body);
        self::assertMatchesRegularExpression(
            '#<small[^>]*>\s*Jan Tipař\s*</small>#u',
            $body,
            'Leaderboard row renders fullName as <small> subtitle when nickname + fullName both set.',
        );
    }

    public function testResolveTiesBlockedWhenMatchSourceActive(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/zebricek/shoda?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertResponseStatusCodeSame(403);
    }
}
