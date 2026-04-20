<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

use App\Entity\SportMatch;
use App\Entity\Tournament;
use App\Exception\SportMatchImportFailed;
use App\Repository\SportMatchRepository;
use App\Service\Identity\ProvideIdentity;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SportMatchImporter
{
    public const string COLUMN_HOME = 'Domácí';
    public const string COLUMN_AWAY = 'Hosté';
    public const string COLUMN_KICKOFF = 'Začátek (YYYY-MM-DD HH:MM)';
    public const string COLUMN_VENUE = 'Místo (nepovinné)';

    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly ProvideIdentity $identity,
    ) {
    }

    public function preview(UploadedFile $file, Tournament $tournament, \DateTimeImmutable $now): SportMatchImportPreview
    {
        unset($tournament, $now); // unused here; kept for signature/stability

        $spreadsheet = $this->loadSpreadsheet($file);
        $sheet = $spreadsheet->getActiveSheet();
        /** @var array<int, array<int, mixed>> $rows */
        $rows = $sheet->toArray(null, true, true, false);

        if ([] === $rows) {
            throw SportMatchImportFailed::withMessage('Soubor je prázdný.');
        }

        $headerRow = array_shift($rows);
        $headerMap = $this->mapHeader($headerRow);

        $validRows = [];
        $errors = [];

        $rowNumber = 1;
        foreach ($rows as $row) {
            ++$rowNumber;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $home = $this->normaliseString($row[$headerMap[self::COLUMN_HOME]] ?? null);
            $away = $this->normaliseString($row[$headerMap[self::COLUMN_AWAY]] ?? null);
            $kickoffRaw = $this->normaliseString($row[$headerMap[self::COLUMN_KICKOFF]] ?? null);
            $venueRaw = $this->normaliseString($row[$headerMap[self::COLUMN_VENUE]] ?? null);

            $rowErrors = [];

            if ('' === $home) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_HOME, 'Chybí domácí tým.');
            } elseif (mb_strlen($home) > 120) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_HOME, 'Domácí tým je delší než 120 znaků.');
            }

            if ('' === $away) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_AWAY, 'Chybí hostující tým.');
            } elseif (mb_strlen($away) > 120) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_AWAY, 'Hostující tým je delší než 120 znaků.');
            }

            $kickoffAt = null;
            if ('' === $kickoffRaw) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_KICKOFF, 'Chybí začátek zápasu.');
            } else {
                $kickoffAt = $this->parseKickoff($kickoffRaw);
                if (null === $kickoffAt) {
                    $rowErrors[] = new SportMatchImportError(
                        $rowNumber,
                        self::COLUMN_KICKOFF,
                        sprintf('Neplatný formát data "%s". Očekáváno YYYY-MM-DD HH:MM.', $kickoffRaw),
                    );
                }
            }

            $venue = null;
            if ('' !== $venueRaw) {
                if (mb_strlen($venueRaw) > 160) {
                    $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_VENUE, 'Místo je delší než 160 znaků.');
                } else {
                    $venue = $venueRaw;
                }
            }

            if ([] !== $rowErrors) {
                foreach ($rowErrors as $error) {
                    $errors[] = $error;
                }

                continue;
            }

            \assert($kickoffAt instanceof \DateTimeImmutable);

            $validRows[] = new SportMatchImportRow(
                rowNumber: $rowNumber,
                homeTeam: $home,
                awayTeam: $away,
                kickoffAt: $kickoffAt,
                venue: $venue,
            );
        }

        return new SportMatchImportPreview(
            validRows: $validRows,
            errors: $errors,
        );
    }

    /**
     * @param list<SportMatchImportRow> $rows
     */
    public function commit(Tournament $tournament, array $rows, \DateTimeImmutable $now): int
    {
        foreach ($rows as $row) {
            $sportMatch = new SportMatch(
                id: $this->identity->next(),
                tournament: $tournament,
                homeTeam: $row->homeTeam,
                awayTeam: $row->awayTeam,
                kickoffAt: $row->kickoffAt,
                venue: $row->venue,
                createdAt: $now,
            );
            $this->sportMatchRepository->save($sportMatch);
        }

        return count($rows);
    }

    public function generateTemplateCsv(): string
    {
        $header = [self::COLUMN_HOME, self::COLUMN_AWAY, self::COLUMN_KICKOFF, self::COLUMN_VENUE];
        $sample = ['Sparta Praha', 'Slavia Praha', '2026-05-10 18:00', 'Generali Arena'];

        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw SportMatchImportFailed::withMessage('Nelze vygenerovat šablonu.');
        }
        fputcsv($handle, $header, ',', '"', '\\');
        fputcsv($handle, $sample, ',', '"', '\\');

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return false === $contents ? '' : $contents;
    }

    private function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        $path = $file->getRealPath();
        if (false === $path) {
            throw SportMatchImportFailed::withMessage('Soubor nelze přečíst.');
        }

        $extension = strtolower($file->getClientOriginalExtension());

        try {
            if ('csv' === $extension) {
                $reader = new CsvReader();
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');

                return $reader->load($path);
            }

            return IOFactory::load($path);
        } catch (\Throwable $e) {
            throw SportMatchImportFailed::withMessage(sprintf('Nelze načíst soubor: %s', $e->getMessage()));
        }
    }

    /**
     * @param array<int, mixed> $headerRow
     *
     * @return array<string, int>
     */
    private function mapHeader(array $headerRow): array
    {
        $normalised = [];
        foreach ($headerRow as $index => $value) {
            $normalised[(int) $index] = $this->normaliseString($value);
        }

        $map = [];
        foreach ([self::COLUMN_HOME, self::COLUMN_AWAY, self::COLUMN_KICKOFF, self::COLUMN_VENUE] as $expected) {
            $columnIndex = array_search($expected, $normalised, true);
            if (false === $columnIndex) {
                throw SportMatchImportFailed::withMessage(sprintf('Ve zdrojovém souboru chybí sloupec "%s".', $expected));
            }
            $map[$expected] = $columnIndex;
        }

        return $map;
    }

    /**
     * @param array<int, mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ('' !== $this->normaliseString($value)) {
                return false;
            }
        }

        return true;
    }

    private function normaliseString(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return '';
    }

    private function parseKickoff(string $raw): ?\DateTimeImmutable
    {
        foreach (['Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d H:i:s'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed;
            }
        }

        return null;
    }
}
