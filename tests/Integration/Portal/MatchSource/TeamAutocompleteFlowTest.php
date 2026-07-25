<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\MatchSource;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class TeamAutocompleteFlowTest extends WebTestCase
{
    public function testCuratedSourceReturnsGlobalDirectoryTeams(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        // Curated PUBLIC source → global directory; filter „Spar" → Sparta Praha with its meta.
        $client->request('GET', '/portal/zdroje/'.AppFixtures::PUBLIC_SOURCE_ID.'/tymy', ['q' => 'Spar']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertContains(['name' => 'Sparta Praha', 'shortName' => 'SPA', 'country' => 'CZ'], $payload);
    }

    public function testPrivateSourceReturnsOnlyItsLocalTeams(): void
    {
        $client = static::createClient();
        // VERIFIED_USER owns the PRIVATE source ⇒ has CREATE_MATCH.
        $this->login($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/portal/zdroje/'.AppFixtures::PRIVATE_SOURCE_ID.'/tymy');

        self::assertResponseIsSuccessful();
        $names = array_column(json_decode((string) $client->getResponse()->getContent(), true), 'name');

        self::assertContains('Tygři', $names);
        self::assertContains('Lvi', $names);
        // Scope isolation: a global directory team never leaks into a private source's picker.
        self::assertNotContains('Sparta Praha', $names);
    }

    public function testOutsiderIsForbiddenOnPrivateSource(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', '/portal/zdroje/'.AppFixtures::PRIVATE_SOURCE_ID.'/tymy');

        self::assertResponseStatusCodeSame(403);
    }

    private function login(KernelBrowser $client, string $userId): void
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString($userId));
        self::assertNotNull($user);
        $client->loginUser($user);
    }
}
