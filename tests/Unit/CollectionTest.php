<?php

declare(strict_types=1);

namespace Daika7ana\SimpleCollections\Tests\Unit;

use Daika7ana\SimpleCollections\Collection;
use Daika7ana\SimpleCollections\LazyCollection;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function test_make_creates_collection_from_iterable(): void
    {
        $collection = Collection::make(['a' => 1, 'b' => 2]);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame(['a' => 1, 'b' => 2], $collection->all());
        $this->assertSame([], Collection::make()->all());
    }

    public function test_wrap_handles_null_collection_iterable_and_scalar(): void
    {
        $this->assertSame([], Collection::wrap(null)->all());

        $original = new Collection(['a' => 1]);
        $copy = Collection::wrap($original);

        $this->assertNotSame($original, $copy);
        $this->assertSame(['a' => 1], $copy->all());

        $this->assertSame([1, 2], Collection::wrap([1, 2])->all());
        $this->assertSame([1, 2], Collection::wrap(new \ArrayIterator([1, 2]))->all());
        $this->assertSame(['value'], Collection::wrap('value')->all());
        $this->assertSame([42], Collection::wrap(42)->all());
    }

    public function test_times_and_range_build_sequences(): void
    {
        $this->assertSame([1, 2, 3], Collection::times(3)->all());
        $this->assertSame([2, 4, 6], Collection::times(3, static fn (int $number): int => $number * 2)->all());
        $this->assertSame([1, 3, 5], Collection::range(1, 5, 2)->all());
        $this->assertSame([3, 2, 1], Collection::range(3, 1)->all());
    }

    public function test_all_and_to_array_return_raw_items(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);

        $this->assertSame(['a' => 1, 'b' => 2], $collection->all());
        $this->assertSame(['a' => 1, 'b' => 2], $collection->toArray());
    }

    public function test_get_supports_default_values(): void
    {
        $collection = new Collection(['a' => null, 'b' => 2]);

        $this->assertNull($collection->get('a'));
        $this->assertSame(2, $collection->get('b'));
        $this->assertNull($collection->get('missing'));
        $this->assertSame('fallback', $collection->get('missing', 'fallback'));
    }

    public function test_count_and_empty_state_helpers(): void
    {
        $empty = new Collection;
        $filled = new Collection(['a']);

        $this->assertSame(0, $empty->count());
        $this->assertCount(1, $filled);
        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($empty->isNotEmpty());
        $this->assertFalse($filled->isEmpty());
        $this->assertTrue($filled->isNotEmpty());
    }

    public function test_has_checks_keys_and_treats_null_as_present(): void
    {
        $collection = new Collection(['a' => 1, 'nullable' => null]);

        $this->assertTrue($collection->has('a'));
        $this->assertTrue($collection->has('nullable'));
        $this->assertTrue($collection->has(['a', 'nullable']));
        $this->assertFalse($collection->has('missing'));
        $this->assertFalse($collection->has(['a', 'missing']));
    }

    public function test_contains_supports_values_callables_and_strictness(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);

        $this->assertTrue($collection->contains(2));
        $this->assertFalse($collection->contains(3));
        $this->assertTrue($collection->contains('2'));
        $this->assertFalse($collection->contains('2', true));
        $this->assertTrue($collection->contains(static fn (int $value, string $key): bool => $key === 'b'));
        $this->assertFalse($collection->contains(static fn (int $value): bool => $value > 5));
    }

    public function test_keys_and_values(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);

        $this->assertSame(['a', 'b'], $collection->keys()->all());
        $this->assertSame([1, 2], $collection->values()->all());
    }

    public function test_first_and_last_with_callbacks_and_defaults(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(1, $collection->first());
        $this->assertSame(3, $collection->last());
        $this->assertSame(2, $collection->first(static fn (int $value): bool => $value > 1));
        $this->assertSame(2, $collection->last(static fn (int $value): bool => $value < 3));
        $this->assertNull((new Collection)->first());
        $this->assertSame('none', (new Collection)->first(null, 'none'));
        $this->assertSame('none', $collection->first(static fn (int $value): bool => $value > 10, 'none'));
        $this->assertSame('none', $collection->last(static fn (int $value): bool => $value > 10, 'none'));
    }

    public function test_map_preserves_keys(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);

        $mapped = $collection->map(static fn (int $value, string $key): string => $key.':'.$value);

        $this->assertSame(['a' => 'a:1', 'b' => 'b:2'], $mapped->all());
    }

    public function test_map_with_keys(): void
    {
        $collection = new Collection([['id' => 1, 'name' => 'one'], ['id' => 2, 'name' => 'two']]);

        $mapped = $collection->mapWithKeys(static fn (array $item): array => ['item_'.$item['id'] => $item['name']]);

        $this->assertSame(['item_1' => 'one', 'item_2' => 'two'], $mapped->all());
    }

    public function test_flat_map_flattens_one_level_and_reindexes(): void
    {
        $collection = new Collection(['a' => [1, 2], 'b' => [3]]);

        $this->assertSame([1, 2, 3], $collection->flatMap(static fn (array $items): array => $items)->all());
        $this->assertSame([2, 4, 6], $collection->flatMap(static fn (array $items): array => array_map(
            static fn (int $item): int => $item * 2,
            $items,
        ))->all());
    }

    public function test_filter_preserves_keys_and_filters_truthy_without_callback(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(['b' => 2, 'c' => 3], $collection->filter(static fn (int $value): bool => $value > 1)->all());

        $truthy = new Collection(['empty' => '', 'zero' => 0, 'false' => false, 'kept' => 'value']);

        $this->assertSame(['kept' => 'value'], $truthy->filter()->all());
    }

    public function test_reject_removes_matching_items(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(['a' => 1, 'c' => 3], $collection->reject(static fn (int $value): bool => $value % 2 === 0)->all());
    }

    public function test_each_stops_on_false_and_returns_same_instance(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
        $visited = [];

        $result = $collection->each(static function (int $value, string $key) use (&$visited): bool {
            $visited[$key] = $value;

            return $key !== 'b';
        });

        $this->assertSame($collection, $result);
        $this->assertSame(['a' => 1, 'b' => 2], $visited);
    }

    public function test_pluck_reads_arrays_and_objects_and_defaults_to_null(): void
    {
        $collection = new Collection([
            'first' => ['name' => 'alpha'],
            'second' => (object) ['name' => 'omega'],
            'third' => ['other' => 'missing'],
        ]);

        $this->assertSame([
            'first' => 'alpha',
            'second' => 'omega',
            'third' => null,
        ], $collection->pluck('name')->all());
    }

    public function test_pluck_can_key_by_another_column(): void
    {
        $collection = new Collection([
            ['id' => 10, 'name' => 'Express'],
            (object) ['id' => 20, 'name' => 'Standard'],
            ['id' => null, 'name' => 'Fallback'],
            ['id' => ['nested'], 'name' => 'Unkeyable'],
        ]);

        $this->assertSame([
            10 => 'Express',
            20 => 'Standard',
            2 => 'Fallback',
            3 => 'Unkeyable',
        ], $collection->pluck('name', 'id')->all());
    }

    public function test_key_by_using_key_or_callable(): void
    {
        $collection = new Collection([
            ['id' => 10, 'name' => 'Express'],
            ['id' => 20, 'name' => 'Standard'],
        ]);

        $this->assertSame([10 => ['id' => 10, 'name' => 'Express'], 20 => ['id' => 20, 'name' => 'Standard']], $collection->keyBy('id')->all());
        $this->assertSame(['a' => 1, 'b' => 2], (new Collection([1, 2]))->keyBy(static fn (int $value): string => $value === 1 ? 'a' : 'b')->all());
    }

    public function test_group_by_returns_collection_of_collections(): void
    {
        $collection = new Collection([
            ['type' => 'a', 'value' => 1],
            ['type' => 'b', 'value' => 2],
            ['type' => 'a', 'value' => 3],
        ]);

        $grouped = $collection->groupBy('type');

        $this->assertSame(['a', 'b'], $grouped->keys()->all());
        $this->assertInstanceOf(Collection::class, $grouped->get('a'));
        $this->assertSame([['type' => 'a', 'value' => 1], ['type' => 'a', 'value' => 3]], $grouped->get('a')->all());
        $this->assertSame([['type' => 'b', 'value' => 2]], $grouped->get('b')->all());

        $this->assertSame(['odd', 'even'], (new Collection([1, 2, 3]))->groupBy(
            static fn (int $value): string => $value % 2 === 0 ? 'even' : 'odd',
        )->keys()->all());
    }

    public function test_sort_and_sort_desc_preserve_keys(): void
    {
        $collection = new Collection([0 => 3, 1 => 1, 2 => 2]);

        $this->assertSame([1 => 1, 2 => 2, 0 => 3], $collection->sort()->all());
        $this->assertSame([0 => 3, 2 => 2, 1 => 1], $collection->sortDesc()->all());
    }

    public function test_sort_by_string_callable_and_descending(): void
    {
        $collection = new Collection([
            'second' => ['name' => 'beta', 'rank' => 2],
            'first' => ['name' => 'alpha', 'rank' => 1],
            'third' => ['name' => 'gamma', 'rank' => 3],
        ]);

        $this->assertSame(['first', 'second', 'third'], $collection->sortBy('name')->keys()->all());
        $this->assertSame(['first', 'second', 'third'], $collection->sortBy(static fn (array $item): int => $item['rank'])->keys()->all());
        $this->assertSame(['third', 'second', 'first'], $collection->sortByDesc('rank')->keys()->all());
        $this->assertSame(['third', 'second', 'first'], $collection->sortBy('rank', SORT_REGULAR, true)->keys()->all());
    }

    public function test_reverse_preserves_keys(): void
    {
        $this->assertSame(['b' => 2, 'a' => 1], (new Collection(['a' => 1, 'b' => 2]))->reverse()->all());
    }

    public function test_unique_with_key_callable_and_strictness(): void
    {
        $this->assertSame([0 => 1, 1 => 2, 3 => 3], (new Collection([1, 2, 2, 3]))->unique()->all());

        $items = new Collection([
            ['id' => 1, 'name' => 'first'],
            ['id' => 2, 'name' => 'second'],
            ['id' => 1, 'name' => 'duplicate'],
        ]);

        $this->assertSame([0, 1], $items->unique('id')->keys()->all());
        $this->assertSame([0, 1], $items->unique(static fn (array $item): int => $item['id'] % 2)->keys()->all());

        $this->assertSame([0 => 1, 1 => '1'], (new Collection([1, '1']))->unique(null, true)->all());
        $this->assertSame([0 => 1], (new Collection([1, '1']))->unique()->all());
    }

    public function test_merge_reindexes_numeric_keys_and_keeps_string_keys(): void
    {
        $this->assertSame([1, 2, 3, 4], (new Collection([1, 2]))->merge([3, 4])->all());
        $this->assertSame(['a' => 1, 'b' => 2], (new Collection(['a' => 1]))->merge(['b' => 2])->all());
        $this->assertSame([1, 2, 3], (new Collection([1, 2]))->merge(new \ArrayIterator([3]))->all());
    }

    public function test_concat_appends_numeric_keys_and_keeps_string_keys(): void
    {
        $this->assertSame(['x', 'y', 'z'], (new Collection(['x', 'y']))->concat(['z'])->all());
        $this->assertSame(['a' => 1, 'b' => 2], (new Collection(['a' => 1]))->concat(['b' => 2])->all());
    }

    public function test_mutators_return_same_instance(): void
    {
        $collection = new Collection(['a' => 1]);

        $this->assertSame($collection, $collection->push(2));
        $this->assertSame($collection, $collection->prepend(0));
        $this->assertSame($collection, $collection->put('c', 3));
        $this->assertSame($collection, $collection->forget('a'));

        $this->assertSame([0 => 0, 1 => 2, 'c' => 3], $collection->all());
    }

    public function test_push_prepend_pop_and_shift(): void
    {
        $collection = new Collection(['b', 'c']);

        $collection->push('d')->prepend('a');

        $this->assertSame(['a', 'b', 'c', 'd'], $collection->all());
        $this->assertSame('d', $collection->pop());
        $this->assertSame('a', $collection->shift());
        $this->assertSame(['b', 'c'], $collection->all());
    }

    public function test_prepend_with_explicit_key(): void
    {
        $collection = new Collection(['beta' => 'second']);

        $collection->prepend('first', 'alpha');

        $this->assertSame(['alpha' => 'first', 'beta' => 'second'], $collection->all());
    }

    public function test_put_and_forget_support_scalar_and_array_keys(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

        $collection->put('d', 4);
        $collection->forget(['a', 'c']);
        $collection->forget('b');

        $this->assertSame(['d' => 4], $collection->all());
    }

    public function test_only_and_except_accept_scalar_and_array_keys(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(['c' => 3, 'a' => 1], $collection->only(['c', 'a', 'missing'])->all());
        $this->assertSame(['b' => 2], $collection->only('b')->all());
        $this->assertSame(['a' => 1, 'c' => 3], $collection->except(['b', 'missing'])->all());
        $this->assertSame(['a' => 1, 'b' => 2], $collection->except('c')->all());
    }

    public function test_slice_take_and_skip(): void
    {
        $collection = new Collection([1, 2, 3, 4]);

        $this->assertSame([2, 3], $collection->slice(1, 2)->all());
        $this->assertSame([1 => 2, 2 => 3], $collection->slice(1, 2, true)->all());
        $this->assertSame([1, 2], $collection->take(2)->all());
        $this->assertSame([2 => 3, 3 => 4], $collection->take(-2)->all());
        $this->assertSame([2 => 3, 3 => 4], $collection->skip(2)->all());
    }

    public function test_chunk_returns_collections_with_reindexed_items(): void
    {
        $chunks = (new Collection([1, 2, 3]))->chunk(2);

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(Collection::class, $chunks->get(0));
        $this->assertSame([1, 2], $chunks->get(0)->all());
        $this->assertSame([3], $chunks->get(1)->all());
    }

    public function test_collapse_flattens_one_level_and_reindexes(): void
    {
        $this->assertSame([1, 2, 3, 4], (new Collection([[1, 2], [3, 4]]))->collapse()->all());
        $this->assertSame([1, 2], (new Collection([new Collection([1, 2])]))->collapse()->all());
    }

    public function test_implode_with_glue_and_callable(): void
    {
        $collection = new Collection(['a', 'b', 'c']);

        $this->assertSame('a, b, c', $collection->implode(', '));
        $this->assertSame('A-B-C', $collection->implode(static fn (string $value): string => strtoupper($value), '-'));
    }

    public function test_sum_with_null_key_and_callable(): void
    {
        $this->assertSame(6, (new Collection([1, 2, 3]))->sum());
        $this->assertSame(6, (new Collection([['price' => 1], ['price' => 5]]))->sum('price'));
        $this->assertSame(6, (new Collection([1, 2, 3]))->sum(static fn (int $value): int => $value));
        $this->assertSame(1.5, (new Collection([0.5, 1.0]))->sum());
    }

    public function test_reduce_receives_carry_value_and_key(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);

        $result = $collection->reduce(
            static fn (string $carry, int $value, string $key): string => $carry.$key.$value,
            '',
        );

        $this->assertSame('a1b2', $result);
        $this->assertSame(3, $collection->reduce(static fn (int $carry, int $value): int => $carry + $value, 0));
    }

    public function test_search_loose_and_strict(): void
    {
        $collection = new Collection([1, 2, 3]);

        $this->assertSame(1, $collection->search('2'));
        $this->assertFalse($collection->search('2', true));
        $this->assertSame(1, $collection->search(2, true));
        $this->assertFalse($collection->search(99));
    }

    public function test_zip_combines_items(): void
    {
        $this->assertSame([[1, 3], [2, 4]], (new Collection([1, 2]))->zip([3, 4])->all());
        $this->assertSame([[1, 3, 5], [2, 4, 6]], (new Collection([1, 2]))->zip([3, 4], [5, 6])->all());
        $this->assertSame([[1, 3], [2, null]], (new Collection([1, 2]))->zip([3])->all());
    }

    public function test_when_with_truthy_value_closure_and_default(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2],
            (new Collection(['a' => 1]))->when(true, static fn (Collection $items): Collection => $items->put('b', 2))->all(),
        );

        $this->assertSame(
            ['a' => 1],
            (new Collection(['a' => 1]))->when(false, static fn (Collection $items): Collection => $items->put('b', 2))->all(),
        );

        $this->assertSame(
            ['a' => 1, 'fallback' => true],
            (new Collection(['a' => 1]))->when(false, static fn (Collection $items): Collection => $items, static fn (Collection $items): Collection => $items->put('fallback', true))->all(),
        );

        $this->assertSame(
            ['a' => 1, 'b' => 2],
            (new Collection(['a' => 1]))->when(static fn (Collection $items): bool => $items->count() === 1, static fn (Collection $items): Collection => $items->put('b', 2))->all(),
        );
    }

    public function test_unless_inverts_the_condition(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2],
            (new Collection(['a' => 1]))->unless(false, static fn (Collection $items): Collection => $items->put('b', 2))->all(),
        );

        $this->assertSame(
            ['a' => 1],
            (new Collection(['a' => 1]))->unless(true, static fn (Collection $items): Collection => $items->put('b', 2))->all(),
        );

        $this->assertSame(
            ['a' => 1, 'fallback' => true],
            (new Collection(['a' => 1]))->unless(true, static fn (Collection $items): Collection => $items, static fn (Collection $items): Collection => $items->put('fallback', true))->all(),
        );
    }

    public function test_tap_invokes_callback_and_returns_same_instance(): void
    {
        $collection = new Collection(['a' => 1]);
        $seen = null;

        $result = $collection->tap(static function (Collection $items) use (&$seen): void {
            $seen = $items->all();
        });

        $this->assertSame($collection, $result);
        $this->assertSame(['a' => 1], $seen);
    }

    public function test_lazy_returns_lazy_collection(): void
    {
        $lazy = (new Collection(['a' => 1]))->lazy();

        $this->assertInstanceOf(LazyCollection::class, $lazy);
        $this->assertSame(['a' => 1], $lazy->all());
    }

    public function test_array_access_read_write_append_and_unset(): void
    {
        $collection = new Collection(['a' => 1]);

        $this->assertTrue(isset($collection['a']));
        $this->assertSame(1, $collection['a']);
        $this->assertNull($collection['missing']);

        $collection['b'] = 2;
        $collection[] = 3;

        $this->assertSame(['a' => 1, 'b' => 2, 0 => 3], $collection->all());

        unset($collection['a']);

        $this->assertFalse(isset($collection['a']));
        $this->assertSame(['b' => 2, 0 => 3], $collection->all());
    }

    public function test_offset_exists_sees_null_values(): void
    {
        $collection = new Collection(['nullable' => null]);

        $this->assertTrue($collection->has('nullable'));
        $this->assertTrue(isset($collection['nullable']));
    }

    public function test_get_iterator_yields_items(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);

        $this->assertSame(['a' => 1, 'b' => 2], iterator_to_array($collection));
    }

    public function test_json_serialization(): void
    {
        $collection = new Collection(['a' => 1, 'b' => [2, 3]]);

        $this->assertSame(['a' => 1, 'b' => [2, 3]], $collection->jsonSerialize());
        $this->assertSame('{"a":1,"b":[2,3]}', $collection->toJson());
        $this->assertSame('{"a":1,"b":[2,3]}', (string) $collection);
        $this->assertSame('{"a":1,"b":[2,3]}', json_encode($collection));
    }

    public function test_json_serialization_supports_options(): void
    {
        $json = (new Collection(['a' => 1]))->toJson(JSON_PRETTY_PRINT);

        $this->assertStringContainsString("\n", $json);
    }

    public function test_static_factories_and_transforms_preserve_subclass_instances(): void
    {
        $collection = new class(['a' => 1, 'b' => 2]) extends Collection {};

        $this->assertInstanceOf($collection::class, $collection->keys());
        $this->assertInstanceOf($collection::class, $collection->values());
        $this->assertInstanceOf($collection::class, $collection->filter(static fn (int $value): bool => $value > 1));
        $this->assertInstanceOf($collection::class, $collection->map(static fn (int $value): int => $value * 10));
        $this->assertInstanceOf($collection::class, $collection->only(['a']));
        $this->assertInstanceOf($collection::class, $collection->pluck('x'));
        $this->assertInstanceOf($collection::class, $collection->slice(0, 1));
        $this->assertInstanceOf($collection::class, $collection->reverse());
        $this->assertInstanceOf($collection::class, $collection->sortBy(static fn (int $value): int => $value));
        $this->assertInstanceOf($collection::class, $collection->merge(['c' => 3]));
        $this->assertInstanceOf($collection::class, $collection->when(true, static fn (Collection $items): Collection => $items));
    }

    public function test_compatibility_with_country_lookup_collection(): void
    {
        $collection = new Collection([
            'ro' => ['id' => 642, 'name' => 'Romania'],
            'bg' => ['id' => 100, 'name' => 'Bulgaria'],
        ]);

        $this->assertSame('Romania', $collection->first()['name']);
        $this->assertSame('Bulgaria', $collection->last()['name']);
        $this->assertTrue($collection->has('ro'));
        $this->assertSame(642, $collection->pluck('id')->first());
        $this->assertSame(['ro', 'bg'], $collection->keys()->all());
        $this->assertSame(['Romania', 'Bulgaria'], $collection->pluck('name')->values()->all());
        $this->assertSame('Romania', $collection['ro']['name']);
    }

    public function test_compatibility_with_filter_map_values_chain(): void
    {
        $result = (new Collection([1, 2, 3, 4]))
            ->filter(static fn (int $value): bool => $value % 2 === 0)
            ->map(static fn (int $value): int => $value * 10)
            ->values();

        $this->assertSame([20, 40], $result->all());
    }

    public function test_three_ways_to_construct(): void
    {
        $generator = static function (): \Generator {
            yield 'a' => 1;
            yield 'b' => 2;
        };

        $this->assertSame(['a' => 1, 'b' => 2], (new Collection($generator()))->all());
        $this->assertSame(['a' => 1, 'b' => 2], Collection::make($generator())->all());
        $this->assertSame(['a' => 1, 'b' => 2], Collection::collect($generator())->all());
        $this->assertSame([], Collection::collect()->all());
        $this->assertInstanceOf(Collection::class, Collection::collect([]));
    }

    public function test_get_without_a_key_returns_all_items(): void
    {
        $collection = new Collection(['a' => 1, 'b' => null]);

        $this->assertSame(['a' => 1, 'b' => null], $collection->get());
        $this->assertNull($collection->get('b', 'fallback'));
        $this->assertSame('fallback', $collection->get('c', 'fallback'));
    }

    public function test_implode_reads_a_field_from_rows(): void
    {
        $rows = new Collection([['name' => 'a'], ['name' => 'b']]);

        $this->assertSame('a, b', $rows->implode('name', ', '));
        $this->assertSame('ab', $rows->implode('name'));
        $this->assertSame('1,2', (new Collection([1, 2]))->implode(','));
    }

    public function test_key_by_falls_back_to_the_original_key_when_the_value_is_missing(): void
    {
        $items = new Collection([['id' => null, 'name' => 'x'], ['name' => 'y'], ['id' => 'k', 'name' => 'z']]);

        $this->assertSame([0 => ['id' => null, 'name' => 'x'], 1 => ['name' => 'y'], 'k' => ['id' => 'k', 'name' => 'z']], $items->keyBy('id')->all());
    }

    public function test_where_does_not_match_items_missing_the_key(): void
    {
        $items = new Collection([['s' => 0], ['s' => null], ['x' => 1]]);

        $this->assertSame([['s' => 0], ['s' => null]], $items->where('s', 0)->all());
        $this->assertSame([], $items->where('x', 0)->all());
        $this->assertSame([2 => ['x' => 1]], $items->where('x', 1)->all());
    }

    public function test_has_key_alias(): void
    {
        $collection = new Collection(['a' => null, 'b' => 1]);

        $this->assertTrue($collection->hasKey('a'));
        $this->assertFalse($collection->hasKey('c'));
    }

    public function test_constructors_keep_subclasses(): void
    {
        $subclass = new class extends Collection {};

        $this->assertInstanceOf($subclass::class, new $subclass([1]));
        $this->assertInstanceOf($subclass::class, $subclass::make([1]));
        $this->assertInstanceOf($subclass::class, $subclass::collect([1]));
    }

    public function test_where_supports_one_two_and_three_argument_forms(): void
    {
        $items = new Collection([
            'a' => ['enabled' => true, 'status' => 'active', 'qty' => 5],
            'b' => ['enabled' => false, 'status' => 'inactive', 'qty' => 10],
            'c' => ['enabled' => true, 'status' => 'active', 'qty' => 15],
        ]);

        $this->assertSame(['a', 'c'], $items->where('enabled')->keys()->all());
        $this->assertSame(['a', 'c'], $items->where('status', 'active')->keys()->all());
        $this->assertSame(['b', 'c'], $items->where('qty', '>', 5)->keys()->all());
        $this->assertSame(['a'], $items->where('qty', '===', 5)->keys()->all());
        $this->assertSame(['b', 'c'], $items->where(static fn (array $item): int => $item['qty'], '>=', 10)->keys()->all());
        $this->assertSame(['b', 'c'], $items->where('qty', static fn (int $qty): bool => $qty > 5)->keys()->all());
        $this->assertSame([], $items->where('missing', 'x')->keys()->all());
    }

    public function test_where_throws_on_unsupported_operator(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Collection([['name' => 'a']]))->where('name', 'like', 'a%');
    }

    public function test_key_by_and_group_by_keep_scalar_keys_and_zip_always_wraps(): void
    {
        $items = new Collection([
            ['enabled' => true, 'group' => 1.5],
            ['enabled' => false, 'group' => null],
        ]);

        $this->assertSame(
            ['1' => ['enabled' => true, 'group' => 1.5], '' => ['enabled' => false, 'group' => null]],
            $items->keyBy('enabled')->all()
        );
        $this->assertSame(['1.5', ''], array_keys($items->groupBy('group')->all()));
        $this->assertSame([[1], [2]], (new Collection([1, 2]))->zip()->all());
    }

    public function test_negative_and_zero_limits(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);

        $this->assertSame([3 => 4, 4 => 5], $collection->skip(-2)->all());
        $this->assertSame([], $collection->take(0)->all());
        $this->assertSame([], $collection->chunk(0)->all());
        $this->assertSame([], $collection->chunk(-1)->all());
    }

    public function test_unique_uses_loose_identity_by_default(): void
    {
        $first = (object) ['id' => 1];
        $second = (object) ['id' => 1];

        $this->assertCount(1, (new Collection([$first, $second]))->unique()->all());
        $this->assertCount(2, (new Collection([$first, $second]))->unique(null, true)->all());
        $this->assertSame([1], (new Collection([1, '1']))->unique()->values()->all());
        $this->assertSame([1, '1'], (new Collection([1, '1']))->unique(null, true)->values()->all());
    }

    public function test_merge_and_concat_overwrite_duplicate_string_keys(): void
    {
        $collection = new Collection(['a' => 1]);

        $this->assertSame(['a' => 2, 'b' => 3], $collection->merge(['a' => 2, 'b' => 3])->all());
        $this->assertSame(['a' => 2, 0 => 3], $collection->concat(['a' => 2, 3])->all());
    }

    public function test_when_returns_a_copy_when_no_branch_applies(): void
    {
        $collection = new Collection(['a' => 1]);

        $result = $collection->when(false, static fn (Collection $items): Collection => $items->put('b', 2));

        $this->assertNotSame($collection, $result);
        $this->assertSame(['a' => 1], $collection->all());
        $this->assertSame(['a' => 1], $result->all());
    }

    public function test_to_json_throws_on_unencodable_data(): void
    {
        $this->expectException(JsonException::class);

        (new Collection([INF]))->toJson();
    }

    public function test_sort_accepts_php_sort_flags(): void
    {
        $this->assertSame([2 => '1', 0 => '10', 1 => '9'], (new Collection(['10', '9', '1']))->sort(SORT_STRING)->all());
        $this->assertSame([1 => '10', 0 => '9'], (new Collection(['9', '10']))->sortDesc(SORT_NUMERIC)->all());
    }

    public function test_sum_counts_booleans_and_handles_numeric_strings(): void
    {
        $this->assertSame(3, (new Collection([true, false, 2]))->sum());
        $this->assertSame(3.5, (new Collection(['1.5', '2']))->sum());
        $this->assertSame(2, (new Collection([['n' => 2], ['n' => 'nope']]))->sum('n'));
        $this->assertSame(0, (new Collection([['price' => '12,50']]))->sum('price'));
    }

    public function test_first_and_last_pass_the_key_to_the_callback(): void
    {
        $collection = new Collection(['a' => 10, 'b' => 20]);

        $this->assertSame(20, $collection->first(static fn (int $value, string $key): bool => $key === 'b'));
        $this->assertSame(10, $collection->last(static fn (int $value, string $key): bool => $key === 'a'));
    }

    public function test_where_supports_every_comparison_operator(): void
    {
        $items = new Collection([
            'a' => ['qty' => 5],
            'b' => ['qty' => 10],
            'c' => ['qty' => 15],
        ]);

        $this->assertSame(['b', 'c'], $items->where('qty', '!=', 5)->keys()->all());
        $this->assertSame(['a', 'b', 'c'], $items->where('qty', '!==', '5')->keys()->all());
        $this->assertSame(['b', 'c'], $items->where('qty', '<>', 5)->keys()->all());
        $this->assertSame(['a'], $items->where('qty', '<', 10)->keys()->all());
        $this->assertSame(['a', 'b'], $items->where('qty', '<=', 10)->keys()->all());
    }

    public function test_both_classes_agree_on_shared_scenarios(): void
    {
        $rows = [
            ['code' => 'b', 'qty' => 2],
            ['code' => 'a', 'qty' => 4],
            ['code' => 'b', 'qty' => 6],
        ];

        $eager = new Collection($rows);
        $lazy = LazyCollection::make($rows);

        $this->assertSame($eager->all(), $lazy->all());
        $this->assertSame(
            $eager->filter(static fn (array $row): bool => $row['qty'] > 2)->map(static fn (array $row): string => $row['code'])->values()->all(),
            $lazy->filter(static fn (array $row): bool => $row['qty'] > 2)->map(static fn (array $row): string => $row['code'])->values()->all()
        );
        $this->assertSame($eager->pluck('qty', 'code')->all(), $lazy->pluck('qty', 'code')->all());
        $this->assertSame($eager->where('qty', '>', 2)->keys()->all(), $lazy->where('qty', '>', 2)->keys()->all());
        $this->assertSame($eager->unique('code')->all(), $lazy->unique('code')->all());
        $this->assertSame($eager->concat(['a' => 9])->all(), $lazy->concat(['a' => 9])->all());
        $this->assertSame($eager->sum('qty'), $lazy->sum('qty'));
        $this->assertSame(
            (new Collection([true, false, '1.5', '2', 'x']))->sum(),
            LazyCollection::make([true, false, '1.5', '2', 'x'])->sum()
        );
        $this->assertSame($eager->implode('code', ','), $lazy->implode('code', ','));
    }
}
