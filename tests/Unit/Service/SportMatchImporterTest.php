<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Exception\SportMatchImportFailed;
use App\Repository\SportMatchRepository;
use App\Service\Identity\ProvideIdentity;
use App\Service\SportMatch\SportMatchImporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class SportMatchImporterTest extends TestCase
{
    private SportMatchImporter $importer;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $repo = $this->createStub(SportMatchRepository::class);
        $identity = $this->createStub(ProvideIdentity::class);
        $identity->method('next')->willReturnCallback(fn () => Uuid::v7());

        $this->importer = new SportMatchImporter($repo, $identity);
    }

    private function makeTournament(): Tournament
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: 'u@t.cz',
            password: null,
            nickname: 'u',
            createdAt: $this->now,
        );
        $owner->popEvents();

        $sport = new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
        );

        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: $sport,
            owner: $owner,
            visibility: TournamentVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $tournament->popEvents();

        return $tournament;
    }

    private function uploadedFileWithContent(string $content, string $extension = 'csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'smi_').'.'.$extension;
        file_put_contents($path, $content);

        return new UploadedFile($path, 'matches.'.$extension, null, null, true);
    }

    public function testPreviewParsesValidCsv(): void
    {
        $csv = "Domácí,Hosté,Začátek (YYYY-MM-DD HH:MM),Místo (nepovinné)\n"
            ."Sparta Praha,Slavia Praha,2026-05-10 18:00,Generali Arena\n"
            ."Plzeň,Baník,2026-05-11 20:00,\n";

        $file = $this->uploadedFileWithContent($csv);

        $preview = $this->importer->preview($file, $this->makeTournament(), $this->now);

        self::assertCount(2, $preview->validRows);
        self::assertCount(0, $preview->errors);
        self::assertSame('Sparta Praha', $preview->validRows[0]->homeTeam);
        self::assertSame('Generali Arena', $preview->validRows[0]->venue);
        self::assertNull($preview->validRows[1]->venue);
    }

    public function testPreviewSkipsEmptyRows(): void
    {
        $csv = "Domácí,Hosté,Začátek (YYYY-MM-DD HH:MM),Místo (nepovinné)\n"
            ."A,B,2026-05-10 18:00,\n"
            .",,,\n"
            ."C,D,2026-05-11 18:00,\n";

        $file = $this->uploadedFileWithContent($csv);
        $preview = $this->importer->preview($file, $this->makeTournament(), $this->now);

        self::assertCount(2, $preview->validRows);
        self::assertCount(0, $preview->errors);
    }

    public function testPreviewReportsInvalidDateAsError(): void
    {
        $csv = "Domácí,Hosté,Začátek (YYYY-MM-DD HH:MM),Místo (nepovinné)\n"
            ."A,B,not-a-date,\n";

        $file = $this->uploadedFileWithContent($csv);
        $preview = $this->importer->preview($file, $this->makeTournament(), $this->now);

        self::assertCount(0, $preview->validRows);
        self::assertCount(1, $preview->errors);
        self::assertSame(2, $preview->errors[0]->rowNumber);
    }

    public function testPreviewReportsMissingFieldsAsErrors(): void
    {
        $csv = "Domácí,Hosté,Začátek (YYYY-MM-DD HH:MM),Místo (nepovinné)\n"
            .",B,2026-05-10 18:00,\n";

        $file = $this->uploadedFileWithContent($csv);
        $preview = $this->importer->preview($file, $this->makeTournament(), $this->now);

        self::assertCount(0, $preview->validRows);
        self::assertGreaterThanOrEqual(1, count($preview->errors));
    }

    public function testPreviewThrowsOnMalformedHeaders(): void
    {
        $csv = "Home,Away,When,Where\nA,B,2026-05-10 18:00,\n";

        $file = $this->uploadedFileWithContent($csv);

        $this->expectException(SportMatchImportFailed::class);
        $this->importer->preview($file, $this->makeTournament(), $this->now);
    }

    public function testGenerateTemplateCsvContainsHeaders(): void
    {
        $content = $this->importer->generateTemplateCsv();

        self::assertStringContainsString('Domácí', $content);
        self::assertStringContainsString('Hosté', $content);
        self::assertStringContainsString('Začátek', $content);
        self::assertStringContainsString('Místo', $content);
    }
}
