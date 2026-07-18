<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\MatchSource;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateCuratedMatchSourceFlowTest extends WebTestCase
{
    public function testNonAdminCannotAccessCreatePage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/admin/turnaje/vytvorit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanCreateCuratedMatchSource(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/turnaje/vytvorit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nový zdroj zápasů');

        $client->submitForm('Vytvořit zdroj zápasů', [
            'match_source_form[name]' => 'Nová liga',
        ]);

        self::assertResponseRedirects();

        $em->clear();
        $matchSource = $em->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Nová liga')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertSame(MatchSourceKind::Curated, $matchSource->kind);
        self::assertSame(AppFixtures::ADMIN_ID, $matchSource->owner->id->toRfc4122());
    }
}
