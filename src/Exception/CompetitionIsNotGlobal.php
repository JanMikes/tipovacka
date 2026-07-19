<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(422)]
final class CompetitionIsNotGlobal extends \DomainException
{
    public static function withId(Uuid $competitionId): self
    {
        return new self(sprintf('Soutěž "%s" není globální — připojit se přes vstupné nelze.', $competitionId->toRfc4122()));
    }
}
