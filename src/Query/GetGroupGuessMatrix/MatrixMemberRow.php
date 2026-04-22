<?php

declare(strict_types=1);

namespace App\Query\GetGroupGuessMatrix;

use Symfony\Component\Uid\Uuid;

final readonly class MatrixMemberRow
{
    /**
     * @param array<string, MatrixCell> $cells keyed by sportMatchId RFC 4122 string
     */
    public function __construct(
        public Uuid $userId,
        public string $nickname,
        public ?string $fullName,
        public int $totalPoints,
        public int $rank,
        public array $cells,
    ) {
    }
}
