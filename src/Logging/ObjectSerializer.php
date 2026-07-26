<?php

declare(strict_types=1);

namespace App\Logging;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Proxy;

/**
 * Generic, dependency-free-ish serializer turning arbitrary values into
 * scalars/arrays safe for structured logging (Sentry, JSON stderr). Objects
 * come out as ['class' => FQCN, <property> => <value>, …] via reflection —
 * including private and inherited properties — so a logged entity shows its id
 * and data instead of an opaque "Object App\Entity\Competition".
 *
 * Depth and item caps keep pathological graphs (EntityManager & friends)
 * bounded; Doctrine lazy proxies/collections are described without triggering
 * a database load.
 */
final class ObjectSerializer
{
    private const int MAX_DEPTH = 3;
    private const int MAX_ARRAY_ITEMS = 20;

    public function serialize(mixed $value): mixed
    {
        return $this->doSerialize($value, 0);
    }

    private function doSerialize(mixed $value, int $depth): mixed
    {
        if (null === $value || \is_scalar($value)) {
            return $value;
        }

        if (\is_array($value)) {
            return $this->serializeArray($value, $depth);
        }

        if (\is_object($value)) {
            return $this->serializeObject($value, $depth);
        }

        return get_debug_type($value);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function serializeArray(array $values, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return [sprintf('array(%d)', \count($values))];
        }

        $result = [];
        $count = 0;

        foreach ($values as $key => $item) {
            if (++$count > self::MAX_ARRAY_ITEMS) {
                $result['…'] = sprintf('+%d more items', \count($values) - self::MAX_ARRAY_ITEMS);

                break;
            }

            $result[$key] = $this->doSerialize($item, $depth + 1);
        }

        return $result;
    }

    /**
     * @return array<array-key, mixed>|string
     */
    private function serializeObject(object $value, int $depth): array|string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof \UnitEnum) {
            return $value::class.'::'.$value->name;
        }

        if ($value instanceof \Throwable) {
            return [
                'class' => $value::class,
                'message' => $value->getMessage(),
                'code' => $value->getCode(),
            ];
        }

        if ($value instanceof \Closure) {
            return \Closure::class;
        }

        // Doctrine collections: show loaded elements, never trigger a lazy load.
        if ($value instanceof Collection) {
            if ($value instanceof AbstractLazyCollection && !$value->isInitialized()) {
                return $value::class.' (uninitialized)';
            }

            return $this->doSerialize($value->toArray(), $depth);
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if ($depth >= self::MAX_DEPTH) {
            return $value::class;
        }

        $reflection = new \ReflectionObject($value);

        // Uninitialized lazy objects (Doctrine native lazy proxies): reading
        // properties would trigger initialization — possibly a DB query — from
        // inside a log processor. Describe, don't touch.
        if ($reflection->isUninitializedLazyObject($value)) {
            return $value::class.' (uninitialized)';
        }

        if ($value instanceof Proxy && !$value->__isInitialized()) {
            return (get_parent_class($value) ?: $value::class).' (uninitialized proxy)';
        }

        $properties = ['class' => $value::class];

        $class = $reflection;
        do {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic() || \array_key_exists($property->name, $properties)) {
                    continue;
                }

                try {
                    $properties[$property->name] = $property->isInitialized($value)
                        ? $this->doSerialize($property->getValue($value), $depth + 1)
                        : '(uninitialized)';
                } catch (\Throwable) {
                    $properties[$property->name] = '(unreadable)';
                }
            }

            $class = $class->getParentClass();
        } while (false !== $class);

        return $properties;
    }
}
