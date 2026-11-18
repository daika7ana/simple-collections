<?php

declare(strict_types=1);

namespace Daika7ana\SimpleCollections\Tests\Unit;

use Daika7ana\SimpleCollections\Collection;
use Daika7ana\SimpleCollections\LazyCollection;
use Generator;
use JsonException;
use PHPUnit\Framework\TestCase;

final class LazyCollectionTest extends TestCase
{
    public function test_constructor_accepts_array_and_closure(): void
    {
        $this->assertSame([1, 2, 3], LazyCollection::make([1, 2, 3])->all());
        $this->assertSame([1, 2, 3], LazyCollection::make(fn () => [1, 2, 3])->all());
        $this->assertSame([1, 2, 3], (new LazyCollection([1, 2, 3]))->all());
    }

    public function test_array_backed_collection_can_be_iterated_repeatedly(): void
    {
        $lazy = LazyCollection::make([1, 2, 3])->map(fn (int $value): int => $value * 2);

        $this->assertSame([2, 4, 6], $lazy->all());
        $this->assertSame([2, 4, 6], $lazy->all());
    }

    public function test_transformations_do_no_work_until_iterated(): void
    {
        $iterations = 0;

        $source = static function () use (&$iterations): Generator {
            foreach ([1, 2, 3, 4] as $value) {
                $iterations++;
                yield $value;
            }
        };

        LazyCollection::make($source)
            ->map(fn (int $value): int => $value * 2)
            ->filter(fn (int $value): bool => $value > 2)
            ->reject(fn (int $value): bool => $value === 4)
            ->take(2)
            ->skip(0)
            ->unique()
            ->values()
            ->keys()
            ->mapWithKeys(fn (int $key): array => [$key => $key])
            ->flatMap(fn (int $value): array => [$value])
            ->chunk(2)
            ->concat([[]])
            ->collapse();

        $this->assertSame(0, $iterations);
    }

    public function test_map_preserves_keys(): void
    {
        $result = LazyCollection::make(['a' => 1, 'b' => 2])
            ->map(fn (int $value, string $key): string => $key.$value)
            ->all();

        $this->assertSame(['a' => 'a1', 'b' => 'b2'], $result);
    }

    public function test_map_with_keys(): void
    {
        $result = LazyCollection::make([1, 2])
            ->mapWithKeys(fn (int $value): array => ['k'.$value => $value * 10])
            ->all();

        $this->assertSame(['k1' => 10, 'k2' => 20], $result);
    }

    public function test_filter_defaults_to_truthiness_and_preserves_keys(): void
    {
        $result = LazyCollection::make(['a' => 0, 'b' => 1, 'c' => '', 'd' => 'x'])
            ->filter()
            ->all();

        $this->assertSame(['b' => 1, 'd' => 'x'], $result);
    }

    public function test_filter_reject_with_callback(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->assertSame(
            ['b' => 2, 'c' => 3],
            LazyCollection::make($source)->filter(fn (int $value): bool => $value > 1)->all(),
        );

        $this->assertSame(
            ['a' => 1],
            LazyCollection::make($source)->reject(fn (int $value, string $key): bool => $key !== 'a')->all(),
        );
    }

    public function test_take_and_skip_preserve_keys(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];

        $this->assertSame(['a' => 1, 'b' => 2], LazyCollection::make($source)->take(2)->all());
        $this->assertSame([], LazyCollection::make($source)->take(0)->all());
        $this->assertSame(['c' => 3, 'd' => 4], LazyCollection::make($source)->skip(2)->all());
        $this->assertSame($source, LazyCollection::make($source)->skip(0)->all());
    }

    public function test_take_stops_early(): void
    {
        $iterations = 0;

        $result = LazyCollection::make(static function () use (&$iterations): Generator {
            foreach (range(1, 100) as $value) {
                $iterations++;
                yield $value;
            }
        })->take(3)->all();

        $this->assertSame([1, 2, 3], $result);
        $this->assertSame(3, $iterations);
    }

    public function test_values_reindexes_and_keys_keeps_original_keys_as_values(): void
    {
        $source = ['a' => 1, 'b' => 2];

        $this->assertSame([1, 2], LazyCollection::make($source)->values()->all());
        $this->assertSame([0 => 'a', 1 => 'b'], LazyCollection::make($source)->keys()->all());
    }

    public function test_unique(): void
    {
        $source = [1, 2, 1, 3, 2];

        $this->assertSame([0 => 1, 1 => 2, 3 => 3], LazyCollection::make($source)->unique()->all());

        $this->assertSame(
            [1, '1', 2, '2'],
            LazyCollection::make([1, '1', 2, '2'])->unique(null, true)->values()->all(),
        );

        $this->assertSame(
            [1, 2],
            LazyCollection::make([1, '1', 2, '2'])->unique()->values()->all(),
        );

        $rows = [
            ['id' => 1, 'name' => 'a'],
            ['id' => 1, 'name' => 'b'],
            ['id' => 2, 'name' => 'c'],
        ];

        $this->assertSame(
            [0 => ['id' => 1, 'name' => 'a'], 2 => ['id' => 2, 'name' => 'c']],
            LazyCollection::make($rows)->unique('id')->all(),
        );

        $this->assertSame(
            ['a' => 1, 'b' => 2],
            LazyCollection::make(['a' => 1, 'b' => 2, 'c' => 3])->unique(fn (int $value): int => $value % 2)->all(),
        );
    }

    public function test_flat_map_reindexes(): void
    {
        $result = LazyCollection::make([1, 2])
            ->flatMap(fn (int $value): array => [$value, $value * 10])
            ->all();

        $this->assertSame([1, 10, 2, 20], $result);
        $this->assertSame([0, 1, 2, 3], array_keys($result));
    }

    public function test_pluck_preserves_keys_and_returns_null_for_missing_values(): void
    {
        $source = [
            'a' => ['name' => 'Alice'],
            'b' => ['name' => 'Bob'],
            'c' => ['name' => null],
            'd' => [],
        ];

        $this->assertSame(
            ['a' => 'Alice', 'b' => 'Bob', 'c' => null, 'd' => null],
            LazyCollection::make($source)->pluck('name')->all(),
        );
    }

    public function test_pluck_from_objects_and_with_key_by(): void
    {
        $first = new \stdClass;
        $first->id = 1;
        $first->name = 'Alice';

        $second = new \stdClass;
        $second->id = 2;
        $second->name = 'Bob';

        $this->assertSame(
            [1 => 'Alice', 2 => 'Bob'],
            LazyCollection::make([$first, $second])->pluck('name', 'id')->all(),
        );

        $this->assertSame(
            ['a' => 'Alice', 'b' => 'Bob'],
            LazyCollection::make(['a' => ['name' => 'Alice'], 'b' => ['name' => 'Bob']])
                ->pluck('name', 'missing')
                ->all(),
        );
    }

    public function test_pluck_with_non_scalar_key_by_falls_back_to_original_key(): void
    {
        $this->assertSame(
            ['a' => 'Alice'],
            LazyCollection::make(['a' => ['name' => 'Alice', 'key' => ['nested']]])
                ->pluck('name', 'key')
                ->all(),
        );
    }

    public function test_chunk_yields_reindexed_array_chunks(): void
    {
        $this->assertSame(
            [[1, 2], [3, 4], [5]],
            LazyCollection::make([1, 2, 3, 4, 5])->chunk(2)->all(),
        );

        $this->assertSame([], LazyCollection::make([1, 2])->chunk(0)->all());
    }

    public function test_concat_and_collapse(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2, 'x' => 3],
            LazyCollection::make(['a' => 1])->concat(['b' => 2, 'x' => 3])->all(),
        );

        $this->assertSame(
            [0 => 1, 1 => 2, 2 => 3, 3 => 4],
            LazyCollection::make([[1, 2], [3, 4], 'ignored'])->collapse()->all(),
        );
    }

    public function test_all_and_to_array_preserve_keys(): void
    {
        $lazy = LazyCollection::make(['a' => 1, 'b' => 2]);

        $this->assertSame(['a' => 1, 'b' => 2], $lazy->all());
        $this->assertSame(['a' => 1, 'b' => 2], $lazy->toArray());
    }

    public function test_first_and_last(): void
    {
        $lazy = LazyCollection::make(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(1, $lazy->first());
        $this->assertSame(2, $lazy->first(fn (int $value): bool => $value > 1));
        $this->assertSame('fallback', $lazy->first(fn (int $value): bool => $value > 10, 'fallback'));
        $this->assertNull($lazy->first(fn (int $value): bool => $value > 10));

        $this->assertSame(3, $lazy->last());
        $this->assertSame(1, $lazy->last(fn (int $value): bool => $value < 2));
        $this->assertSame('fallback', $lazy->last(fn (int $value): bool => $value > 10, 'fallback'));
    }

    public function test_count_and_emptiness(): void
    {
        $this->assertSame(3, LazyCollection::make([1, 2, 3])->count());
        $this->assertSame(0, LazyCollection::empty()->count());
        $this->assertTrue(LazyCollection::empty()->isEmpty());
        $this->assertFalse(LazyCollection::empty()->isNotEmpty());
        $this->assertFalse(LazyCollection::make([null])->isEmpty());
        $this->assertTrue(LazyCollection::make([1])->isNotEmpty());
    }

    public function test_sum(): void
    {
        $this->assertSame(6, LazyCollection::make([1, 2, 3])->sum());
        $this->assertSame(6, LazyCollection::make(['a' => 1, 'b' => 2, 'c' => 3])->sum(fn (int $value): int => $value));
        $this->assertSame(3, LazyCollection::make([['n' => 1], ['n' => 2]])->sum('n'));
        $this->assertSame(5.5, LazyCollection::make([1, 2, 2.5])->sum());
        $this->assertSame(0, LazyCollection::make(['x' => 'not a number'])->sum());
    }

    public function test_reduce(): void
    {
        $this->assertSame(
            'a1b2',
            LazyCollection::make(['a' => 1, 'b' => 2])->reduce(
                fn (string $carry, int $value, string $key): string => $carry.$key.$value,
                '',
            ),
        );

        $this->assertSame(6, LazyCollection::make([1, 2, 3])->reduce(fn (int $carry, int $value): int => $carry + $value, 0));
    }

    public function test_implode(): void
    {
        $this->assertSame('1,2,3', LazyCollection::make([1, 2, 3])->implode(','));
        $this->assertSame('1-2-3', LazyCollection::make([1, 2, 3])->implode(fn (int $value): string => (string) $value, '-'));
        $this->assertSame('a=1;b=2', LazyCollection::make(['a' => 1, 'b' => 2])->implode(fn (int $value, string $key): string => $key.'='.$value, ';'));
    }

    public function test_contains(): void
    {
        $lazy = LazyCollection::make(['a' => 1, 'b' => 2]);

        $this->assertTrue($lazy->contains(2));
        $this->assertFalse($lazy->contains(3));
        $this->assertFalse($lazy->contains('2', true));
        $this->assertTrue($lazy->contains('2'));
        $this->assertTrue($lazy->contains(fn (int $value, string $key): bool => $key === 'b'));
        $this->assertFalse($lazy->contains(fn (int $value): bool => $value > 5));
    }

    public function test_json_serialization(): void
    {
        $lazy = LazyCollection::make(['a' => 1, 'b' => [2, 3]]);

        $this->assertSame(['a' => 1, 'b' => [2, 3]], $lazy->jsonSerialize());
        $this->assertSame('{"a":1,"b":[2,3]}', $lazy->toJson());
        $this->assertSame((string) $lazy, $lazy->toJson());
        $this->assertSame('{"a":1,"b":[2,3]}', (string) $lazy);
        $this->assertSame("{\n    \"a\": 1,\n    \"b\": [\n        2,\n        3\n    ]\n}", $lazy->toJson(JSON_PRETTY_PRINT));
    }

    public function test_group_by_returns_collections_and_buffers(): void
    {
        $lazy = LazyCollection::make([
            ['type' => 'a', 'value' => 1],
            ['type' => 'b', 'value' => 2],
            ['type' => 'a', 'value' => 3],
        ])->groupBy('type');

        $result = $lazy->all();

        $this->assertSame(['a', 'b'], array_keys($result));
        $this->assertInstanceOf(Collection::class, $result['a']);
        $this->assertSame([
            ['type' => 'a', 'value' => 1],
            ['type' => 'a', 'value' => 3],
        ], $result['a']->all());
        $this->assertSame([['type' => 'b', 'value' => 2]], $result['b']->all());
    }

    public function test_group_by_callable(): void
    {
        $result = LazyCollection::make([1, 2, 3, 4])->groupBy(fn (int $value): string => $value % 2 === 0 ? 'even' : 'odd')->all();

        $this->assertSame(['odd', 'even'], array_keys($result));
        $this->assertSame([1, 3], $result['odd']->values()->all());
        $this->assertSame([2, 4], $result['even']->values()->all());
    }

    public function test_to_collection(): void
    {
        $collection = LazyCollection::make(['a' => 1, 'b' => 2])->toCollection();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame(['a' => 1, 'b' => 2], $collection->all());
    }

    public function test_each_stops_on_false_and_returns_this(): void
    {
        $seen = [];

        $lazy = LazyCollection::make([1, 2, 3, 4]);

        $this->assertSame($lazy, $lazy->each(function (int $value) use (&$seen): mixed {
            $seen[] = $value;

            return $value < 2 ? null : false;
        }));

        $this->assertSame([1, 2], $seen);
    }

    public function test_tap_returns_this_without_consuming(): void
    {
        $iterations = 0;

        $lazy = LazyCollection::make(static function () use (&$iterations): Generator {
            $iterations++;
            yield 1;
        });

        $tapped = null;

        $this->assertSame($lazy, $lazy->tap(function (LazyCollection $collection) use (&$tapped): void {
            $tapped = $collection;
        }));
        $this->assertSame($lazy, $tapped);
        $this->assertSame(0, $iterations);
    }

    public function test_when_and_unless(): void
    {
        $lazy = LazyCollection::make([1, 2]);

        $this->assertSame([2, 3], $lazy->when(true, fn (LazyCollection $c): LazyCollection => $c->map(fn (int $v): int => $v + 1))->all());
        $this->assertSame([1, 2], $lazy->when(false, fn (LazyCollection $c): LazyCollection => $c->take(1))->all());
        $this->assertSame([1], $lazy->when(false, fn (LazyCollection $c): LazyCollection => $c->take(1), fn (LazyCollection $c): LazyCollection => $c->take(1))->all());
        $this->assertSame([1], $lazy->when(fn (LazyCollection $c): bool => $c->count() === 2, fn (LazyCollection $c): LazyCollection => $c->take(1))->all());
        $this->assertSame([1], $lazy->unless(false, fn (LazyCollection $c): LazyCollection => $c->take(1))->all());
        $this->assertSame([1, 2], $lazy->unless(true, fn (LazyCollection $c): LazyCollection => $c->take(1))->all());
        $this->assertSame([1], $lazy->unless(true, fn (LazyCollection $c): LazyCollection => $c->take(2), fn (LazyCollection $c): LazyCollection => $c->take(1))->all());
    }

    public function test_generator_backed_collection_is_single_use(): void
    {
        $lazy = LazyCollection::make((static function (): Generator {
            yield 1;
            yield 2;
        })());

        $this->assertSame([1, 2], $lazy->all());

        $this->expectException(\Exception::class);

        $lazy->all();
    }

    public function test_get_iterator_wraps_arrays(): void
    {
        $iterator = LazyCollection::make(['a' => 1])->getIterator();

        $this->assertInstanceOf(\ArrayIterator::class, $iterator);
        $this->assertSame(['a' => 1], iterator_to_array($iterator));
    }

    public function test_string_columns_are_treated_as_keys_not_functions(): void
    {
        $rows = [
            ['count' => 1, 'date' => 'a'],
            ['count' => 2, 'date' => 'a'],
            ['count' => 2, 'date' => 'b'],
        ];

        $this->assertSame([1, 2], LazyCollection::make($rows)->unique('count')->pluck('count')->all());
        $this->assertSame(['a', 'b'], LazyCollection::make($rows)->groupBy('date')->keys()->all());
        $this->assertSame(5, LazyCollection::make($rows)->sum('count'));
    }

    public function test_concat_appends_numeric_keys_like_the_eager_class(): void
    {
        $this->assertSame(['x', 'y', 'z'], LazyCollection::make(['x', 'y'])->concat(['z'])->all());
        $this->assertSame(['a' => 1, 'b' => 2], LazyCollection::make(['a' => 1])->concat(['b' => 2])->all());
        $this->assertSame([5 => 'x', 6 => 'z'], LazyCollection::make([5 => 'x'])->concat(['z'])->all());
    }

    public function test_group_by_is_deferred_until_iteration(): void
    {
        $hits = 0;

        $grouped = LazyCollection::make(function () use (&$hits): Generator {
            $hits++;

            yield ['date' => 'a'];
        })->groupBy('date');

        $this->assertSame(0, $hits);
        $this->assertSame(['a'], $grouped->keys()->all());
        $this->assertSame(1, $hits);
    }

    public function test_when_and_unless_pass_the_value_and_tolerate_null_results(): void
    {
        $lazy = LazyCollection::make([1, 2]);

        $this->assertSame($lazy, $lazy->when(false, fn (LazyCollection $c): LazyCollection => $c->take(1)));
        $this->assertSame([1, 2], $lazy->when(true, fn (LazyCollection $c, mixed $v): null => null)->all());
        $this->assertSame([1], $lazy->unless(false, fn (LazyCollection $c, mixed $v): LazyCollection => $c->take(1))->all());
    }

    public function test_where_callable_key_receives_the_item_key(): void
    {
        $filtered = LazyCollection::make(['a' => ['v' => 1], 'b' => ['v' => 1]])
            ->where(static fn (array $item, string $key): string => $key, '=', 'b');

        $this->assertSame(['b' => ['v' => 1]], $filtered->all());
    }

    public function test_implode_reads_a_field_from_rows(): void
    {
        $rows = LazyCollection::make([['name' => 'a'], ['name' => 'b']]);

        $this->assertSame('a, b', $rows->implode('name', ', '));
        $this->assertSame('1,2', LazyCollection::make([1, 2])->implode(','));
        $this->assertSame('0:1|1:2', LazyCollection::make([1, 2])->implode(fn (int $v, int $k): string => "$k:$v", '|'));
    }

    public function test_where_does_not_match_items_missing_the_key(): void
    {
        $items = LazyCollection::make([['s' => 0], ['s' => null], ['x' => 1]]);

        $this->assertSame([['s' => 0], ['s' => null]], $items->where('s', 0)->all());
        $this->assertSame([], $items->where('x', 0)->all());
    }

    public function test_collect_is_an_alias_for_make(): void
    {
        $lazy = LazyCollection::collect(['a' => 1]);

        $this->assertInstanceOf(LazyCollection::class, $lazy);
        $this->assertSame(['a' => 1], $lazy->all());
    }

    public function test_where_stays_lazy_and_forwards_all_argument_forms(): void
    {
        $hits = 0;

        $lazy = LazyCollection::make(function () use (&$hits): Generator {
            $hits++;

            yield 'a' => ['qty' => 5];
            yield 'b' => ['qty' => 10];
            yield 'c' => ['qty' => 15];
        });

        $filtered = $lazy->where('qty', '>=', 10)->where('qty', static fn (int $qty): bool => $qty < 15);

        $this->assertSame(0, $hits);
        $this->assertInstanceOf(LazyCollection::class, $filtered);
        $this->assertSame(['b' => ['qty' => 10]], $filtered->all());
        $this->assertSame(1, $hits);
        $this->assertSame([], LazyCollection::make([['enabled' => false]])->where('enabled')->all());
        $this->assertSame(['x' => ['name' => 'x']], LazyCollection::make(['x' => ['name' => 'x']])->where('name', 'x')->all());
    }

    public function test_negative_limits_are_empty_or_passthrough(): void
    {
        $lazy = LazyCollection::make([1, 2, 3]);

        $this->assertSame([], $lazy->take(-1)->all());
        $this->assertSame([1, 2, 3], $lazy->skip(-1)->all());
        $this->assertSame([], $lazy->chunk(-1)->all());
    }

    public function test_unique_uses_loose_identity_by_default(): void
    {
        $first = (object) ['id' => 1];
        $second = (object) ['id' => 1];

        $this->assertCount(1, LazyCollection::make([$first, $second])->unique()->all());
        $this->assertCount(2, LazyCollection::make([$first, $second])->unique(null, true)->all());
        $this->assertSame([1], LazyCollection::make([1, '1'])->unique()->values()->all());
        $this->assertSame([1, '1'], LazyCollection::make([1, '1'])->unique(null, true)->values()->all());
    }

    public function test_concat_overwrites_duplicate_string_keys(): void
    {
        $this->assertSame(['a' => 2, 0 => 3], LazyCollection::make(['a' => 1])->concat(['a' => 2, 3])->all());
    }

    public function test_when_returns_the_same_instance_when_no_branch_applies(): void
    {
        $lazy = LazyCollection::make([1, 2]);

        $this->assertSame($lazy, $lazy->when(false, static fn (LazyCollection $items): LazyCollection => $items->take(1)));
        $this->assertSame($lazy, $lazy->when(true, static fn (): ?LazyCollection => null));
    }

    public function test_to_json_throws_on_unencodable_data(): void
    {
        $this->expectException(JsonException::class);

        LazyCollection::make([INF])->toJson();
    }

    public function test_sum_counts_booleans_and_handles_numeric_strings(): void
    {
        $this->assertSame(3, LazyCollection::make([true, false, 2])->sum());
        $this->assertSame(3.5, LazyCollection::make(['1.5', '2'])->sum());
        $this->assertSame(2, LazyCollection::make([['n' => 2], ['n' => 'nope']])->sum('n'));
        $this->assertSame(0, LazyCollection::make([['price' => '12,50']])->sum('price'));
    }

    public function test_first_and_last_pass_the_key_to_the_callback(): void
    {
        $lazy = LazyCollection::make(['a' => 10, 'b' => 20]);

        $this->assertSame(20, $lazy->first(static fn (int $value, string $key): bool => $key === 'b'));
        $this->assertSame(10, $lazy->last(static fn (int $value, string $key): bool => $key === 'a'));
    }
}
