<?php

declare(strict_types=1);

namespace App\Tests\Unit\Value;

use App\Enum\NotificationType;
use App\Value\UnsubscribeScope;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UnsubscribeScopeTest extends TestCase
{
    public function testEveryScopeRoundTripsThroughItsSlug(): void
    {
        $competitionId = Uuid::fromString('019bbbbb-0000-7000-8000-000000000001');

        self::assertTrue(UnsubscribeScope::fromSlug(UnsubscribeScope::all()->toSlug())?->isAll);
        self::assertSame(
            NotificationType::GuessReminder,
            UnsubscribeScope::fromSlug(UnsubscribeScope::type(NotificationType::GuessReminder)->toSlug())?->type,
        );
        self::assertSame(
            $competitionId->toRfc4122(),
            UnsubscribeScope::fromSlug(UnsubscribeScope::competition($competitionId)->toSlug())?->competitionId?->toRfc4122(),
        );
    }

    public function testEveryNotificationTypeHasASlug(): void
    {
        foreach (NotificationType::cases() as $type) {
            self::assertSame($type, UnsubscribeScope::fromSlug('typ-'.$type->value)?->type);
        }
    }

    public function testGarbageIsNoScope(): void
    {
        foreach (['', 'all', 'typ-', 'typ-nope', 'soutez-not-a-uuid', 'soutez-'] as $slug) {
            self::assertNull(UnsubscribeScope::fromSlug($slug), $slug);
        }
    }
}
