<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Competition;

use App\Service\Competition\InvitationTokenGenerator;
use PHPUnit\Framework\TestCase;

final class InvitationTokenGeneratorTest extends TestCase
{
    public function testGeneratesHexStringOfLength64(): void
    {
        $generator = new InvitationTokenGenerator();

        $token = $generator->generate();

        self::assertSame(64, strlen($token));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testTokensAreUnique(): void
    {
        $generator = new InvitationTokenGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }
}
