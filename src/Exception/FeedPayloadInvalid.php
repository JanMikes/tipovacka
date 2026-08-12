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

    public static function missingFeedRef(string $provider, string $expected): self
    {
        return new self(sprintf('%s source has no feedRef — expected a %s.', $provider, $expected));
    }

    public static function missingCredentials(string $provider, string $variable): self
    {
        return new self(sprintf('%s needs the %s environment variable to be set.', $provider, $variable));
    }

    public static function requestFailed(string $provider, string $error): self
    {
        return new self(sprintf('%s request failed: %s', $provider, $error));
    }

    /**
     * A 200 that contains no fixtures at all. Almost always a wrong feedRef, and
     * worth failing on: a silent empty payload reads as „nothing changed".
     */
    public static function emptyResponse(string $provider, string $feedRef): self
    {
        return new self(sprintf('%s returned no matches for feedRef "%s" — wrong reference?', $provider, $feedRef));
    }

    /**
     * The response parsed, but not a single row survived to a snapshot — same
     * danger as an empty response, wearing a well-formed envelope.
     */
    public static function unusableRows(string $provider, string $problem): self
    {
        return new self(sprintf('%s payload has no usable rows: %s', $provider, $problem));
    }
}
