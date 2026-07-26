<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging;

use App\Enum\MatchSourceKind;
use App\Logging\ObjectSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Uid\Uuid;

final class ObjectSerializerTest extends TestCase
{
    private ObjectSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ObjectSerializer();
    }

    public function testScalarsAndNullPassThrough(): void
    {
        self::assertNull($this->serializer->serialize(null));
        self::assertSame(42, $this->serializer->serialize(42));
        self::assertSame('hello', $this->serializer->serialize('hello'));
        self::assertTrue($this->serializer->serialize(true));
    }

    public function testObjectSerializesToClassAndProperties(): void
    {
        $id = Uuid::v7();
        $object = new class ($id, 'Muj tip', 5) {
            public function __construct(
                // @phpstan-ignore property.onlyWritten (read via reflection in the serializer)
                private readonly Uuid $id,
                // @phpstan-ignore property.onlyWritten (read via reflection in the serializer)
                private readonly string $name,
                public int $count,
            ) {
            }
        };

        $serialized = $this->serializer->serialize($object);

        self::assertIsArray($serialized);
        self::assertSame($object::class, $serialized['class']);
        self::assertSame((string) $id, $serialized['id']);
        self::assertSame('Muj tip', $serialized['name']);
        self::assertSame(5, $serialized['count']);
    }

    public function testPrivateParentPropertiesAreIncluded(): void
    {
        $serialized = $this->serializer->serialize(new ChildFixture());

        self::assertIsArray($serialized);
        self::assertSame('from-parent', $serialized['parentSecret']);
        self::assertSame('from-child', $serialized['childValue']);
    }

    public function testStringableBecomesString(): void
    {
        $uuid = Uuid::v7();

        self::assertSame((string) $uuid, $this->serializer->serialize($uuid));
    }

    public function testDateTimeBecomesAtomString(): void
    {
        $date = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        self::assertSame('2025-06-15T12:00:00+00:00', $this->serializer->serialize($date));
    }

    public function testEnumShowsClassAndCase(): void
    {
        self::assertSame(MatchSourceKind::class.'::Curated', $this->serializer->serialize(MatchSourceKind::Curated));
    }

    public function testThrowableBecomesClassMessageCode(): void
    {
        $serialized = $this->serializer->serialize(new \RuntimeException('boom', 7));

        self::assertSame(
            ['class' => \RuntimeException::class, 'message' => 'boom', 'code' => 7],
            $serialized,
        );
    }

    public function testMessengerEnvelopeShowsInnerMessageData(): void
    {
        $envelope = new Envelope(new MessageFixture('reminder-sweep'));

        $serialized = $this->serializer->serialize($envelope);

        self::assertIsArray($serialized);
        self::assertSame(Envelope::class, $serialized['class']);
        self::assertIsArray($serialized['message']);
        self::assertSame(MessageFixture::class, $serialized['message']['class']);
        self::assertSame('reminder-sweep', $serialized['message']['payload']);
    }

    public function testDepthIsCapped(): void
    {
        $level4 = new ChildFixture();
        $level3 = new class ($level4) {
            public function __construct(public object $inner)
            {
            }
        };
        $level2 = new class ($level3) {
            public function __construct(public object $inner)
            {
            }
        };
        $level1 = new class ($level2) {
            public function __construct(public object $inner)
            {
            }
        };

        $serialized = $this->serializer->serialize($level1);

        self::assertIsArray($serialized);
        self::assertIsArray($serialized['inner']);
        self::assertIsArray($serialized['inner']['inner']);
        // At max depth the object collapses to its class name instead of recursing forever.
        self::assertSame($level4::class, $serialized['inner']['inner']['inner']);
    }

    public function testLargeArraysAreCapped(): void
    {
        $serialized = $this->serializer->serialize(range(1, 50));

        self::assertIsArray($serialized);
        self::assertCount(21, $serialized);
        self::assertSame('+30 more items', $serialized['…']);
    }

    public function testUninitializedPropertyDoesNotBreakSerialization(): void
    {
        $object = new class () {
            public string $set = 'value';
            public string $unset;
        };

        $serialized = $this->serializer->serialize($object);

        self::assertIsArray($serialized);
        self::assertSame('value', $serialized['set']);
        self::assertSame('(uninitialized)', $serialized['unset']);
    }
}

final class ChildFixture extends ParentFixture
{
    public string $childValue = 'from-child';
}

abstract class ParentFixture
{
    // @phpstan-ignore property.onlyWritten (read via reflection in the serializer)
    private string $parentSecret = 'from-parent';
}

final readonly class MessageFixture
{
    public function __construct(
        public string $payload,
    ) {
    }
}
