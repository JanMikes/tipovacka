<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * Thrown by the strict invitation path (create-competition wizard) when any
 * supplied e-mail is malformed. Rolls back the whole competition-creation
 * transaction so a bad address never leaves an orphan source/competition.
 */
#[WithHttpStatus(422)]
final class InvalidInvitationEmails extends \DomainException
{
    /**
     * @param list<string> $emails
     */
    public static function withEmails(array $emails): self
    {
        return new self(sprintf(
            1 === count($emails)
                ? 'Adresa %s není platný e-mail. Opravte ji (zkontrolujte zavináč a doménu, např. jmeno@seznam.cz), nebo ji ze seznamu smažte.'
                : 'Tyto adresy nejsou platné e-maily: %s. Opravte je (zkontrolujte zavináč a doménu, např. jmeno@seznam.cz), nebo je ze seznamu smažte.',
            implode(', ', $emails),
        ));
    }
}
