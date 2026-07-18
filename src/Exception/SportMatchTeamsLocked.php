<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class SportMatchTeamsLocked extends \DomainException
{
    public static function create(): self
    {
        return new self('Název týmu nelze změnit — k zápasu už jsou zapsané události. Nejprve smažte střelce/karty.');
    }
}
