<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateSportMatchFlowTest extends WebTestCase
{
    public function testAdminCanUpdateMatch(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/upravit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit změny', [
            'sport_match_form[homeTeam]' => 'NEW HOME',
        ]);

        self::assertResponseRedirects();

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('NEW HOME', $match->homeTeam->name);
        // „Kolo" is prefilled from the entity, so an untouched edit round-trips it.
        self::assertSame('Čtvrtfinále', $match->round);
    }

    public function testReassigningATeamOfAMatchWithRecordedScorersIsExplainedOnTheFieldNotA409Page(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        // MATCH_FINISHED carries scorers and a card (Bohemians 1905 vs Jablonec).
        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_FINISHED_ID.'/upravit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit změny', [
            'sport_match_form[homeTeam]' => 'Úplně jiný tým',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Tým zápasu nelze změnit');
        self::assertSame('true', $client->getCrawler()->filter('input[name="sport_match_form[homeTeam]"]')->attr('aria-invalid'));

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('Bohemians 1905', $match->homeTeam->name);
    }

    public function testRoundCanBeChangedAndCleared(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/upravit');
        $client->submitForm('Uložit změny', ['sport_match_form[round]' => 'Osmifinále']);
        self::assertResponseRedirects();

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('Osmifinále', $match->round);

        // Blanking the field clears the round — a match without one is normal.
        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/upravit');
        $client->submitForm('Uložit změny', ['sport_match_form[round]' => '']);
        self::assertResponseRedirects();

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertNull($match->round);
    }
}
