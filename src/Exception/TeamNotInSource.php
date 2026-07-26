<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(422)]
final class TeamNotInSource extends \DomainException
{
    public static function create(Uuid $teamId, Uuid $matchSourceId): self
    {
        return new self(sprintf(
            'Tým "%s" nepatří do zdroje zápasů "%s".',
            $teamId->toRfc4122(),
            $matchSourceId->toRfc4122(),
        ));
    }
}
