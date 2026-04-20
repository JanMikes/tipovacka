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

final class SetFinalScoreFlowTest extends WebTestCase
{
    public function testAdminCanSetFinalScore(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/skore');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit skóre', [
            'set_final_score_form[homeScore]' => 2,
            'set_final_score_form[awayScore]' => 2,
        ]);

        self::assertResponseRedirects();

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertSame(2, $match->homeScore);
        self::assertSame(2, $match->awayScore);
    }
}
