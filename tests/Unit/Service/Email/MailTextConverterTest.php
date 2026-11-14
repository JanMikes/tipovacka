<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Email;

use App\Service\Email\MailTextConverter;
use PHPUnit\Framework\TestCase;

final class MailTextConverterTest extends TestCase
{
    public function testLinksKeepTheirTarget(): void
    {
        $text = $this->convert('<p>Text</p><a href="https://wtips.cz/zapasy?a=1&amp;b=2">Natipovat</a>');

        self::assertStringContainsString('Natipovat: https://wtips.cz/zapasy?a=1&b=2', $text);
    }

    public function testALinkWhoseTextIsItsUrlIsWrittenOnce(): void
    {
        $text = $this->convert('<a href="https://wtips.cz/x">https://wtips.cz/x</a>');

        self::assertSame("https://wtips.cz/x\n", $text);
    }

    public function testInvisiblePartsAreDropped(): void
    {
        $text = $this->convert(
            '<html><head><title>Titulek</title><style>p { color: red; }</style></head><body>'
            .'<!--[if mso]><table><tr><td>outlook<![endif]-->'
            .'<div style="display:none" aria-hidden="true">preheader &zwnj;&nbsp;</div>'
            .'<p>Viditelné</p></body></html>',
        );

        self::assertSame("Viditelné\n", $text);
    }

    public function testBlocksBecomeLinesAndCellsOfARowShareOne(): void
    {
        $text = $this->convert(
            '<h1>Nadpis</h1><table><tr><td>Připsáno</td><td>+200 kreditů</td></tr><tr><td>Zůstatek</td><td>245&nbsp;kreditů</td></tr></table>',
        );

        self::assertSame("Nadpis\n\nPřipsáno +200 kreditů\nZůstatek 245 kreditů\n", $text);
    }

    public function testAnImageLinkLeavesItsAltText(): void
    {
        $text = $this->convert('<table><tr><td><a href="https://wtips.cz/"><img src="logo.png" alt="Wtips"></a></td></tr></table><p>Ahoj</p>');

        self::assertSame("Wtips\n\nAhoj\n", $text);
    }

    private function convert(string $html): string
    {
        return (new MailTextConverter())->convert($html, 'utf-8');
    }
}
