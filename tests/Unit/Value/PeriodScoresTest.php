<?php

declare(strict_types=1);

namespace App\Tests\Unit\Value;

use App\Exception\InvalidScore;
use App\Value\PeriodScores;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeriodScoresTest extends TestCase
{
    public function testFromArrayValidatesAndExposesPairs(): void
    {
        $scores = PeriodScores::fromArray([[1, 0], [2, 3]]);

        self::assertCount(2, $scores);
        self::assertSame([[1, 0], [2, 3]], $scores->toArray());
        self::assertSame(3, $scores->sumHome());
        self::assertSame(3, $scores->sumAway());
        self::assertSame(1, $scores->homeAt(0));
        self::assertSame(0, $scores->awayAt(0));
        self::assertSame(2, $scores->homeAt(1));
        self::assertSame(3, $scores->awayAt(1));
    }

    public function testFromArrayReindexesKeys(): void
    {
        $scores = PeriodScores::fromArray([2 => [1, 1], 5 => [0, 0]]);

        self::assertSame([[1, 1], [0, 0]], $scores->toArray());
    }

    public function testFromNullableArrayReturnsNullForNull(): void
    {
        self::assertNull(PeriodScores::fromNullableArray(null));
        self::assertInstanceOf(PeriodScores::class, PeriodScores::fromNullableArray([[0, 0]]));
    }

    public function testEmptyListIsRejected(): void
    {
        $this->expectException(InvalidScore::class);
        PeriodScores::fromArray([]);
    }

    public function testNegativeValueIsRejected(): void
    {
        $this->expectException(InvalidScore::class);
        $this->expectExceptionMessage('Skóre nemůže být záporné.');
        PeriodScores::fromArray([[1, -1]]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidPairShapes(): iterable
    {
        yield 'single value' => [[[1]]];
        yield 'three values' => [[[1, 2, 3]]];
        yield 'not an array' => [[1]];
        yield 'string values' => [[['1', '0']]];
        yield 'float values' => [[[1.5, 0]]];
        yield 'associative pair' => [[['home' => 1, 'away' => 0]]];
    }

    #[DataProvider('invalidPairShapes')]
    public function testInvalidPairShapeIsRejected(mixed $pairs): void
    {
        $this->expectException(InvalidScore::class);
        \assert(is_array($pairs));
        PeriodScores::fromArray($pairs);
    }

    public function testOutOfRangeIndexThrows(): void
    {
        $scores = PeriodScores::fromArray([[1, 0]]);

        $this->expectException(\OutOfRangeException::class);
        $scores->homeAt(1);
    }
}
