<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
final class InvalidGuessScore extends \DomainException
{
    public static function create(): self
    {
        return new self('Skóre musí být 0 nebo vyšší.');
    }
}
