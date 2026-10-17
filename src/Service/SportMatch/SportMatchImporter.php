<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Exception\SportMatchImportFailed;
use App\Repository\SportMatchRepository;
use App\Service\Identity\ProvideIdentity;
use App\Service\Team\TeamResolver;
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
    public const string COLUMN_ROUND = 'Kolo (nepovinné)';
    public const string COLUMN_PLAYOFF = 'Playoff (nepovinné)';

    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly TeamResolver $teamResolver,
        private readonly ProvideIdentity $identity,
    ) {
    }

    public function preview(UploadedFile $file, MatchSource $matchSource, \DateTimeImmutable $now): SportMatchImportPreview
    {
        unset($now); // unused here; kept for signature/stability

        // Lowercased team name → already known (exists in the source's scope OR seen
        // earlier in this file), so the „nový tým" badge shows once per new name.
        $seenTeams = [];

        $spreadsheet = $this->loadSpreadsheet($file);
        $sheet = $spreadsheet->getActiveSheet();
        /** @var array<int, array<int, mixed>> $rows */
        $rows = $sheet->toArray(null, true, true, false);

        if ([] === $rows) {
            throw SportMatchImportFailed::withMessage('Soubor je prázdný. Vyplňte do šablony aspoň jeden zápas a nahrajte ho znovu.');
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
            $roundRaw = isset($headerMap[self::COLUMN_ROUND])
                ? $this->normaliseString($row[$headerMap[self::COLUMN_ROUND]] ?? null)
                : '';
            $playoffRaw = isset($headerMap[self::COLUMN_PLAYOFF])
                ? $this->normaliseString($row[$headerMap[self::COLUMN_PLAYOFF]] ?? null)
                : '';

            $rowErrors = [];

            if ('' === $home) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_HOME, 'Chybí domácí tým — doplňte jeho název.');
            } elseif (mb_strlen($home) > 120) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_HOME, 'Název domácího týmu je delší než 120 znaků — zkraťte ho.');
            }

            if ('' === $away) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_AWAY, 'Chybí hostující tým — doplňte jeho název.');
            } elseif (mb_strlen($away) > 120) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_AWAY, 'Název hostujícího týmu je delší než 120 znaků — zkraťte ho.');
            }

            if ('' !== $home && mb_strtolower($home) === mb_strtolower($away)) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_AWAY, sprintf('Tým „%s" nemůže hrát sám se sebou — opravte domácí nebo hostující tým.', $away));
            }

            $kickoffAt = null;
            if ('' === $kickoffRaw) {
                $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_KICKOFF, 'Chybí začátek zápasu — doplňte datum a čas, např. 2026-05-10 18:00.');
            } else {
                $kickoffAt = $this->parseKickoff($kickoffRaw);
                if (null === $kickoffAt) {
                    $rowErrors[] = new SportMatchImportError(
                        $rowNumber,
                        self::COLUMN_KICKOFF,
                        sprintf('Datum „%s" neumíme přečíst. Zapište ho jako RRRR-MM-DD HH:MM v českém čase, např. 2026-05-10 18:00.', $kickoffRaw),
                    );
                }
            }

            $venue = null;
            if ('' !== $venueRaw) {
                if (mb_strlen($venueRaw) > 160) {
                    $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_VENUE, 'Místo je delší než 160 znaků — zkraťte ho.');
                } else {
                    $venue = $venueRaw;
                }
            }

            $round = null;
            if ('' !== $roundRaw) {
                if (mb_strlen($roundRaw) > 120) {
                    $rowErrors[] = new SportMatchImportError($rowNumber, self::COLUMN_ROUND, 'Kolo je delší než 120 znaků — zkraťte ho.');
                } else {
                    $round = $roundRaw;
                }
            }

            $isPlayoff = false;
            if ('' !== $playoffRaw) {
                $parsedPlayoff = $this->parsePlayoff($playoffRaw);
                if (null === $parsedPlayoff) {
                    $rowErrors[] = new SportMatchImportError(
                        $rowNumber,
                        self::COLUMN_PLAYOFF,
                        sprintf('Hodnotě „%s" nerozumíme. Napište ano nebo ne (případně 1/0), nebo pole nechte prázdné.', $playoffRaw),
                    );
                } else {
                    $isPlayoff = $parsedPlayoff;
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
                round: $round,
                isPlayoff: $isPlayoff,
                homeTeamIsNew: $this->isNewTeamName($matchSource, $home, $seenTeams),
                awayTeamIsNew: $this->isNewTeamName($matchSource, $away, $seenTeams),
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
    public function commit(MatchSource $matchSource, array $rows, \DateTimeImmutable $now): int
    {
        // Resolve each distinct team NAME once for the whole batch. resolve() finds
        // teams via a DB query, which cannot see a team persisted earlier in this same
        // (still unflushed) transaction — so a team playing several matches in one
        // import would otherwise be created once per appearance and collide on the
        // (match_source_id, name) / (sport_id, name) unique index on flush. The key is
        // case-insensitive to match the resolver's own LOWER(name) = LOWER(name) lookup.
        $resolvedTeams = [];
        $resolve = function (string $name) use ($matchSource, $now, &$resolvedTeams): Team {
            $key = mb_strtolower(trim($name));

            return $resolvedTeams[$key] ??= $this->teamResolver->resolve($matchSource, $name, $now);
        };

        foreach ($rows as $row) {
            $sportMatch = new SportMatch(
                id: $this->identity->next(),
                matchSource: $matchSource,
                homeTeam: $resolve($row->homeTeam),
                awayTeam: $resolve($row->awayTeam),
                kickoffAt: $row->kickoffAt,
                venue: $row->venue,
                createdAt: $now,
                round: $row->round,
                isPlayoff: $row->isPlayoff,
            );
            $this->sportMatchRepository->save($sportMatch);
        }

        return count($rows);
    }

    /**
     * A team name is „new" if it doesn't yet resolve to a team in the source's scope
     * AND hasn't already appeared earlier in this file. Marks the name seen so a
     * repeated new name is flagged only on its first row.
     *
     * @param array<string, true> $seen lowercased name → already known
     */
    private function isNewTeamName(MatchSource $matchSource, string $name, array &$seen): bool
    {
        $key = mb_strtolower(trim($name));

        if (isset($seen[$key])) {
            return false;
        }

        $seen[$key] = true;

        return null === $this->teamResolver->findExisting($matchSource, $name);
    }

    public function generateTemplateCsv(): string
    {
        $header = [self::COLUMN_HOME, self::COLUMN_AWAY, self::COLUMN_KICKOFF, self::COLUMN_VENUE, self::COLUMN_ROUND, self::COLUMN_PLAYOFF];
        $sample = ['Sparta Praha', 'Slavia Praha', '2026-05-10 18:00', 'Generali Arena', 'Čtvrtfinále', 'ne'];

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
            throw SportMatchImportFailed::withMessage('Soubor se nepodařilo přečíst. Nahrajte ho prosím znovu.');
        }

        $extension = strtolower($file->getClientOriginalExtension());

        try {
            if ('csv' === $extension) {
                $reader = new CsvReader();
                // Czech Excel saves „CSV" as Windows-1250 separated by semicolons;
                // the template is UTF-8 with commas. Accept both.
                $reader->setInputEncoding(CsvReader::GUESS_ENCODING);
                $reader->setFallbackEncoding('Windows-1250');
                $reader->setDelimiter($this->sniffDelimiter($path));
                $reader->setEnclosure('"');

                return $reader->load($path);
            }

            return IOFactory::load($path);
        } catch (\Throwable $e) {
            throw SportMatchImportFailed::withMessage('Soubor se nepodařilo přečíst. Uložte ho prosím jako XLSX, nebo jako CSV oddělené čárkou či středníkem, a nahrajte znovu.', $e);
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
                throw SportMatchImportFailed::withMessage(sprintf('V prvním řádku souboru chybí sloupec „%s". První řádek musí obsahovat názvy sloupců přesně jako šablona — stáhněte si ji a data do ní zkopírujte.', $expected));
            }
            $map[$expected] = $columnIndex;
        }

        // Optional columns — older templates (and CSVs without them) stay valid.
        foreach ([self::COLUMN_ROUND, self::COLUMN_PLAYOFF] as $optional) {
            $optionalIndex = array_search($optional, $normalised, true);
            if (false !== $optionalIndex) {
                $map[$optional] = $optionalIndex;
            }
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

    private function parsePlayoff(string $raw): ?bool
    {
        return match (mb_strtolower($raw)) {
            'ano', '1', 'true' => true,
            'ne', '0', 'false' => false,
            default => null,
        };
    }

    private function parseKickoff(string $raw): ?\DateTimeImmutable
    {
        // Times in the uploaded file are Czech local time; store them in UTC.
        $localTimezone = new \DateTimeZone('Europe/Prague');
        // ISO as in the template, plus the Czech „10. 5. 2026 18:00" a spreadsheet
        // shows (and hands over) for a date cell formatted the Czech way.
        $normalised = (string) preg_replace('/\s*\.\s*/', '.', $raw);

        foreach (['Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'j.n.Y H:i', 'j.n.Y H:i:s'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $normalised, $localTimezone);
            $warnings = \DateTimeImmutable::getLastErrors();

            // createFromFormat() silently rolls 2026-02-31 over to March — refuse it.
            if ($parsed instanceof \DateTimeImmutable && (false === $warnings || 0 === $warnings['warning_count'])) {
                return $parsed->setTimezone(new \DateTimeZone('UTC'));
            }
        }

        return null;
    }

    /**
     * Semicolon or tab when the header line uses it more than commas, else comma.
     * (PhpSpreadsheet's own inference also weighs „:", which every kickoff contains.).
     */
    private function sniffDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $firstLine = false !== $handle ? (string) fgets($handle) : '';

        if (false !== $handle) {
            fclose($handle);
        }

        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);

        return 0 === reset($counts) ? ',' : (string) array_key_first($counts);
    }
}
