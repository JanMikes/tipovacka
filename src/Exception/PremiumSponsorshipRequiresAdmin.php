<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(403)]
final class PremiumSponsorshipRequiresAdmin extends \DomainException
{
    public static function create(): self
    {
        return new self('Prémium na náš účet může přidělit jen administrátor.');
    }
}
