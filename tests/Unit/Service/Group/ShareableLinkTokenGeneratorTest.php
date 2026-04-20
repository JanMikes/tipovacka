<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Group;

use App\Service\Group\ShareableLinkTokenGenerator;
use PHPUnit\Framework\TestCase;

final class ShareableLinkTokenGeneratorTest extends TestCase
{
    public function testGenerates48CharHexToken(): void
    {
        $generator = new ShareableLinkTokenGenerator();
        $token = $generator->generate();

        self::assertSame(48, strlen($token));
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);
    }

    public function testGeneratesDifferentTokensOnEachCall(): void
    {
        $generator = new ShareableLinkTokenGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }
}
