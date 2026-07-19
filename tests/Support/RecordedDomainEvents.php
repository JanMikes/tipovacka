<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Test-only spy: registered as an event.bus handler (see config/services_test.php)
 * for the S10 premium domain events, which are recording-only in Part A (no prod
 * handler). Lets integration tests assert an event was actually dispatched through
 * the bus after the transaction committed — not just that DB state changed.
 *
 * @phpstan-type EventClass class-string
 */
final class RecordedDomainEvents implements ResetInterface
{
    /** @var list<object> */
    public array $events = [];

    public function record(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function ofType(string $class): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (object $event): bool => $event instanceof $class,
        ));
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
