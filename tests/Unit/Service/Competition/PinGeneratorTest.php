<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Competition;

use App\Exception\CouldNotGeneratePin;
use App\Repository\CompetitionRepository;
use App\Service\Competition\PinGenerator;
use PHPUnit\Framework\TestCase;

final class PinGeneratorTest extends TestCase
{
    public function testGeneratesEightDigitPin(): void
    {
        $repository = $this->createStub(CompetitionRepository::class);
        $repository->method('pinExists')->willReturn(false);

        $pin = (new PinGenerator($repository))->generate();

        self::assertMatchesRegularExpression('/^\d{8}$/', $pin);
    }

    public function testRetriesUntilUnique(): void
    {
        $repository = $this->createStub(CompetitionRepository::class);
        $repository
            ->method('pinExists')
            ->willReturnOnConsecutiveCalls(true, true, false);

        $pin = (new PinGenerator($repository))->generate();

        self::assertMatchesRegularExpression('/^\d{8}$/', $pin);
    }

    public function testThrowsAfterTooManyAttempts(): void
    {
        $repository = $this->createStub(CompetitionRepository::class);
        $repository->method('pinExists')->willReturn(true);

        $this->expectException(CouldNotGeneratePin::class);

        (new PinGenerator($repository))->generate();
    }
}
