<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\MatchSource;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CreatePrivateMatchSourceFlowTest extends WebTestCase
{
    public function testUnauthenticatedRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/portal/turnaje/vytvorit');

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testVerifiedUserCanLoadCreateForm(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/turnaje/vytvorit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nový zdroj zápasů');
    }

    public function testVerifiedUserCanCreatePrivateMatchSource(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/turnaje/vytvorit');
        $client->submitForm('Vytvořit zdroj zápasů', [
            'match_source_form[name]' => 'Můj nový turnaj',
        ]);

        self::assertResponseRedirects();

        $em->clear();

        $matchSource = $em->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Můj nový turnaj')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertSame(MatchSourceKind::Private, $matchSource->kind);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $matchSource->owner->id->toRfc4122());
    }
}
