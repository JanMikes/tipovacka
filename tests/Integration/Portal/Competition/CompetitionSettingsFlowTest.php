<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * „Nastavení soutěže" (item 08) — the destination every organizer control moved
 * to when the competition detail became a playing surface. The point of this
 * test is that NO capability was lost in the move: members, invitations,
 * PIN/odkaz, the links to the large forms, leave and delete are all here, and
 * each is still gated by its own voter.
 */
final class CompetitionSettingsFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string OWNED_SETTINGS = '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/nastaveni';
    private const string BOOSTS_SETTINGS = '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID.'/nastaveni';

    public function testOrganizerSeesEveryRelocatedControl(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::OWNED_SETTINGS);
        self::assertResponseIsSuccessful();

        $id = AppFixtures::VERIFIED_COMPETITION_ID;

        // The large forms stay on their own pages and are linked from here.
        self::assertCount(1, $crawler->filter('a[href="/souteze/'.$id.'/upravit"]'));
        self::assertCount(1, $crawler->filter('a[href="/souteze/'.$id.'/pravidla"]'));
        self::assertCount(1, $crawler->filter('a[href="/souteze/'.$id.'/spravovat-tipy"]'));
        self::assertCount(1, $crawler->filter('a[href="/souteze/'.$id.'/clenove/bez-emailu"]'));

        // The small ones are inline: invitations, PIN, shareable link.
        self::assertCount(1, $crawler->filter('form[action="/souteze/'.$id.'/pozvanky/odeslat"]'));
        self::assertCount(1, $crawler->filter('form[action="/souteze/'.$id.'/pozvanky/hromadne"]'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('form[action="/souteze/'.$id.'/pin/novy"]')->count());
        self::assertGreaterThanOrEqual(1, $crawler->filter('form[action="/souteze/'.$id.'/odkaz/novy"]')->count());

        // Members + the danger zone.
        self::assertSelectorTextContains('body', 'Členové');
        self::assertSelectorTextContains('body', AppFixtures::ANONYMOUS_USER_FIRST_NAME);
        self::assertCount(1, $crawler->filter('form[action="/souteze/'.$id.'/smazat"]'));

        // Scoring rules render read-only.
        self::assertSelectorTextContains('body', 'Pravidla bodování');
    }

    public function testPlainMemberSeesTheRosterButNoManagementControls(): void
    {
        // SECOND_VERIFIED_USER is a plain member of the boosts competition (ADMIN owns it).
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_SETTINGS);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body', 'Členové');

        $id = AppFixtures::BOOSTS_COMPETITION_ID;
        self::assertCount(0, $crawler->filter('a[href="/souteze/'.$id.'/upravit"]'));
        self::assertCount(0, $crawler->filter('form[action="/souteze/'.$id.'/pozvanky/odeslat"]'));
        self::assertCount(0, $crawler->filter('form[action="/souteze/'.$id.'/smazat"]'));

        // A member may always walk away.
        self::assertCount(1, $crawler->filter('form[action="/souteze/'.$id.'/opustit"]'));
    }

    public function testNonMemberReceivesForbidden(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $em = $this->testEntityManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $stranger = new User(
            id: Uuid::v7(),
            email: 'stranger-settings@tipovacka.test',
            password: null,
            nickname: 'stranger_set_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        $client->loginUser($stranger);

        $client->request('GET', self::OWNED_SETTINGS);
        self::assertResponseStatusCodeSame(403);
    }
}
