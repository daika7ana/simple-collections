<?php

declare(strict_types=1);

namespace Daika7ana\SimpleCollections\Concerns;

use Closure;
use InvalidArgumentException;

/**
 * Shared `where()` comparison logic for Collection and LazyCollection.
 *
 * @internal
 */
trait ComparesWhere
{
    /**
     * @param  int|string|callable(mixed, int|string): mixed  $key
     * @return Closure(mixed, array-key): bool
     */
    private static function wherePredicate(int|string|callable $key, int $argumentCount, mixed $operator, mixed $value): Closure
    {
        if ($argumentCount === 1) {
            [$operator, $value] = ['=', true];
        } elseif ($argumentCount === 2) {
            [$operator, $value] = ['=', $operator];
        }

        return static function (mixed $item, int|string $itemKey) use ($key, $operator, $value): bool {
            if (is_int($key) || is_string($key)) {
                if (! self::hasValue($item, $key)) {
                    return false;
                }

                $retrieved = self::valueFrom($item, $key);
            } else {
                $retrieved = $key($item, $itemKey);
            }

            if ($value instanceof Closure) {
                return (bool) $value($retrieved);
            }

            return match ($operator) {
                '=', '==' => $retrieved == $value,
                '===' => $retrieved === $value,
                '!=', '<>' => $retrieved != $value,
                '!==' => $retrieved !== $value,
                '<' => $retrieved < $value,
                '<=' => $retrieved <= $value,
                '>' => $retrieved > $value,
                '>=' => $retrieved >= $value,
                default => throw new InvalidArgumentException(
                    'Unsupported where operator ['.get_debug_type($operator).'].'
                ),
            };
        };
    }

    private static function valueFrom(mixed $item, int|string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        return is_object($item) ? ($item->{$key} ?? null) : null;
    }

    private static function hasValue(mixed $item, int|string $key): bool
    {
        if (is_array($item)) {
            return array_key_exists($key, $item);
        }

        return is_object($item) && (property_exists($item, (string) $key) || isset($item->{$key}));
    }
}
