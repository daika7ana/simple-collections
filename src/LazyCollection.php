<?php

declare(strict_types=1);

namespace Daika7ana\SimpleCollections;

use ArrayIterator;
use Closure;
use Countable;
use Daika7ana\SimpleCollections\Concerns\ComparesWhere;
use Generator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A lazily evaluated collection.
 *
 * A LazyCollection backed by a generator is single-use: iterating it twice
 * throws. Wrap arrays (or return a fresh array from your closure) to re-iterate.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements IteratorAggregate<TKey, TValue>
 */
final class LazyCollection implements Countable, IteratorAggregate, JsonSerializable
{
    use ComparesWhere;

    /** @var Closure(): iterable<TKey, TValue> */
    private Closure $source;

    /**
     * @param  iterable<TKey, TValue>|Closure(): iterable<TKey, TValue>  $source
     */
    public function __construct(iterable|Closure $source = [])
    {
        $this->source = $source instanceof Closure ? $source : fn () => $source;
    }

    /**
     * @param  iterable<TKey, TValue>|Closure(): iterable<TKey, TValue>  $source
     * @return static<TKey, TValue>
     */
    public static function make(iterable|Closure $source = []): static
    {
        return new self($source);
    }

    /**
     * Alias for make().
     *
     * @param  iterable<TKey, TValue>|Closure(): iterable<TKey, TValue>  $source
     * @return static<TKey, TValue>
     */
    public static function collect(iterable|Closure $source = []): static
    {
        return new self($source);
    }

    /**
     * @return static<array-key, mixed>
     */
    public static function empty(): static
    {
        return new self([]);
    }

    /**
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        $iterable = ($this->source)();

        return is_array($iterable) ? new ArrayIterator($iterable) : $iterable;
    }

    /**
     * @template TMapValue
     *
     * @param  callable(TValue, TKey): TMapValue  $callback
     * @return static<TKey, TMapValue>
     */
    public function map(callable $callback): static
    {
        return new self(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * @template TMapKey of array-key
     * @template TMapValue
     *
     * @param  callable(TValue, TKey): array<TMapKey, TMapValue>  $callback
     * @return static<TMapKey, TMapValue>
     */
    public function mapWithKeys(callable $callback): static
    {
        return new self(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield from $callback($value, $key);
            }
        });
    }

    /**
     * @param  (callable(TValue, TKey): bool)|null  $callback
     * @return static<TKey, TValue>
     */
    public function filter(?callable $callback = null): static
    {
        return new self(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback === null ? (bool) $value : $callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * @param  callable(TValue, TKey): bool  $callback
     * @return static<TKey, TValue>
     */
    public function reject(callable $callback): static
    {
        return new self(function () use ($callback) {
            foreach ($this as $key => $value) {
                if (! $callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
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
     * @template TFlatMapValue
     *
     * @param  callable(TValue, TKey): iterable<array-key, TFlatMapValue>  $callback
     * @return static<int, TFlatMapValue>
     */
    public function flatMap(callable $callback): static
    {
        return new self(function () use ($callback) {
            $index = 0;

            foreach ($this as $key => $value) {
                foreach ($callback($value, $key) as $item) {
                    yield $index++ => $item;
                }
            }
        });
    }

    /**
     * @return static<TKey, mixed>
     */
    public function pluck(int|string $value, int|string|null $keyBy = null): static
    {
        return new self(function () use ($value, $keyBy) {
            $extract = static fn (mixed $item, int|string $key): mixed => self::valueFrom($item, $key);

            foreach ($this as $key => $item) {
                $newKey = $keyBy === null ? null : $extract($item, $keyBy);

                yield (is_int($newKey) || is_string($newKey) ? $newKey : $key) => $extract($item, $value);
            }
        });
    }

    /**
     * @return static<int, TKey>
     */
    public function keys(): static
    {
        return new self(function () {
            $index = 0;

            foreach ($this as $key => $value) {
                yield $index++ => $key;
            }
        });
    }

    /**
     * @return static<int, TValue>
     */
    public function values(): static
    {
        return new self(function () {
            foreach ($this as $value) {
                yield $value;
            }
        });
    }

    /**
     * @param  int|string|(callable(TValue, TKey): mixed)|null  $key
     * @return static<TKey, TValue>
     */
    public function unique(int|string|callable|null $key = null, bool $strict = false): static
    {
        return new self(function () use ($key, $strict) {
            $seen = [];

            foreach ($this as $itemKey => $value) {
                $needle = match (true) {
                    is_int($key), is_string($key) => self::valueFrom($value, $key),
                    is_callable($key) => $key($value, $itemKey),
                    default => $value,
                };

                if (in_array($needle, $seen, $strict)) {
                    continue;
                }

                $seen[] = $needle;

                yield $itemKey => $value;
            }
        });
    }

    /**
     * @return static<TKey, TValue>
     */
    public function take(int $limit): static
    {
        return new self(function () use ($limit) {
            if ($limit <= 0) {
                return;
            }

            $taken = 0;

            foreach ($this as $key => $value) {
                yield $key => $value;

                if (++$taken >= $limit) {
                    return;
                }
            }
        });
    }

    /**
     * @return static<TKey, TValue>
     */
    public function skip(int $count): static
    {
        return new self(function () use ($count) {
            $skipped = 0;

            foreach ($this as $key => $value) {
                if ($skipped++ < $count) {
                    continue;
                }

                yield $key => $value;
            }
        });
    }

    /**
     * @return static<int, array<int, TValue>>
     */
    public function chunk(int $size): static
    {
        return new self(function () use ($size) {
            if ($size <= 0) {
                return;
            }

            $chunk = [];

            foreach ($this as $value) {
                $chunk[] = $value;

                if (count($chunk) === $size) {
                    yield $chunk;
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                yield $chunk;
            }
        });
    }

    /**
     * @template TConcatValue
     *
     * @param  iterable<array-key, TConcatValue>  $items
     * @return static<array-key, TValue|TConcatValue>
     */
    public function concat(iterable $items): static
    {
        return new self(function () use ($items) {
            $next = 0;

            foreach ($this as $key => $value) {
                if (is_int($key) && $key >= $next) {
                    $next = $key + 1;
                }

                yield $key => $value;
            }

            foreach ($items as $key => $value) {
                if (is_int($key)) {
                    yield $next++ => $value;
                } else {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * @return static<int, mixed>
     */
    public function collapse(): static
    {
        return new self(function () {
            $index = 0;

            foreach ($this as $values) {
                if (! is_iterable($values)) {
                    continue;
                }

                foreach ($values as $value) {
                    yield $index++ => $value;
                }
            }
        });
    }

    /**
     * @param  callable(TValue, TKey): mixed  $callback
     * @return static<TKey, TValue>
     */
    public function each(callable $callback): static
    {
        foreach ($this as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * @param  callable(static<TKey, TValue>): mixed  $callback
     * @return static<TKey, TValue>
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * @param  callable(static<TKey, TValue>, mixed): (static<TKey, TValue>|null)  $callback
     * @param  (callable(static<TKey, TValue>, mixed): (static<TKey, TValue>|null))|null  $default
     * @return static<TKey, TValue>
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if ($value) {
            return $callback($this, $value) ?? $this;
        }

        return $default === null ? $this : ($default($this, $value) ?? $this);
    }

    /**
     * @param  callable(static<TKey, TValue>, mixed): (static<TKey, TValue>|null)  $callback
     * @param  (callable(static<TKey, TValue>, mixed): (static<TKey, TValue>|null))|null  $default
     * @return static<TKey, TValue>
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): static
    {
        return $this->when($value, $default ?? static fn (self $collection): self => $collection, $callback);
    }

    /**
     * @param  int|string|(callable(TValue, TKey): mixed)  $groupBy
     * @return static<array-key, Collection<int, TValue>>
     */
    public function groupBy(int|string|callable $groupBy): static
    {
        return new self(function () use ($groupBy): Generator {
            $groups = [];

            foreach ($this as $key => $value) {
                $group = is_int($groupBy) || is_string($groupBy)
                    ? self::valueFrom($value, $groupBy)
                    : $groupBy($value, $key);

                $groupKey = is_int($group) || is_string($group)
                    ? $group
                    : (is_scalar($group) ? (string) $group : '');

                $groups[$groupKey][] = $value;
            }

            foreach ($groups as $key => $items) {
                yield $key => new Collection($items);
            }
        });
    }

    /**
     * @return Collection<TKey, TValue>
     */
    public function toCollection(): Collection
    {
        return new Collection($this->all());
    }

    /**
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return iterator_to_array($this->getIterator());
    }

    /**
     * @return array<TKey, TValue>
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * @param  (callable(TValue, TKey): bool)|null  $callback
     * @return TValue|null
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        foreach ($this as $key => $value) {
            if ($callback === null || $callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @param  (callable(TValue, TKey): bool)|null  $callback
     * @return TValue|null
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        $found = false;
        $result = $default;

        foreach ($this as $key => $value) {
            if ($callback === null || $callback($value, $key)) {
                $found = true;
                $result = $value;
            }
        }

        return $found ? $result : $default;
    }

    public function count(): int
    {
        $iterable = ($this->source)();

        return $iterable instanceof Countable ? count($iterable) : iterator_count($iterable);
    }

    public function isEmpty(): bool
    {
        foreach ($this as $value) {
            return false;
        }

        return true;
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * @param  string|(callable(TValue, TKey): mixed)|null  $callback
     */
    public function sum(string|callable|null $callback = null): int|float
    {
        $sum = 0;

        foreach ($this as $key => $value) {
            $item = match (true) {
                is_string($callback) => self::valueFrom($value, $callback),
                $callback !== null => $callback($value, $key),
                default => $value,
            };

            if (is_numeric($item)) {
                $sum += $item;
            } elseif (is_bool($item)) {
                $sum += (int) $item;
            }
        }

        return $sum;
    }

    /**
     * @param  callable(mixed, TValue, TKey): mixed  $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $carry = $initial;

        foreach ($this as $key => $value) {
            $carry = $callback($carry, $value, $key);
        }

        return $carry;
    }

    /**
     * @param  string|(callable(TValue, TKey): mixed)  $value
     */
    public function implode(string|callable $value, ?string $glue = null): string
    {
        $items = $this->all();

        if (is_string($value)) {
            $first = $items === [] ? null : reset($items);

            if (is_array($first) || (is_object($first) && ! $first instanceof \Stringable)) {
                $pieces = [];

                foreach ($items as $item) {
                    $pieces[] = (string) self::valueFrom($item, $value);
                }

                return implode($glue ?? '', $pieces);
            }

            return implode($value, array_map(static fn (mixed $item): string => (string) $item, $items));
        }

        $pieces = [];

        foreach ($items as $key => $item) {
            $pieces[] = (string) $value($item, $key);
        }

        return implode($glue ?? '', $pieces);
    }

    public function contains(mixed $value, bool $strict = false): bool
    {
        if ($value instanceof Closure || (is_callable($value) && ! is_string($value))) {
            foreach ($this as $key => $item) {
                if ($value($item, $key)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($this as $item) {
            if ($strict ? $item === $value : $item == $value) {
                return true;
            }
        }

        return false;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->all(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->all();
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}
