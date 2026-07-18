<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionJoinRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class RejectJoinRequestFlowTest extends WebTestCase
{
    public function testRejectMarksDecided(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(
            'form[action="/portal/zadosti/'.AppFixtures::PENDING_JOIN_REQUEST_ID.'/zamitnout"]'
        )->form();
        $client->submit($form);

        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID);

        $em->clear();
        $request = $em->find(CompetitionJoinRequest::class, Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID));
        self::assertNotNull($request);
        self::assertTrue($request->isRejected);
    }
}
