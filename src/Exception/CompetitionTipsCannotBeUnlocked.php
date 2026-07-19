<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class CompetitionTipsCannotBeUnlocked extends \DomainException
{
    public static function afterCompetitionStart(): self
    {
        return new self('Tipy už nelze odemknout, soutěž již začala.');
    }
}
