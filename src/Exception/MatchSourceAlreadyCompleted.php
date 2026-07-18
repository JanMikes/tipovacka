<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(409)]
final class MatchSourceAlreadyCompleted extends \DomainException
{
    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Zdroj zápasů s ID "%s" je již ukončen.', $id->toRfc4122()));
    }
}
