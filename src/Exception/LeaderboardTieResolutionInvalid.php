<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
final class LeaderboardTieResolutionInvalid extends \DomainException
{
    public static function notTied(): self
    {
        return new self('Mezi vybranými uživateli není shoda bodů, rozřazení nelze uložit.');
    }
}
