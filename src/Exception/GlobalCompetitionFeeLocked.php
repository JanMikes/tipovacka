<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

/**
 * Raised when an admin tries to change a global competition's entry fee or
 * monetization after the first non-owner member has already joined — players
 * joined under the advertised terms, so the terms are then locked.
 */
#[WithHttpStatus(409)]
final class GlobalCompetitionFeeLocked extends \DomainException
{
    public static function withId(Uuid $competitionId): self
    {
        return new self(sprintf('Vstupné soutěže "%s" už nelze změnit — připojil se první hráč.', $competitionId->toRfc4122()));
    }
}
