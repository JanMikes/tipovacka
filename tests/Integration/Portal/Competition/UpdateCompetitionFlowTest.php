<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateCompetitionFlowTest extends WebTestCase
{
    private const string EDIT_URL = '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/upravit';

    public function testEditPageShowsLockInfoInsteadOfDeadlineField(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $crawler = $client->request('GET', self::EDIT_URL);
        self::assertResponseIsSuccessful();

        // S07: the old group-wide deadline field is gone…
        self::assertCount(0, $crawler->filter('input[name="competition_form[tipsDeadline]"]'));
        // …replaced by read-only lock info with the lock moment
        // (first kickoff 2025-06-20 19:00 UTC = 21:00 Europe/Prague).
        self::assertSelectorTextContains('body', 'Tipy se uzamknou startem soutěže');
        self::assertSelectorTextContains('body', '20. 6. 2025 21:00');
    }

    public function testEditPageShowsLockedStateWhenTipsAreLocked(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);

        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertNotNull($competition);
        $competition->lockTips(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $competition->popEvents();
        $em->flush();

        $client->loginUser($owner);

        $client->request('GET', self::EDIT_URL);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Tipy jsou uzamčeny.');
    }

    public function testOwnerCanRenameCompetition(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', self::EDIT_URL);
        $client->submitForm('Uložit změny', [
            'competition_form[name]' => 'Přejmenovaná parta',
        ]);

        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);

        $em->clear();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        self::assertSame('Přejmenovaná parta', $competition->name);
    }
}
