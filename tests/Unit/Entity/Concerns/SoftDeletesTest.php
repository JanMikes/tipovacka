<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Concerns;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use PHPUnit\Framework\TestCase;

final class SoftDeletesTest extends TestCase
{
    private SoftDeletable $subject;

    protected function setUp(): void
    {
        $this->subject = new class () implements SoftDeletable {
            use SoftDeletes;
        };
    }

    public function testIsNotDeletedInitially(): void
    {
        self::assertFalse($this->subject->isDeleted());
        self::assertNull($this->subject->deletedAt);
    }

    public function testMarkDeletedSetsTimestamp(): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $this->subject->markDeleted($now);

        self::assertTrue($this->subject->isDeleted());
        self::assertSame($now, $this->subject->deletedAt);
    }

    public function testMarkDeletedOverwritesOnSecondCall(): void
    {
        $first = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $second = new \DateTimeImmutable('2025-06-16 12:00:00 UTC');

        $this->subject->markDeleted($first);
        $this->subject->markDeleted($second);

        self::assertSame($second, $this->subject->deletedAt);
    }
}
