<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Service\SportMatch\SportMatchImporter;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * The import preview „nový tým" badge: a team name is flagged new when it doesn't
 * yet resolve in the source's scope AND hasn't appeared earlier in the file.
 */
final class SportMatchImportBadgeTest extends IntegrationTestCase
{
    public function testFlagsOnlyGenuinelyNewTeamsOncePerFile(): void
    {
        $csv = "Domácí,Hosté,Začátek (YYYY-MM-DD HH:MM),Místo (nepovinné)\n"
            ."Sparta Praha,Nový Tým FC,2026-05-10 18:00,\n"
            ."Nový Tým FC,Slavia Praha,2026-05-11 18:00,\n";

        $preview = $this->importer()->preview(
            $this->uploadedCsv($csv),
            $this->publicSource(),
            new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );

        self::assertSame([], $preview->errors);
        self::assertCount(2, $preview->validRows);

        // Row 1: Sparta already exists (curated global team) → not new; the unknown away team → new.
        self::assertFalse($preview->validRows[0]->homeTeamIsNew);
        self::assertTrue($preview->validRows[0]->awayTeamIsNew);

        // Row 2: „Nový Tým FC" already flagged on row 1 → deduped; Slavia exists → not new.
        self::assertFalse($preview->validRows[1]->homeTeamIsNew);
        self::assertFalse($preview->validRows[1]->awayTeamIsNew);
    }

    private function importer(): SportMatchImporter
    {
        /* @var SportMatchImporter */
        return self::getContainer()->get(SportMatchImporter::class);
    }

    private function publicSource(): MatchSource
    {
        $source = $this->entityManager()->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $source);

        return $source;
    }

    private function uploadedCsv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'smib_').'.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'matches.csv', null, null, true);
    }
}
