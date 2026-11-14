<?php

declare(strict_types=1);

namespace App\Command\PruneEmailLog;

/**
 * Deletes the e-mail audit log rows that outlived their retention
 * ({@see \App\Entity\EmailLog::RETENTION_DAYS}) — an address and a subject are
 * personal data, kept only as long as they help debugging.
 */
final readonly class PruneEmailLogCommand
{
}
