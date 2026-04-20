<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
final class SportMatchImportFailed extends \RuntimeException
{
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
