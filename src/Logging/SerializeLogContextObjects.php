<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Replaces objects in log context/extra with their serialized representation
 * (class + properties) BEFORE records reach the Sentry/stderr handlers, so an
 * event shows e.g. competition → {class, id, name, …} instead of
 * "Object App\Entity\Competition". Throwables are passed through untouched —
 * Monolog and Sentry give the 'exception' key special treatment (stack trace,
 * issue grouping).
 */
#[AsMonologProcessor]
final readonly class SerializeLogContextObjects implements ProcessorInterface
{
    public function __construct(
        private ObjectSerializer $serializer,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->serializeValues($record->context),
            extra: $this->serializeValues($record->extra),
        );
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function serializeValues(array $values): array
    {
        $serialized = [];

        foreach ($values as $key => $value) {
            $serialized[$key] = $value instanceof \Throwable ? $value : $this->serializer->serialize($value);
        }

        return $serialized;
    }
}
