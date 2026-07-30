<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * „Tipování otevřeno od" was set at or after the moment tipping CLOSES for that
 * match — a window nobody could ever tip in. Rejected at write time rather than
 * stored, so a match is never silently untippable.
 */
#[WithHttpStatus(409)]
final class CompetitionMatchOpeningAfterDeadline extends \DomainException
{
    public static function at(\DateTimeImmutable $deadline): self
    {
        return new self(sprintf(
            'Otevření tipování musí být dřív než uzávěrka tohoto zápasu (%s), jinak by zápas nešlo tipovat vůbec.',
            $deadline->setTimezone(new \DateTimeZone('Europe/Prague'))->format('j. n. Y H:i'),
        ));
    }
}
