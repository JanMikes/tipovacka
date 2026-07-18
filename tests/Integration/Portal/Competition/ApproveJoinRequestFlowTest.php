<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionJoinRequest;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ApproveJoinRequestFlowTest extends WebTestCase
{
    public function testApproveCreatesMembership(): void
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
            'form[action="/portal/zadosti/'.AppFixtures::PENDING_JOIN_REQUEST_ID.'/schvalit"]'
        )->form();
        $client->submit($form);

        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID);

        $em->clear();
        $request = $em->find(CompetitionJoinRequest::class, Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID));
        self::assertNotNull($request);
        self::assertTrue($request->isApproved);

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.competition = :competitionId')
            ->setParameter('userId', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
    }
}
