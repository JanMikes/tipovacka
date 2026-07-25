<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\Team;

use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Entity\Team;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class TeamDirectoryFlowTest extends WebTestCase
{
    public function testListShowsDirectoryTeams(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $client->request('GET', '/admin/tymy');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Adresář týmů');
        self::assertSelectorTextContains('body', 'Sparta Praha');
    }

    public function testAdminCreatesGlobalTeam(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $client->request('GET', '/admin/tymy/vytvorit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Vytvořit tým', [
            'team_form[sport]' => Sport::FOOTBALL_ID,
            'team_form[name]' => 'Manchester United',
            'team_form[shortName]' => 'MUN',
            'team_form[country]' => 'gb',
            'team_form[brandColor]' => '#DA020E',
        ]);

        self::assertResponseRedirects('/admin/tymy');

        $team = $this->teams($client)->findGlobalByName(Uuid::fromString(Sport::FOOTBALL_ID), 'Manchester United');
        self::assertInstanceOf(Team::class, $team);
        self::assertTrue($team->isGlobal);
        self::assertSame('MUN', $team->shortName);
        self::assertSame('GB', $team->country); // normalised to upper-case
        self::assertSame('#DA020E', $team->brandColor);
    }

    public function testAdminRenamesTeam(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $client->request('GET', '/admin/tymy/'.AppFixtures::TEAM_SPARTA_ID.'/upravit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Sparta Praha');

        $client->submitForm('Uložit změny', [
            'team_form[name]' => 'AC Sparta Praha',
            'team_form[shortName]' => 'SPA',
            'team_form[country]' => 'CZ',
            'team_form[brandColor]' => '#EE1C25',
        ]);

        self::assertResponseRedirects('/admin/tymy');

        $em = $this->em($client);
        $em->clear();
        $team = $em->find(Team::class, Uuid::fromString(AppFixtures::TEAM_SPARTA_ID));
        self::assertInstanceOf(Team::class, $team);
        self::assertSame('AC Sparta Praha', $team->name);
    }

    public function testNonAdminIsForbidden(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/admin/tymy');

        self::assertResponseStatusCodeSame(403);
    }

    private function login(KernelBrowser $client, string $userId): void
    {
        $user = $this->em($client)->find(\App\Entity\User::class, Uuid::fromString($userId));
        self::assertNotNull($user);
        $client->loginUser($user);
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /* @var EntityManagerInterface */
        return $client->getContainer()->get('doctrine.orm.entity_manager');
    }

    private function teams(KernelBrowser $client): TeamRepository
    {
        /* @var TeamRepository */
        return $client->getContainer()->get(TeamRepository::class);
    }
}
