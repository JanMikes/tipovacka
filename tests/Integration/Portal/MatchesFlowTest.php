<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class MatchesFlowTest extends WebTestCase
{
    public function testAnonymousRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/zapasy');

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testPageListsMatchesFromMultipleSouteze(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        // Verified owns VERIFIED_GROUP (private tournament → match "Tygři vs Lvi").
        // Add them to PUBLIC_GROUP (public tournament → "Bohemians 1905" etc.) so the
        // page must aggregate matches across two soutěže / tournaments.
        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $publicGroup = $em->find(Group::class, Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID));
        self::assertNotNull($publicGroup);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $publicGroup,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $client->loginUser($verified);

        $client->request('GET', '/zapasy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Vaše zápasy');

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        // Private-tournament match…
        self::assertStringContainsString('Tygři', $body);
        // …and public-tournament match.
        self::assertStringContainsString('Bohemians 1905', $body);
    }

    public function testFinishedFilterShowsOnlyFinishedMatches(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // Admin (PUBLIC_GROUP) has a finished match (Bohemians) and a scheduled one (Sparta).
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/zapasy?filtr=ukoncene');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString('Bohemians 1905', $body);
        self::assertStringNotContainsString('Sparta Praha', $body);
    }

    public function testTippableFilterShowsOnlyOpenFutureMatches(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/zapasy?filtr=tipovatelne');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        // Scheduled future match is tippable…
        self::assertStringContainsString('Sparta Praha', $body);
        // …the finished one is not.
        self::assertStringNotContainsString('Bohemians 1905', $body);
    }
}
