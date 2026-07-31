<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * The uploaded file passed the form's Image constraint but could not be turned
 * into a stored logo — corrupt bytes, an exotic sub-format, or a storage failure.
 */
#[WithHttpStatus(422)]
final class TeamLogoNotProcessable extends \DomainException
{
    public static function undecodable(string $originalName): self
    {
        return new self(sprintf('Soubor "%s" se nepodařilo načíst jako obrázek.', $originalName));
    }

    public static function unencodable(): self
    {
        return new self('Obrázek se nepodařilo převést do formátu WebP.');
    }

    public static function unwritable(string $path, ?\Throwable $previous = null): self
    {
        return new self(sprintf('Logo se nepodařilo uložit jako "%s".', $path), previous: $previous);
    }
}
