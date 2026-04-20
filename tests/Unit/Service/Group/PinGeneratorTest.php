<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Group;

use App\Exception\CouldNotGeneratePin;
use App\Repository\GroupRepository;
use App\Service\Group\PinGenerator;
use PHPUnit\Framework\TestCase;

final class PinGeneratorTest extends TestCase
{
    public function testGeneratesEightDigitPin(): void
    {
        $repository = $this->createStub(GroupRepository::class);
        $repository->method('pinExists')->willReturn(false);

        $pin = (new PinGenerator($repository))->generate();

        self::assertMatchesRegularExpression('/^\d{8}$/', $pin);
    }

    public function testRetriesUntilUnique(): void
    {
        $repository = $this->createStub(GroupRepository::class);
        $repository
            ->method('pinExists')
            ->willReturnOnConsecutiveCalls(true, true, false);

        $pin = (new PinGenerator($repository))->generate();

        self::assertMatchesRegularExpression('/^\d{8}$/', $pin);
    }

    public function testThrowsAfterTooManyAttempts(): void
    {
        $repository = $this->createStub(GroupRepository::class);
        $repository->method('pinExists')->willReturn(true);

        $this->expectException(CouldNotGeneratePin::class);

        (new PinGenerator($repository))->generate();
    }
}
