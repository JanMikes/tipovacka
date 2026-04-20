<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
final class InvalidScore extends \DomainException
{
    public static function negative(): self
    {
        return new self('Skóre nemůže být záporné.');
    }
}
