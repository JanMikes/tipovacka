<?php

declare(strict_types=1);

namespace App\Value;

use App\Exception\InvalidScore;

/**
 * Immutable list of per-period [home, away] score pairs (poločasy / třetiny).
 *
 * Stored on SportMatch as a plain JSON array of pairs; this value object guards
 * the shape: a non-empty list of exact [int, int] pairs with non-negative values.
 */
final readonly class PeriodScores implements \Countable
{
    /**
     * @param list<array{int, int}> $pairs
     */
    private function __construct(
        private array $pairs,
    ) {
    }

    /**
     * @param array<mixed> $pairs
     */
    public static function fromArray(array $pairs): self
    {
        if ([] === $pairs) {
            throw InvalidScore::emptyPeriods();
        }

        $validated = [];

        foreach (array_values($pairs) as $pair) {
            if (!is_array($pair) || 2 !== count($pair) || !array_is_list($pair)) {
                throw InvalidScore::invalidPeriodPair();
            }

            [$home, $away] = $pair;

            if (!is_int($home) || !is_int($away)) {
                throw InvalidScore::invalidPeriodPair();
            }

            if ($home < 0 || $away < 0) {
                throw InvalidScore::negative();
            }

            $validated[] = [$home, $away];
        }

        return new self($validated);
    }

    /**
     * @param array<mixed>|null $pairs
     */
    public static function fromNullableArray(?array $pairs): ?self
    {
        return null === $pairs ? null : self::fromArray($pairs);
    }

    /**
     * @return list<array{int, int}>
     */
    public function toArray(): array
    {
        return $this->pairs;
    }

    public function count(): int
    {
        return count($this->pairs);
    }

    public function sumHome(): int
    {
        return array_sum(array_column($this->pairs, 0));
    }

    public function sumAway(): int
    {
        return array_sum(array_column($this->pairs, 1));
    }

    /**
     * @param int $index zero-based period index
     */
    public function homeAt(int $index): int
    {
        return ($this->pairs[$index] ?? throw new \OutOfRangeException(sprintf('Period %d neexistuje.', $index)))[0];
    }

    /**
     * @param int $index zero-based period index
     */
    public function awayAt(int $index): int
    {
        return ($this->pairs[$index] ?? throw new \OutOfRangeException(sprintf('Period %d neexistuje.', $index)))[1];
    }
}
