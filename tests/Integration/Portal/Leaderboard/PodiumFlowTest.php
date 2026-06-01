<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Leaderboard;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class PodiumFlowTest extends WebTestCase
{
    public function testPodiumRendersWithThreePlayersAndAScorer(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        // PUBLIC_GROUP starts with one member (admin, who has 3 points from the
        // fixture evaluation). Add two more members → 3 players total, top has > 0
        // points, so the podium shows.
        $publicGroup = $em->find(Group::class, Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID));
        self::assertNotNull($publicGroup);

        foreach ([AppFixtures::VERIFIED_USER_ID, AppFixtures::SECOND_VERIFIED_USER_ID] as $userId) {
            $member = $em->find(User::class, Uuid::fromString($userId));
            self::assertNotNull($member);
            $membership = new Membership(
                id: Uuid::v7(),
                group: $publicGroup,
                user: $member,
                joinedAt: $now,
            );
            $membership->popEvents();
            $em->persist($membership);
        }
        $em->flush();

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zebricek');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString('1. místo', $body);
        self::assertStringContainsString('2. místo', $body);
        self::assertStringContainsString('3. místo', $body);
        // The fixture scorer (admin) tops the podium.
        self::assertStringContainsString(AppFixtures::ADMIN_NICKNAME, $body);
    }

    public function testPodiumHiddenWithFewerThanThreePlayers(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // PUBLIC_GROUP has a single member in the baseline → no podium.
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zebricek');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringNotContainsString('1. místo', $body);
    }
}
