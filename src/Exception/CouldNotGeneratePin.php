<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(500)]
final class CouldNotGeneratePin extends \RuntimeException
{
    public static function afterExhaustion(): self
    {
        return new self('Nepodařilo se vygenerovat PIN, zkuste to prosím znovu.');
    }
}
