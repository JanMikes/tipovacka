<?php

declare(strict_types=1);

namespace App\Service\Email;

/**
 * Makes a mail's text safe to keep in the audit log: every link is reduced to
 * WHERE it leads, without the part that makes it work.
 *
 * A password-reset link, a verification link, an invitation, an unsubscribe
 * link — each is a credential for as long as it is valid. The reset token is
 * stored hashed in its own table precisely so that the database cannot be used
 * to take over an account; a log that kept the mail verbatim would undo that.
 *
 * The rule is generic rather than a list of „sensitive mails", so a mail added
 * later is covered without anyone remembering to add it:
 *
 *  - the query string is dropped (signatures and tokens live there),
 *  - a path segment of 20+ characters is masked — unless it is a UUID, which
 *    names a soutěž or a match and is exactly what a debugger wants to see.
 */
final class EmailBodyRedactor
{
    private const int LONG_SEGMENT = 20;
    private const string MASK = '…';
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public static function redact(string $text): string
    {
        return (string) preg_replace_callback(
            '#https?://[^\s<>"\')\]]+#i',
            static fn (array $match): string => self::redactUrl($match[0]),
            $text,
        );
    }

    private static function redactUrl(string $url): string
    {
        // Sentence punctuation right after a link is not part of it.
        $trailing = '';

        if (1 === preg_match('/[.,;:!?]+$/', $url, $match)) {
            $trailing = $match[0];
            $url = substr($url, 0, -strlen($trailing));
        }

        $parts = parse_url($url);

        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return self::MASK.$trailing;
        }

        $segments = array_map(
            static fn (string $segment): string => strlen($segment) >= self::LONG_SEGMENT && 1 !== preg_match(self::UUID, $segment)
                ? self::MASK
                : $segment,
            explode('/', $parts['path'] ?? ''),
        );

        return sprintf(
            '%s://%s%s%s%s%s',
            $parts['scheme'],
            $parts['host'],
            isset($parts['port']) ? ':'.$parts['port'] : '',
            implode('/', $segments),
            isset($parts['query']) ? '?'.self::MASK : '',
            $trailing,
        );
    }
}
