<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging;

use App\Logging\ObjectSerializer;
use App\Logging\SerializeLogContextObjects;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SerializeLogContextObjectsTest extends TestCase
{
    public function testContextObjectsAreSerializedButThrowablesKept(): void
    {
        $processor = new SerializeLogContextObjects(new ObjectSerializer());
        $exception = new \RuntimeException('boom');
        $id = Uuid::v7();
        $competition = new class ($id) {
            public function __construct(
                // @phpstan-ignore property.onlyWritten (read via reflection in the serializer)
                private readonly Uuid $id,
            ) {
            }
        };

        $record = $processor(new LogRecord(
            datetime: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            channel: 'app',
            level: Level::Error,
            message: 'Something failed',
            context: [
                'exception' => $exception,
                'competition' => $competition,
                'plain' => 'string stays',
            ],
            extra: ['request_id' => $id],
        ));

        // Monolog/Sentry give Throwables special treatment — must stay objects.
        self::assertSame($exception, $record->context['exception']);
        self::assertSame(
            ['class' => $competition::class, 'id' => (string) $id],
            $record->context['competition'],
        );
        self::assertSame('string stays', $record->context['plain']);
        self::assertSame((string) $id, $record->extra['request_id']);
    }
}
