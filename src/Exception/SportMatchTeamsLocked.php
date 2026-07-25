<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class SportMatchTeamsLocked extends \DomainException
{
    public static function create(): self
    {
        return new self('Tým zápasu nelze změnit — k zápasu už jsou zapsaní střelci nebo karty. Nejprve je smažte. (Přejmenovat tým můžete v adresáři týmů.)');
    }
}
