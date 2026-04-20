<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Group;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateGroupFlowTest extends WebTestCase
{
    public function testOwnerCanCreateGroup(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID.'/skupiny/novy');
        self::assertResponseIsSuccessful();

        $client->submitForm('Vytvořit skupinu', [
            'group_form[name]' => 'Další parta',
        ]);

        self::assertResponseRedirects();

        $em->clear();

        $group = $em->createQueryBuilder()
            ->select('g')
            ->from(Group::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Další parta')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Group::class, $group);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $group->owner->id->toRfc4122());
    }
}
