<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Group;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Guess;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class MyTipsBatchFlowTest extends WebTestCase
{
    public function testMemberCanSaveMultipleGuessesAtOnce(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);

        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/moje-tipy',
        );
        self::assertResponseIsSuccessful();

        $batchAction = '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/moje-tipy';
        $formNode = $crawler->filter(sprintf('form[action="%s"]', $batchAction));
        self::assertGreaterThan(0, $formNode->count(), 'Batch save form not found.');

        $form = $formNode->form();
        $form['guesses['.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID.'][homeScore]'] = '3';
        $form['guesses['.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID.'][awayScore]'] = '2';
        $client->submit($form);

        self::assertResponseRedirects();

        $em->clear();
        /** @var Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->andWhere('g.group = :gr')
            ->setParameter('u', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->setParameter('gr', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);
        self::assertSame(3, $guess->homeScore);
        self::assertSame(2, $guess->awayScore);
    }

    public function testNonMemberGets403(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $outsider = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        $group = $em->find(Group::class, Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID));
        self::assertNotNull($outsider);
        self::assertNotNull($group);

        $client->loginUser($outsider);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/moje-tipy');
        self::assertResponseStatusCodeSame(403);
    }
}
