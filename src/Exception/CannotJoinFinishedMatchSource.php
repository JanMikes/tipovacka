<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(409)]
final class CannotJoinFinishedMatchSource extends \DomainException
{
    public static function forCompetition(Uuid $competitionId): self
    {
        return new self(sprintf('Nelze se připojit k soutěži "%s" — zdroj zápasů je již ukončen.', $competitionId->toRfc4122()));
    }
}
