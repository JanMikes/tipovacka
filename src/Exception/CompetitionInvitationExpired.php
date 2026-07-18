<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(410)]
final class CompetitionInvitationExpired extends \DomainException
{
    public static function create(): self
    {
        return new self('Platnost pozvánky vypršela.');
    }
}
