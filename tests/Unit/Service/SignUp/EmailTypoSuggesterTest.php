<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\SignUp;

use App\Service\SignUp\EmailTypoSuggester;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTypoSuggesterTest extends TestCase
{
    #[DataProvider('typos')]
    public function testSuggestsTheIntendedDomain(string $typed, string $expected): void
    {
        self::assertSame($expected, (new EmailTypoSuggester())->suggest($typed));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function typos(): iterable
    {
        yield 'wrong tld' => ['tipujici@seznam.cy', 'tipujici@seznam.cz'];
        yield 'swapped letters' => ['tipujici@gmial.com', 'tipujici@gmail.com'];
        yield 'cz instead of com' => ['tipujici@gmail.cz', 'tipujici@gmail.com'];
        yield 'missing letter' => ['tipujici@sezam.cz', 'tipujici@seznam.cz'];
        yield 'uppercase and spaces' => ['  Tipujici@Seznam.Cy ', 'tipujici@seznam.cz'];
    }

    #[DataProvider('fine')]
    public function testLeavesCorrectOrUnknownAddressesAlone(string $typed): void
    {
        self::assertNull((new EmailTypoSuggester())->suggest($typed));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fine(): iterable
    {
        yield 'known domain' => ['tipujici@seznam.cz'];
        yield 'company domain' => ['tipujici@firma.example'];
        yield 'no at sign' => ['tipujici'];
        yield 'no domain yet' => ['tipujici@'];
        yield 'email.com is real' => ['tipujici@email.com'];
    }
}
