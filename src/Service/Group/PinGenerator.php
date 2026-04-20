<?php

declare(strict_types=1);

namespace App\Service\Group;

use App\Exception\CouldNotGeneratePin;
use App\Repository\GroupRepository;

final class PinGenerator
{
    private const int MAX_ATTEMPTS = 10;

    public function __construct(
        private readonly GroupRepository $groupRepository,
    ) {
    }

    public function generate(): string
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $pin = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);

            if (!$this->groupRepository->pinExists($pin)) {
                return $pin;
            }
        }

        throw CouldNotGeneratePin::afterExhaustion();
    }
}
