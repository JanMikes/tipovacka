<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\Team;

use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Service\Team\TeamLogoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
            'team_form[country]' => 'GB',
            'team_form[brandColor]' => '#DA020E',
        ]);

        self::assertResponseRedirects('/admin/tymy');

        $team = $this->teams($client)->findGlobalByName(Uuid::fromString(Sport::FOOTBALL_ID), 'Manchester United');
        self::assertInstanceOf(Team::class, $team);
        self::assertTrue($team->isGlobal);
        self::assertSame('MUN', $team->shortName);
        self::assertSame('GB', $team->country);
        self::assertSame('#DA020E', $team->brandColor);
        self::assertNull($team->logo);
    }

    public function testCountryPickerOffersTheDirectoryAndTheListShowsTheCzechName(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', '/admin/tymy/vytvorit');
        self::assertResponseIsSuccessful();

        // A closed picker over the ISO directory — not free text — with the flag asset on each option.
        $options = $crawler->filter('#team_form_country option');
        self::assertGreaterThan(200, $options->count());
        self::assertSame(
            'Česká republika',
            $crawler->filter('#team_form_country option[value="CZ"]')->text(),
        );
        self::assertStringContainsString(
            'flags/CZE',
            (string) $crawler->filter('#team_form_country option[value="CZ"]')->attr('data-flag'),
        );

        // Sparta is Czech in the fixtures — the directory renders the name, not the raw code.
        $client->request('GET', '/admin/tymy');
        self::assertSelectorTextContains('body', 'Česká republika');
    }

    public function testAdminUploadsAndThenRemovesTeamLogo(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $client->request('GET', '/admin/tymy/'.AppFixtures::TEAM_SPARTA_ID.'/upravit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit změny', [
            'team_form[name]' => 'Sparta Praha',
            'team_form[country]' => 'CZ',
            'team_form[logoFile]' => $this->pngUpload(),
        ]);
        self::assertResponseRedirects('/admin/tymy');

        $team = $this->reloadSparta($client);
        $logo = $team->logo;
        self::assertNotNull($logo);
        self::assertStringEndsWith('.webp', $logo); // whatever was uploaded, WebP is what we store
        self::assertStringNotContainsString('/', $logo); // a storage path, not a baked-in URL
        self::assertSame('/uploads/teams/'.$logo, $this->logoStorage($client)->url($logo));
        self::assertTrue($this->logoStorage($client)->exists($logo));

        // The removal checkbox only exists once there IS a logo — and it deletes the file.
        $client->request('GET', '/admin/tymy/'.AppFixtures::TEAM_SPARTA_ID.'/upravit');
        self::assertSelectorExists('#team_form_removeLogo');

        $client->submitForm('Uložit změny', [
            'team_form[name]' => 'Sparta Praha',
            'team_form[country]' => 'CZ',
            'team_form[removeLogo]' => true,
        ]);
        self::assertResponseRedirects('/admin/tymy');

        self::assertNull($this->reloadSparta($client)->logo);
        self::assertFalse($this->logoStorage($client)->exists($logo));
    }

    public function testLogoRemovalCheckboxIsAbsentForALogolessTeam(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $client->request('GET', '/admin/tymy/'.AppFixtures::TEAM_SPARTA_ID.'/upravit');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#team_form_removeLogo');
    }

    public function testEditingWithoutTouchingTheLogoKeepsIt(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $client->request('GET', '/admin/tymy/'.AppFixtures::TEAM_SPARTA_ID.'/upravit');
        $client->submitForm('Uložit změny', [
            'team_form[name]' => 'Sparta Praha',
            'team_form[logoFile]' => $this->pngUpload(),
        ]);
        $logo = $this->reloadSparta($client)->logo;
        self::assertNotNull($logo);

        $client->request('GET', '/admin/tymy/'.AppFixtures::TEAM_SPARTA_ID.'/upravit');
        $client->submitForm('Uložit změny', ['team_form[name]' => 'AC Sparta Praha']);
        self::assertResponseRedirects('/admin/tymy');

        $team = $this->reloadSparta($client);
        self::assertSame('AC Sparta Praha', $team->name);
        self::assertSame($logo, $team->logo);

        $this->logoStorage($client)->remove($logo);
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

    /** A tiny real PNG on disk — the form's Image constraint sniffs the actual bytes. */
    private function pngUpload(): UploadedFile
    {
        $path = sys_get_temp_dir().'/team-logo-'.uniqid().'.png';
        $image = imagecreatetruecolor(40, 24);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 238, 28, 37));
        imagepng($image, $path);

        return new UploadedFile($path, 'sparta.png', 'image/png', test: true);
    }

    private function reloadSparta(KernelBrowser $client): Team
    {
        $em = $this->em($client);
        $em->clear();
        $team = $em->find(Team::class, Uuid::fromString(AppFixtures::TEAM_SPARTA_ID));
        self::assertInstanceOf(Team::class, $team);

        return $team;
    }

    private function logoStorage(KernelBrowser $client): TeamLogoStorage
    {
        /* @var TeamLogoStorage */
        return $client->getContainer()->get(TeamLogoStorage::class);
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
