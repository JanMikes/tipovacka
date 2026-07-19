<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(422)]
final class GlobalCompetitionRequiresCuratedSource extends \DomainException
{
    public static function forSource(Uuid $matchSourceId): self
    {
        return new self(sprintf('Globální soutěž lze založit jen nad veřejným (curated) zdrojem zápasů "%s".', $matchSourceId->toRfc4122()));
    }
}
