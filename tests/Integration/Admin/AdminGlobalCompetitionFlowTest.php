<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AdminGlobalCompetitionFlowTest extends WebTestCase
{
    public function testAdminCompetitionListShowsGlobalCreateAction(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('GET', '/admin/souteze');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vytvořit globální soutěž');
        // Global fixture competition surfaces with its fee.
        self::assertSelectorTextContains('body', AppFixtures::GLOBAL_COMPETITION_NAME);
    }

    public function testAdminCreatesGlobalCompetition(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('GET', '/admin/souteze/globalni/vytvorit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Vytvořit globální soutěž', [
            'global_competition_form[matchSource]' => AppFixtures::PUBLIC_SOURCE_ID,
            'global_competition_form[name]' => 'Admin globální soutěž',
            'global_competition_form[entryFeeCredits]' => '25',
            'global_competition_form[monetization]' => 'none',
        ]);

        self::assertResponseRedirects();

        $competition = $this->competitionByName($client, 'Admin globální soutěž');
        self::assertInstanceOf(Competition::class, $competition);
        self::assertTrue($competition->isGlobal);
        self::assertSame(25, $competition->entryFeeCredits);
    }

    public function testCuratedSourceCreateWithGlobalCheckbox(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('GET', '/admin/turnaje/vytvorit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Vytvořit zdroj zápasů', [
            'match_source_form[sport]' => \App\Entity\Sport::FOOTBALL_ID,
            'match_source_form[name]' => 'Zdroj s globální soutěží',
            'match_source_form[createGlobalCompetition]' => '1',
            'match_source_form[globalCompetitionName]' => 'Soutěž ze zdroje',
            'match_source_form[globalCompetitionEntryFee]' => '10',
            'match_source_form[globalCompetitionMonetization]' => 'none',
        ]);

        self::assertResponseRedirects();

        $em = $this->em($client);
        $em->clear();

        $source = $em->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Zdroj s globální soutěží')
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(MatchSource::class, $source);

        $competition = $this->competitionByName($client, 'Soutěž ze zdroje');
        self::assertInstanceOf(Competition::class, $competition);
        self::assertTrue($competition->isGlobal);
        self::assertSame($source->id->toRfc4122(), $competition->matchSource->id->toRfc4122());
    }

    private function competitionByName(KernelBrowser $client, string $name): ?Competition
    {
        $em = $this->em($client);
        $em->clear();

        return $em->createQueryBuilder()
            ->select('g', 't')
            ->from(Competition::class, 'g')
            ->innerJoin('g.matchSource', 't')
            ->where('g.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private function loginAdmin(KernelBrowser $client): void
    {
        $admin = $this->em($client)->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);
    }
}
