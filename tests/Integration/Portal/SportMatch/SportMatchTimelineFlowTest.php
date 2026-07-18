<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class SportMatchTimelineFlowTest extends WebTestCase
{
    public function testTimelineRendersOnMatchDetail(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/portal/zapasy/'.AppFixtures::MATCH_FINISHED_ID);

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('h2', 'Průběh zápasu');
        self::assertAnySelectorTextContains('#match-timeline', AppFixtures::PLAYER_HOME_SCORER_ONE_NAME);
        self::assertAnySelectorTextContains('#match-timeline', AppFixtures::PLAYER_HOME_SCORER_TWO_NAME);
        self::assertAnySelectorTextContains('#match-timeline', AppFixtures::PLAYER_AWAY_BOOKED_NAME);
        self::assertAnySelectorTextContains('#match-timeline', 'Výkop');

        // Ordered by minute descending: 63' (goal two) before 27' (goal one).
        $text = $crawler->filter('#match-timeline')->text();
        $posLaterGoal = mb_strpos($text, AppFixtures::PLAYER_HOME_SCORER_TWO_NAME);
        $posEarlierGoal = mb_strpos($text, AppFixtures::PLAYER_HOME_SCORER_ONE_NAME);
        self::assertNotFalse($posLaterGoal);
        self::assertNotFalse($posEarlierGoal);
        self::assertLessThan($posEarlierGoal, $posLaterGoal);

        // Period summary + no timeline on matches without events.
        self::assertAnySelectorTextContains('section', '(1:0, 1:1)');
    }

    public function testNoTimelineSectionWithoutEvents(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('section h2:contains("Průběh zápasu")');
    }

    public function testTimelineRendersOnCompetitionGuessPage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/zapasy/'.AppFixtures::MATCH_FINISHED_ID,
        );

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('h2', 'Průběh zápasu');
        self::assertAnySelectorTextContains('#match-timeline', AppFixtures::PLAYER_HOME_SCORER_ONE_NAME);
    }
}
