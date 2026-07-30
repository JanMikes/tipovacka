<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CzechPlural;
use PHPUnit\Framework\TestCase;

/**
 * Item 36 — the missing-tips badge shortened to „Chybí N tipů" and needs the
 * genitive-plural („tipů") form for 5+. Dev fixtures only ever reach 3 missing
 * tips, so that form cannot be eyeballed on a real card — this proves it directly
 * against {@see CzechPlural::tipCount}, the ONE place the declension lives.
 */
final class CzechPluralTest extends TestCase
{
    public function testTipCountDeclinesLikeZapas(): void
    {
        self::assertSame('tip', CzechPlural::tipCount(1));
        self::assertSame('tipy', CzechPlural::tipCount(2));
        self::assertSame('tipy', CzechPlural::tipCount(3));
        self::assertSame('tipy', CzechPlural::tipCount(4));
        self::assertSame('tipů', CzechPlural::tipCount(5));
        self::assertSame('tipů', CzechPlural::tipCount(6));
        self::assertSame('tipů', CzechPlural::tipCount(11));
        self::assertSame('tipů', CzechPlural::tipCount(0));
    }

    /**
     * {@see CzechPlural::tip} is a DIFFERENT method for a different construction
     * („chybí vám tip/tipy na N zápasů" — nominative subject, never genitive) and
     * must keep its own two-way split even at 5+, where {@see CzechPlural::tipCount}
     * switches to „tipů".
     */
    public function testTipStaysTwoWayWhereTipCountBecomesGenitive(): void
    {
        self::assertSame('tipy', CzechPlural::tip(5));
        self::assertSame('tipů', CzechPlural::tipCount(5));
    }
}
