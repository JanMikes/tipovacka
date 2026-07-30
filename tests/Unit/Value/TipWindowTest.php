<?php

declare(strict_types=1);

namespace App\Tests\Unit\Value;

use App\Value\TipWindow;
use PHPUnit\Framework\TestCase;

final class TipWindowTest extends TestCase
{
    public function testWithoutOpeningTheWindowIsOpenUntilTheDeadline(): void
    {
        $window = new TipWindow(deadline: new \DateTimeImmutable('2025-06-20 18:00'));

        self::assertTrue($window->isOpen(new \DateTimeImmutable('2025-06-15 12:00')));
        self::assertFalse($window->isWaiting(new \DateTimeImmutable('2025-06-15 12:00')));
        self::assertFalse($window->isClosed(new \DateTimeImmutable('2025-06-15 12:00')));
    }

    public function testTheDeadlineItselfIsAlreadyClosed(): void
    {
        $deadline = new \DateTimeImmutable('2025-06-20 18:00');
        $window = new TipWindow(deadline: $deadline);

        self::assertTrue($window->isClosed($deadline));
        self::assertFalse($window->isOpen($deadline));
        self::assertFalse($window->isOpen($deadline->modify('+1 second')));
    }

    public function testBeforeTheOpeningTheWindowIsWaiting(): void
    {
        $window = new TipWindow(
            deadline: new \DateTimeImmutable('2025-06-20 18:00'),
            opensAt: new \DateTimeImmutable('2025-06-18 09:00'),
            openingNote: 'Po losu skupin.',
        );

        $before = new \DateTimeImmutable('2025-06-17 23:59:59');

        self::assertTrue($window->isWaiting($before));
        self::assertFalse($window->isOpen($before));
        self::assertFalse($window->isClosed($before));
    }

    public function testTheOpeningMomentItselfIsAlreadyOpen(): void
    {
        $opensAt = new \DateTimeImmutable('2025-06-18 09:00');
        $window = new TipWindow(
            deadline: new \DateTimeImmutable('2025-06-20 18:00'),
            opensAt: $opensAt,
        );

        self::assertTrue($window->isOpen($opensAt));
        self::assertFalse($window->isWaiting($opensAt));
    }

    public function testAfterTheDeadlineTheWindowIsClosedNotWaiting(): void
    {
        $window = new TipWindow(
            deadline: new \DateTimeImmutable('2025-06-20 18:00'),
            opensAt: new \DateTimeImmutable('2025-06-18 09:00'),
        );

        $after = new \DateTimeImmutable('2025-06-21 10:00');

        self::assertTrue($window->isClosed($after));
        self::assertFalse($window->isWaiting($after));
        self::assertFalse($window->isOpen($after));
    }

    /**
     * A window that opens after it closes can only ever be untippable. It must
     * read as closed rather than as a „waiting" that never resolves — the write
     * side rejects the combination, but a kickoff moved EARLIER still produces it.
     */
    public function testDegenerateWindowNeverReportsOpen(): void
    {
        $window = new TipWindow(
            deadline: new \DateTimeImmutable('2025-06-18 12:00'),
            opensAt: new \DateTimeImmutable('2025-06-20 09:00'),
        );

        $beforeBoth = new \DateTimeImmutable('2025-06-17 09:00');
        $between = new \DateTimeImmutable('2025-06-19 09:00');
        $afterBoth = new \DateTimeImmutable('2025-06-21 09:00');

        self::assertTrue($window->isWaiting($beforeBoth));
        self::assertFalse($window->isOpen($beforeBoth));

        self::assertTrue($window->isClosed($between));
        self::assertFalse($window->isWaiting($between));
        self::assertFalse($window->isOpen($between));

        self::assertTrue($window->isClosed($afterBoth));
        self::assertFalse($window->isOpen($afterBoth));
    }
}
