<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateCompetitionFlowTest extends WebTestCase
{
    public function testOwnerCanCreateCompetition(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_SOURCE_ID.'/souteze/novy');
        self::assertResponseIsSuccessful();

        $client->submitForm('Vytvořit soutěž', [
            'competition_form[name]' => 'Další parta',
        ]);

        self::assertResponseRedirects();

        $em->clear();

        $competition = $em->createQueryBuilder()
            ->select('g')
            ->from(Competition::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Další parta')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $competition->owner->id->toRfc4122());
    }
}
