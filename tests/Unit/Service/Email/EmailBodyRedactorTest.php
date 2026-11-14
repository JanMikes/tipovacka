<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Email;

use App\Service\Email\EmailBodyRedactor;
use PHPUnit\Framework\TestCase;

final class EmailBodyRedactorTest extends TestCase
{
    public function testQueryStringsAreDropped(): void
    {
        self::assertSame(
            'Potvrdit e-mail: https://wtips.cz/overit-email?…',
            EmailBodyRedactor::redact('Potvrdit e-mail: https://wtips.cz/overit-email?expires=1794000000&id=0198c0de&signature=Zk3VbQ2n8sT1xYpL0aRmC4dE&token=q9W8e7R6'),
        );
    }

    public function testLongPathSegmentsAreMasked(): void
    {
        self::assertSame(
            'https://wtips.cz/reset-hesla/token/…',
            EmailBodyRedactor::redact('https://wtips.cz/reset-hesla/token/8f3a1c0e5b7d49a2b6c1e0f9d8a7b6c5d4e3f2a1'),
        );
        self::assertSame(
            'https://wtips.cz/pozvanka/…',
            EmailBodyRedactor::redact('https://wtips.cz/pozvanka/'.str_repeat('ab', 32)),
        );
        // The unsubscribe token never expires — it must not be kept either.
        self::assertSame(
            'https://wtips.cz/e-maily/…?…',
            EmailBodyRedactor::redact('https://wtips.cz/e-maily/AZMzMwAAcACAAAAAAAAAmQ.VYtUtVjjrq-iJNKdeCGloA?co=typ-guess_reminder'),
        );
    }

    public function testAUuidStaysBecauseItNamesASoutezNotASecret(): void
    {
        $url = 'https://wtips.cz/souteze/019bbbbb-0000-7000-8000-000000000001';

        self::assertSame($url, EmailBodyRedactor::redact($url));
    }

    public function testOrdinaryLinksAndSurroundingTextAreUntouched(): void
    {
        $text = "Natipovat: https://wtips.cz/zapasy\nTlačítko nefunguje? Otevřete https://wtips.cz/kredity.";

        self::assertSame($text, EmailBodyRedactor::redact($text));
    }

    public function testEveryLinkInATextIsHandled(): void
    {
        $redacted = EmailBodyRedactor::redact(
            'A: https://wtips.cz/a/'.str_repeat('x', 40).' B: http://localhost:8080/b?c=d, C: https://wtips.cz/zapasy',
        );

        self::assertSame('A: https://wtips.cz/a/… B: http://localhost:8080/b?…, C: https://wtips.cz/zapasy', $redacted);
    }
}
