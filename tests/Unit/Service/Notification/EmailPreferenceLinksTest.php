<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification;

use App\Service\Notification\EmailPreferenceLinks;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class EmailPreferenceLinksTest extends TestCase
{
    private const string USER = '01933333-0000-7000-8000-000000000002';

    public function testTokenRoundTripsToTheUserId(): void
    {
        $links = $this->links('secret');
        $token = $links->tokenFor(Uuid::fromString(self::USER));

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22}\.[A-Za-z0-9_-]{22}$/', $token, 'URL-safe, no padding');
        self::assertSame(self::USER, $links->userIdFrom($token)?->toRfc4122());
    }

    public function testTokenIsStableSoALinkInAnOldMailKeepsWorking(): void
    {
        self::assertSame(
            $this->links('secret')->tokenFor(Uuid::fromString(self::USER)),
            $this->links('secret')->tokenFor(Uuid::fromString(self::USER)),
        );
    }

    public function testATokenSignedWithAnotherSecretIsRejected(): void
    {
        $token = $this->links('other-secret')->tokenFor(Uuid::fromString(self::USER));

        self::assertNull($this->links('secret')->userIdFrom($token));
    }

    public function testASignatureCannotBeMovedToAnotherUser(): void
    {
        $links = $this->links('secret');
        $mine = $links->tokenFor(Uuid::fromString(self::USER));
        $theirs = $links->tokenFor(Uuid::fromString('01933333-0000-7000-8000-000000000099'));

        $forged = explode('.', $theirs)[0].'.'.explode('.', $mine)[1];

        self::assertNull($links->userIdFrom($forged));
    }

    public function testMalformedTokensAreRejectedNotThrownOn(): void
    {
        $links = $this->links('secret');

        foreach (['', 'nodot', 'a.b', 'a.b.c', '!!!.???', str_repeat('A', 22).'.'.str_repeat('A', 22)] as $garbage) {
            self::assertNull($links->userIdFrom($garbage), $garbage);
        }
    }

    private function links(string $secret): EmailPreferenceLinks
    {
        return new EmailPreferenceLinks($secret, $this->createStub(UrlGeneratorInterface::class));
    }
}
