<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * The match is listed and visible, but its tip window has not opened yet
 * („tipování otevřeno od", set per competition match by an admin).
 *
 * Thrown by every guess-writing handler, which is what makes the gate
 * bypass-proof: the UI hides the inputs, but a hand-crafted POST straight to a
 * tip endpoint lands here too. Applies to on-behalf writes as well — a manager
 * who needs the match open moves the opening moment, they do not tip around it.
 */
#[WithHttpStatus(409)]
final class GuessNotYetOpen extends \DomainException
{
    public static function until(\DateTimeImmutable $opensAt): self
    {
        return new self(sprintf(
            'Tipování tohoto zápasu začíná až %s, dřív tip uložit nelze.',
            $opensAt->setTimezone(new \DateTimeZone('Europe/Prague'))->format('j. n. Y H:i'),
        ));
    }
}
