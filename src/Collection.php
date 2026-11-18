<?php

declare(strict_types=1);

namespace Daika7ana\SimpleCollections;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Daika7ana\SimpleCollections\Concerns\ComparesWhere;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 *
 * @phpstan-consistent-constructor
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    use ComparesWhere;

    /**
     * @var array<array-key, TValue>
     */
    protected array $items;

    /**
     * @param  iterable<TKey, TValue>  $items
     */
    public function __construct(iterable $items = [])
    {
        $this->items = is_array($items) ? $items : iterator_to_array($items);
    }

    /**
     * @template TMakeKey of array-key
     * @template TMakeValue
     *
     * @param  iterable<TMakeKey, TMakeValue>  $items
     * @return static<TMakeKey, TMakeValue>
     */
    public static function make(iterable $items = []): static
    {
        /** @var static<TMakeKey, TMakeValue> $collection */
        $collection = new static($items);

        return $collection;
    }

    /**
     * Alias for make().
     *
     * @template TCollectKey of array-key
     * @template TCollectValue
     *
     * @param  iterable<TCollectKey, TCollectValue>  $items
     * @return static<TCollectKey, TCollectValue>
     */
    public static function collect(iterable $items = []): static
    {
        /** @var static<TCollectKey, TCollectValue> $collection */
        $collection = new static($items);

        return $collection;
    }

    /**
     * @return static<array-key, mixed>
     */
    public static function wrap(mixed $value): static
    {
        if ($value === null) {
            return new static([]);
        }

        if ($value instanceof self) {
            $value = $value->all();
        }

        /** @var static<array-key, mixed> $collection */
        $collection = new static(is_iterable($value) ? $value : [$value]);

        return $collection;
    }

    /**
     * @param  (callable(int): mixed)|null  $callback
     * @return static<int, mixed>
     */
    public static function times(int $number, ?callable $callback = null): static
    {
        $items = [];

        for ($index = 1; $index <= $number; $index++) {
            $items[] = $callback === null ? $index : $callback($index);
        }

        /** @var static<int, mixed> $collection */
        $collection = new static($items);

        return $collection;
    }

    /**
     * @return static<int, int>
     */
    public static function range(int $from, int $to, int $step = 1): static
    {
        /** @var static<int, int> $collection */
        $collection = new static(range($from, $to, $step));

        return $collection;
    }

    /**
     * @return array<array-key, TValue>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return array<array-key, TValue>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * @template TDefault
     *
     * @param  TDefault  $default
     * @return (TValue|TDefault|array<array-key, TValue>|null)
     */
    public function get(int|string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->items;
        }

        return array_key_exists($key, $this->items) ? $this->items[$key] : $default;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    /**
     * @param  int|string|array<int, int|string>  $keys
     */
    public function has(int|string|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $this->items)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alias for has(), kept for the ecolet SDK helper's name.
     */
    public function hasKey(int|string $key): bool
    {
        return $this->has($key);
    }

    public function contains(mixed $value, bool $strict = false): bool
    {
        if (is_callable($value) && ! is_string($value)) {
            foreach ($this->items as $key => $item) {
                if ($value($item, $key)) {
                    return true;
                }
            }

            return false;
        }

        return in_array($value, $this->items, $strict);
    }

    /**
     * @return static<int, TKey>
     */
    public function keys(): static
    {
        /** @var static<int, TKey> $collection */
        $collection = new static(array_keys($this->items));

        return $collection;
    }

    /**
     * @return static<int, TValue>
     */
    public function values(): static
    {
        /** @var static<int, TValue> $collection */
        $collection = new static(array_values($this->items));

        return $collection;
    }

    /**
     * @param  (callable(TValue, array-key): bool)|null  $callback
     */
    /**
     * @template TDefault
     *
     * @param  (callable(TValue, array-key): bool)|null  $callback
     * @param  TDefault  $default
     * @return (TValue|TDefault|null)
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return $this->items === [] ? $default : $this->items[array_key_first($this->items)];
        }

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * @param  (callable(TValue, array-key): bool)|null  $callback
     */
    /**
     * @template TDefault
     *
     * @param  (callable(TValue, array-key): bool)|null  $callback
     * @param  TDefault  $default
     * @return (TValue|TDefault|null)
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return $this->items === [] ? $default : $this->items[array_key_last($this->items)];
        }

        foreach (array_reverse($this->items, true) as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * @template TMapValue
     *
     * @param  callable(TValue, array-key): TMapValue  $callback
     * @return static<TKey, TMapValue>
     */
    public function map(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $key => $item) {
            $result[$key] = $callback($item, $key);
        }

        /** @var static<TKey, TMapValue> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @template TMapKey of array-key
     * @template TMapValue
     *
     * @param  callable(TValue, array-key): array<TMapKey, TMapValue>  $callback
     * @return static<TMapKey, TMapValue>
     */
    public function mapWithKeys(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $key => $item) {
            foreach ($callback($item, $key) as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        /** @var static<TMapKey, TMapValue> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @template TFlatValue
     *
     * @param  callable(TValue, array-key): iterable<TFlatValue>  $callback
     * @return static<int, TFlatValue>
     */
    public function flatMap(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $key => $item) {
            foreach ($callback($item, $key) as $value) {
                $result[] = $value;
            }
        }

        /** @var static<int, TFlatValue> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @param  (callable(TValue, array-key): bool)|null  $callback
     * @return static<TKey, TValue>
     */
    public function filter(?callable $callback = null): static
    {
        $result = $callback === null
            ? array_filter($this->items)
            : array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH);

        /** @var static<TKey, TValue> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @param  callable(TValue, array-key): bool  $callback
     * @return static<TKey, TValue>
     */
    public function reject(callable $callback): static
    {
        return $this->filter(static fn (mixed $item, int|string $key): bool => ! $callback($item, $key));
    }

    /**
     * Filter items by a key, an operator and a value, or by a callable key.
     *
     * `where($key)`, `where($key, $value)`, `where($key, $operator, $value)`.
     * Supported operators: =, ==, ===, !=, !==, <>, <, <=, >, >=.
     * A Closure value receives the retrieved value.
     *
     * @param  int|string|callable(mixed): mixed  $key
     * @return static<TKey, TValue>
     */
    public function where(int|string|callable $key, mixed $operator = null, mixed $value = null): static
    {
        $predicate = self::wherePredicate($key, func_num_args(), $operator, $value);

        return $this->filter(static fn (mixed $item, int|string $itemKey): bool => $predicate($item, $itemKey));
    }

    /**
     * @param  callable(TValue, array-key): mixed  $callback
     * @return $this
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * @return static<TKey, mixed>
     */
    public function pluck(int|string $value, int|string|null $keyBy = null): static
    {
        $result = [];

        foreach ($this->items as $key => $item) {
            $pluckedValue = self::valueFrom($item, $value);

            if ($keyBy === null) {
                $result[$key] = $pluckedValue;

                continue;
            }

            $pluckedKey = self::valueFrom($item, $keyBy);

            if (is_int($pluckedKey) || is_string($pluckedKey)) {
                $result[$pluckedKey] = $pluckedValue;
            } else {
                $result[$key] = $pluckedValue;
            }
        }

        /** @var static<TKey, mixed> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @param  int|string|callable(TValue, array-key): mixed  $keyBy
     * @return static<array-key, TValue>
     */
    public function keyBy(int|string|callable $keyBy): static
    {
        $result = [];

        foreach ($this->items as $key => $item) {
            $resolvedKey = is_int($keyBy) || is_string($keyBy)
                ? self::valueFrom($item, $keyBy)
                : $keyBy($item, $key);

            $resolvedKey = is_int($resolvedKey) || is_string($resolvedKey)
                ? $resolvedKey
                : (is_scalar($resolvedKey) ? (string) $resolvedKey : null);

            $result[$resolvedKey ?? $key] = $item;
        }

        /** @var static<array-key, TValue> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @param  int|string|callable(TValue, array-key): mixed  $groupBy
     * @return static<array-key, Collection<int, TValue>>
     */
    public function groupBy(int|string|callable $groupBy): static
    {
        /** @var array<int|string, list<TValue>> $grouped */
        $grouped = [];

        foreach ($this->items as $key => $item) {
            $groupKey = is_int($groupBy) || is_string($groupBy)
                ? self::valueFrom($item, $groupBy)
                : $groupBy($item, $key);

            if (! is_int($groupKey) && ! is_string($groupKey)) {
                $groupKey = is_scalar($groupKey) ? (string) $groupKey : '';
            }

            $grouped[$groupKey][] = $item;
        }

        $result = [];

        foreach ($grouped as $groupKey => $groupItems) {
            $result[$groupKey] = new static($groupItems);
        }

        /** @var static<array-key, Collection<int, TValue>> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @return static<TKey, TValue>
     */
    public function sort(int $options = SORT_REGULAR): static
    {
        $items = $this->items;

        asort($items, $options);

        /** @var static<TKey, TValue> $collection */
        $collection = new static($items);

        return $collection;
    }

    /**
     * @return static<TKey, TValue>
     */
    public function sortDesc(int $options = SORT_REGULAR): static
    {
        $items = $this->items;

        arsort($items, $options);

        /** @var static<TKey, TValue> $collection */
        $collection = new static($items);

        return $collection;
    }

    /**
     * @param  int|string|callable(TValue, array-key): mixed  $callback
     * @return static<TKey, TValue>
     */
    public function sortBy(int|string|callable $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $sortable = [];

        foreach ($this->items as $key => $item) {
            $sortable[$key] = is_int($callback) || is_string($callback)
                ? self::valueFrom($item, $callback)
                : $callback($item, $key);
        }

        $descending ? arsort($sortable, $options) : asort($sortable, $options);

        $items = [];

        foreach ($sortable as $key => $sortValue) {
            $items[$key] = $this->items[$key];
        }

        /** @var static<TKey, TValue> $collection */
        $collection = new static($items);

        return $collection;
    }

    /**
     * @param  int|string|callable(TValue, array-key): mixed  $callback
     * @return static<TKey, TValue>
     */
    public function sortByDesc(int|string|callable $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * @return static<TKey, TValue>
     */
    public function reverse(): static
    {
        /** @var static<TKey, TValue> $collection */
        $collection = new static(array_reverse($this->items, true));

        return $collection;
    }

    /**
     * @param  int|string|callable(TValue, array-key): mixed|null  $key
     * @return static<TKey, TValue>
     */
    public function unique(int|string|callable|null $key = null, bool $strict = false): static
    {
        $result = [];
        $seen = [];

        foreach ($this->items as $itemKey => $item) {
            $identity = match (true) {
                $key === null => $item,
                is_int($key), is_string($key) => self::valueFrom($item, $key),
                default => $key($item, $itemKey),
            };

            if (in_array($identity, $seen, $strict)) {
                continue;
            }

            $seen[] = $identity;
            $result[$itemKey] = $item;
        }

        /** @var static<TKey, TValue> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @param  iterable<array-key, TValue>  $items
     * @return static<array-key, TValue>
     */
    public function merge(iterable $items): static
    {
        /** @var static<array-key, TValue> $collection */
        $collection = new static(array_merge($this->items, is_array($items) ? $items : iterator_to_array($items)));

        return $collection;
    }

    /**
     * @param  iterable<array-key, TValue>  $items
     * @return static<array-key, TValue>
     */
    public function concat(iterable $items): static
    {
        $result = $this->items;

        foreach ($items as $key => $value) {
            if (is_int($key)) {
                $result[] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        /** @var static<array-key, TValue> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @param  TValue  ...$values
     * @return $this
     */
    public function push(mixed ...$values): static
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;
    }

    /**
     * @param  TValue  $value
     * @param  TKey|null  $key
     * @return $this
     */
    public function prepend(mixed $value, int|string|null $key = null): static
    {
        if ($key === null) {
            array_unshift($this->items, $value);
        } else {
            $this->items = [$key => $value] + $this->items;
        }

        return $this;
    }

    public function pop(): mixed
    {
        return array_pop($this->items);
    }

    public function shift(): mixed
    {
        return array_shift($this->items);
    }

    /**
     * @param  TKey  $key
     * @param  TValue  $value
     * @return $this
     */
    public function put(int|string $key, mixed $value): static
    {
        $this->items[$key] = $value;

        return $this;
    }

    /**
     * @param  int|string|array<int, int|string>  $keys
     * @return $this
     */
    public function forget(int|string|array $keys): static
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            unset($this->items[$key]);
        }

        return $this;
    }

    /**
     * @param  array<int, int|string>|int|string  $keys
     * @return static<array-key, TValue>
     */
    public function only(array|int|string $keys): static
    {
        $keys = is_array($keys) ? $keys : [$keys];

        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $this->items)) {
                $result[$key] = $this->items[$key];
            }
        }

        /** @var static<array-key, TValue> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @param  array<int, int|string>|int|string  $keys
     * @return static<TKey, TValue>
     */
    public function except(array|int|string $keys): static
    {
        $keys = is_array($keys) ? $keys : [$keys];

        /** @var static<TKey, TValue> $collection */
        $collection = new static(array_diff_key($this->items, array_flip($keys)));

        return $collection;
    }

    /**
     * @return static<TKey, TValue>
     */
    public function slice(int $offset, ?int $length = null, bool $preserveKeys = false): static
    {
        /** @var static<TKey, TValue> $collection */
        $collection = new static(array_slice($this->items, $offset, $length, $preserveKeys));

        return $collection;
    }

    /**
     * @return static<TKey, TValue>
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->slice($limit, null, true);
        }

        return $this->slice(0, $limit);
    }

    /**
     * @return static<TKey, TValue>
     */
    public function skip(int $count): static
    {
        return $this->slice($count, null, true);
    }

    /**
     * @return static<int, Collection<int, TValue>>
     */
    public function chunk(int $size): static
    {
        if ($size < 1) {
            /** @var static<int, Collection<int, TValue>> $empty */
            $empty = new static;

            return $empty;
        }

        $chunks = [];

        foreach (array_chunk($this->items, $size) as $chunk) {
            $chunks[] = new static($chunk);
        }

        /** @var static<int, Collection<int, TValue>> $collection */
        $collection = new static($chunks);

        return $collection;
    }

    /**
     * @return static<int, mixed>
     */
    public function collapse(): static
    {
        $result = [];

        foreach ($this->items as $item) {
            if (! is_iterable($item)) {
                continue;
            }

            foreach ($item as $value) {
                $result[] = $value;
            }
        }

        /** @var static<int, mixed> $collection */
        $collection = new static($result);

        return $collection;
    }

    /**
     * @param  string|callable(TValue, array-key): mixed  $value
     */
    public function implode(string|callable $value, ?string $glue = null): string
    {
        if (is_string($value)) {
            $first = $this->first();

            if (is_array($first) || (is_object($first) && ! $first instanceof \Stringable)) {
                return implode($glue ?? '', array_map(
                    static fn (mixed $item): string => (string) self::valueFrom($item, $value),
                    $this->items
                ));
            }

            return implode($value, array_map(static fn (mixed $item): string => (string) $item, $this->items));
        }

        $pieces = [];

        foreach ($this->items as $key => $item) {
            $pieces[] = (string) $value($item, $key);
        }

        return implode($glue ?? '', $pieces);
    }

    /**
     * @param  string|callable(TValue, array-key): mixed|null  $callback
     */
    public function sum(string|callable|null $callback = null): int|float
    {
        $sum = 0;

        foreach ($this->items as $key => $item) {
            $value = match (true) {
                is_string($callback) => self::valueFrom($item, $callback),
                $callback !== null => $callback($item, $key),
                default => $item,
            };

            if (is_numeric($value)) {
                $sum += $value;
            } elseif (is_bool($value)) {
                $sum += (int) $value;
            }
        }

        return $sum;
    }

    /**
     * @param  callable(mixed, TValue, array-key): mixed  $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $carry = $initial;

        foreach ($this->items as $key => $item) {
            $carry = $callback($carry, $item, $key);
        }

        return $carry;
    }

    public function search(mixed $value, bool $strict = false): int|string|false
    {
        return array_search($value, $this->items, $strict);
    }

    /**
     * @param  iterable<mixed>  ...$arrays
     * @return static<int, array<int, mixed>>
     */
    public function zip(iterable ...$arrays): static
    {
        $normalized = [];

        foreach ($arrays as $array) {
            $normalized[] = array_values(is_array($array) ? $array : iterator_to_array($array));
        }

        /** @var static<int, array<int, mixed>> $collection */
        $collection = new static(array_map(static fn (mixed ...$values): array => $values, array_values($this->items), ...$normalized));

        return $collection;
    }

    /**
     * @param  callable(Collection<TKey, TValue>, mixed): (static<TKey, TValue>|null)  $callback
     * @param  (callable(Collection<TKey, TValue>, mixed): (static<TKey, TValue>|null))|null  $default
     * @return static<TKey, TValue>
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if ($value) {
            return $callback($this, $value) ?? new static($this->items);
        }

        if ($default !== null) {
            return $default($this, $value) ?? new static($this->items);
        }

        return new static($this->items);
    }

    /**
     * @param  callable(Collection<TKey, TValue>, mixed): (static<TKey, TValue>|null)  $callback
     * @param  (callable(Collection<TKey, TValue>, mixed): (static<TKey, TValue>|null))|null  $default
     * @return static<TKey, TValue>
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): static
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if (! $value) {
            return $callback($this, $value) ?? new static($this->items);
        }

        if ($default !== null) {
            return $default($this, $value) ?? new static($this->items);
        }

        return new static($this->items);
    }

    /**
     * @param  callable(Collection<TKey, TValue>): mixed  $callback
     * @return $this
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * @return LazyCollection<TKey, TValue>
     */
    public function lazy(): LazyCollection
    {
        return new LazyCollection($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * @param  TKey|null  $offset
     * @param  TValue  $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;

            return;
        }

        $this->items[$offset] = $value;
    }

    /**
     * @param  TKey  $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<array-key, TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->items, $options | JSON_THROW_ON_ERROR);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}
