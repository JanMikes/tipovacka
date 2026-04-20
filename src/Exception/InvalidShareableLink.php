<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(404)]
final class InvalidShareableLink extends \DomainException
{
    public static function create(): self
    {
        return new self('Pozvánkový odkaz není platný.');
    }
}
