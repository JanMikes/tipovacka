<?php

declare(strict_types=1);

namespace App\Service\Email;

use Symfony\Component\Mime\HtmlToTextConverter\HtmlToTextConverterInterface;

/**
 * Makes the plain-text alternative of every mail out of its HTML — wired as
 * Twig's `mailer.html_to_text_converter`, so no mail needs a second template.
 *
 * Symfony's default is `strip_tags()`, which on a table-layout mail yields a
 * page of whitespace, raw `&nbsp;` entities and — the real loss — NO LINK: the
 * button's URL lives in an attribute. A text part that cannot be acted on is
 * worse than none (it is what a plain-text client and some spam filters read),
 * so this keeps what matters: every link is written out as „text: URL", blocks
 * become lines, and everything invisible (the <head>, Outlook's conditional
 * comments, the hidden preheader) is dropped.
 *
 * Regex-based on purpose: the input is always OUR template output — a known,
 * well-formed shape — not arbitrary HTML.
 */
final readonly class MailTextConverter implements HtmlToTextConverterInterface
{
    public function convert(string $html, string $charset): string
    {
        $text = (string) preg_replace(
            [
                '#<head\b.*?</head>#is',
                '#<(style|script)\b.*?</\1>#is',
                '#<!--.*?-->#s',
                // The inbox-preview line and its invisible padding.
                '#<div\b[^>]*aria-hidden="true"[^>]*>.*?</div>#is',
            ],
            '',
            $html,
        );

        // A link keeps its target: „Natipovat: https://…". A link whose text IS
        // the URL (the „tlačítko nefunguje?" fallback) is written once.
        $text = (string) preg_replace_callback(
            '#<a\b[^>]*\bhref="([^"]*)"[^>]*>(.*?)</a>#is',
            static function (array $match): string {
                $url = html_entity_decode($match[1], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
                $label = trim(strip_tags($match[2]));

                if ('' === $label) {
                    // An image link (the logo): its alt text is enough.
                    return $match[2];
                }

                return $label === $url || '' === $url ? $label : sprintf('%s: %s', $label, $url);
            },
            $text,
        );

        $text = (string) preg_replace('#<img\b[^>]*\balt="([^"]*)"[^>]*>#i', '$1', $text);

        // Block boundaries become line breaks; cells of one row stay on one line.
        $text = (string) preg_replace(
            ['#<br\s*/?>#i', '#</(h[1-6]|p|table)>#i', '#</(tr|div|li)>#i', '#</td>#i'],
            ["\n", "\n\n", "\n", ' '],
            $text,
        );

        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = str_replace(["\u{00A0}", "\u{200C}", "\r"], [' ', '', ''], $text);

        $lines = array_map(
            static fn (string $line): string => trim((string) preg_replace('/[ \t]+/', ' ', $line)),
            explode("\n", $text),
        );

        // A separator dot between two links is decoration, not content.
        $lines = array_filter($lines, static fn (string $line): bool => '·' !== $line);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)))."\n";
    }
}
