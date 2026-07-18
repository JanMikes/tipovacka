<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Leaderboard;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class SoutezSwitcherFlowTest extends WebTestCase
{
    public function testSwitcherListsAllUserCompetitionsAndLinksResolve(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        // The verified user already owns VERIFIED_COMPETITION. Add them to PUBLIC_COMPETITION too,
        // so they have ≥2 soutěže and the switcher renders.
        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
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

        $client->loginUser($verified);

        $client->request('GET', '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/zebricek');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);

        // Switcher lists both of the user's soutěže by name…
        self::assertStringContainsString(AppFixtures::PUBLIC_COMPETITION_NAME, $body);
        self::assertStringContainsString(AppFixtures::VERIFIED_COMPETITION_NAME, $body);

        // …and links to the other soutěž's leaderboard (the switch target).
        self::assertStringContainsString(
            '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/zebricek',
            $body,
        );
    }

    public function testSwitcherHiddenWhenUserHasSingleCompetition(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // Admin is a member of exactly one soutěž (PUBLIC_COMPETITION) in the baseline fixtures.
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/zebricek');
        self::assertResponseIsSuccessful();

        // No second soutěž → no switch target link to a different competition's leaderboard.
        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringNotContainsString(
            '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/zebricek',
            $body,
        );
    }
}
