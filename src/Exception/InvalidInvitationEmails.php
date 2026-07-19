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
        return new self(sprintf('Neplatné e-mailové adresy: %s.', implode(', ', $emails)));
    }
}
