<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class BulkImportFlowTest extends WebTestCase
{
    public function testAdminCanPreviewAndCommitImport(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $csv = "Domácí,Hosté,Začátek (YYYY-MM-DD HH:MM),Místo (nepovinné)\n"
            ."ImportA,ImportB,2025-12-01 18:00,Arena 1\n"
            ."ImportC,ImportD,2025-12-02 20:00,\n";

        $path = tempnam(sys_get_temp_dir(), 'smi_').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'matches.csv', 'text/csv', null, true);

        $client->request(
            'GET',
            '/portal/turnaje/'.AppFixtures::PUBLIC_SOURCE_ID.'/zapasy/import',
        );
        self::assertResponseIsSuccessful();

        $client->submitForm('Nahrát a zobrazit náhled', [
            'import_sport_matches_form[file]' => $file,
        ]);
        self::assertResponseIsSuccessful();

        $client->submitForm('Potvrdit import (2 zápasů)');
        self::assertResponseRedirects();

        $em->clear();
        $matches = $em->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->join('m.homeTeam', 't')
            ->where('t.name IN (:h)')
            ->setParameter('h', ['ImportA', 'ImportC'])
            ->getQuery()
            ->getResult();

        self::assertCount(2, $matches);
    }

    public function testImportStoresOptionalRoundColumn(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        // Second row deliberately leaves „Kolo" empty — the column is optional per
        // ROW, not just per file, so a mixed sheet must import cleanly.
        $csv = "Domácí,Hosté,Začátek (YYYY-MM-DD HH:MM),Místo (nepovinné),Kolo (nepovinné)\n"
            ."RoundHome,RoundAway,2025-12-03 18:00,Arena 9,Čtvrtfinále\n"
            ."NoRoundHome,NoRoundAway,2025-12-04 18:00,Arena 10,\n";

        $path = tempnam(sys_get_temp_dir(), 'smi_').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'matches.csv', 'text/csv', null, true);

        $client->request(
            'GET',
            '/portal/turnaje/'.AppFixtures::PUBLIC_SOURCE_ID.'/zapasy/import',
        );
        $client->submitForm('Nahrát a zobrazit náhled', [
            'import_sport_matches_form[file]' => $file,
        ]);
        self::assertResponseIsSuccessful();
        // Preview surfaces the parsed round.
        self::assertSelectorTextContains('body', 'Čtvrtfinále');

        $client->submitForm('Potvrdit import (2 zápasů)');
        self::assertResponseRedirects();

        $em->clear();

        self::assertSame('Čtvrtfinále', $this->importedMatch($em, 'RoundHome')->round);
        self::assertNull($this->importedMatch($em, 'NoRoundHome')->round, 'An empty „Kolo" cell stays null.');
    }

    public function testPreviewShowsErrorsForInvalidRow(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $csv = "Domácí,Hosté,Začátek (YYYY-MM-DD HH:MM),Místo (nepovinné)\n"
            ."A,B,not-a-date,\n";

        $path = tempnam(sys_get_temp_dir(), 'smi_').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'matches.csv', 'text/csv', null, true);

        $client->request(
            'GET',
            '/portal/turnaje/'.AppFixtures::PUBLIC_SOURCE_ID.'/zapasy/import',
        );
        $client->submitForm('Nahrát a zobrazit náhled', [
            'import_sport_matches_form[file]' => $file,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Chyby');
    }

    private function importedMatch(EntityManagerInterface $em, string $homeTeamName): SportMatch
    {
        $match = $em->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->join('m.homeTeam', 't')
            ->where('t.name = :h')
            ->setParameter('h', $homeTeamName)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(SportMatch::class, $match);

        return $match;
    }
}
