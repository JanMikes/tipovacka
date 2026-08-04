<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A feed payload (file or API response) does not match the expected shape.
 * Console-surface only — feeds have no HTTP entry point, hence no status attribute.
 */
final class FeedPayloadInvalid extends \DomainException
{
    public static function unreadableFile(string $path): self
    {
        return new self(sprintf('Feed fixture file "%s" cannot be read.', $path));
    }

    public static function notJson(string $path, string $error): self
    {
        return new self(sprintf('Feed fixture file "%s" is not valid JSON: %s', $path, $error));
    }

    public static function invalidRow(int $index, string $problem): self
    {
        return new self(sprintf('Feed payload row #%d is invalid: %s', $index, $problem));
    }
}
