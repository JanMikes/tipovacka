<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * A waiting note was submitted without a „tipování otevřeno od" moment. The note
 * is shown only while a match waits, so without an opening it would never be
 * seen — rejected instead of silently dropping the text somebody typed.
 */
#[WithHttpStatus(409)]
final class CompetitionMatchOpeningNoteWithoutTime extends \DomainException
{
    public static function create(): self
    {
        return new self('Text pro čekání se zobrazuje jen do otevření tipování — vyplňte i čas otevření, nebo text smažte.');
    }
}
