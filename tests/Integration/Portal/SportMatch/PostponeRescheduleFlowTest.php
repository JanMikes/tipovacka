<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\SportMatchState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class PostponeRescheduleFlowTest extends WebTestCase
{
    public function testAdminCanPostponeAndReschedule(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/portal/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);
        self::assertResponseIsSuccessful();

        $postponeToken = $crawler
            ->filter('form[action$="/odlozit"] input[name="_token"]')
            ->attr('value');
        self::assertNotNull($postponeToken);

        $client->request('POST', '/portal/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/odlozit', [
            '_token' => $postponeToken,
            'new_kickoff_at' => '2025-10-10T18:00',
        ]);
        self::assertResponseRedirects();

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Postponed, $match->state);

        $crawler = $client->request('GET', '/portal/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);
        self::assertResponseIsSuccessful();

        $rescheduleToken = $crawler
            ->filter('form[action$="/presunout"] input[name="_token"]')
            ->attr('value');
        self::assertNotNull($rescheduleToken);

        $client->request('POST', '/portal/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/presunout', [
            '_token' => $rescheduleToken,
            'new_kickoff_at' => '2025-11-01T20:00',
        ]);
        self::assertResponseRedirects();

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Scheduled, $match->state);
    }
}
