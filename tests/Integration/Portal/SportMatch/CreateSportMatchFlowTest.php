<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateSportMatchFlowTest extends WebTestCase
{
    public function testOwnerCanCreateMatch(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID.'/zapasy/novy');
        self::assertResponseIsSuccessful();

        $client->submitForm('Vytvořit zápas', [
            'sport_match_form[homeTeam]' => 'Tým A',
            'sport_match_form[awayTeam]' => 'Tým B',
            'sport_match_form[kickoffAt]' => '2025-09-15 18:00',
            'sport_match_form[venue]' => 'Stadion',
        ]);

        self::assertResponseRedirects();

        $em->clear();
        $match = $em->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.homeTeam = :h')
            ->setParameter('h', 'Tým A')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(SportMatch::class, $match);
    }

    public function testNonOwnerCannotCreateMatch(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertNotNull($user);
        $user->markAsVerified(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $user->popEvents();
        $em->flush();

        $client->loginUser($user);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID.'/zapasy/novy');
        self::assertResponseStatusCodeSame(403);
    }
}
