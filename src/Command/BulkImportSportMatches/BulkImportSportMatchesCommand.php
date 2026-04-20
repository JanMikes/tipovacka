<?php

declare(strict_types=1);

namespace App\Command\BulkImportSportMatches;

use App\Service\SportMatch\SportMatchImportRow;
use Symfony\Component\Uid\Uuid;

final readonly class BulkImportSportMatchesCommand
{
    /**
     * @param list<SportMatchImportRow> $rows
     */
    public function __construct(
        public Uuid $tournamentId,
        public Uuid $editorId,
        public array $rows,
    ) {
    }
}
