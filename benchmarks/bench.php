<?php

declare(strict_types=1);

/**
 * Micro-benchmark: this package vs illuminate/collections.
 *
 * Usage:
 *   php benchmarks/bench.php ours all
 *   php benchmarks/bench.php laravel all
 *   php benchmarks/bench.php ours sum 15
 *
 * The Laravel side needs a separate Composer install that provides
 * illuminate/collections. Point ILLUMINATE_AUTOLOAD at its autoload.php:
 *
 *   mkdir -p /tmp/collections-bench && cd /tmp/collections-bench
 *   composer require illuminate/collections:^13
 *   ILLUMINATE_AUTOLOAD=/tmp/collections-bench/vendor/autoload.php \
 *     php /path/to/benchmarks/bench.php laravel all
 *
 * Each scenario runs in its own process when "all" is used, so peak memory
 * is not shared between scenarios. Times are wall-clock milliseconds.
 */

require dirname(__DIR__).'/vendor/autoload.php';

use Daika7ana\SimpleCollections\Collection as OurCollection;
use Daika7ana\SimpleCollections\LazyCollection as OurLazy;
use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\LazyCollection as LaravelLazy;

[$_, $impl, $scenario, $repsArg] = $argv + [null, null, null, null];
$reps = max(3, (int) ($repsArg ?? 7));

if ($impl !== 'ours' && $impl !== 'laravel') {
    fwrite(STDERR, "usage: bench.php <ours|laravel> <scenario|all> [reps]\n");
    exit(1);
}

if ($impl === 'laravel') {
    $illuminate = getenv('ILLUMINATE_AUTOLOAD');
    if ($illuminate === false || ! is_file($illuminate)) {
        fwrite(STDERR, "Set ILLUMINATE_AUTOLOAD to an autoload.php that provides illuminate/collections.\n");
        exit(1);
    }
    require $illuminate;
}

$eager = $impl === 'laravel' ? LaravelCollection::class : OurCollection::class;
$lazy = $impl === 'laravel' ? LaravelLazy::class : OurLazy::class;

$scenarioNames = [
    'construct', 'filter_map', 'where_map', 'pluck', 'key_by', 'group_by',
    'sort_by', 'unique', 'sum', 'reduce', 'contains', 'first_match',
    'lazy_filter_map_take', 'lazy_filter_map_sum', 'lazy_pluck_take',
    'lazy_group_by', 'lazy_chunk', 'lazy_count', 'mem_lazy_sum_1m',
    'mem_lazy_take_1m', 'mem_eager_50k',
];

if ($scenario === 'all') {
    foreach ($scenarioNames as $name) {
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '
            .escapeshellarg($impl).' '.escapeshellarg($name).' '.escapeshellarg((string) $reps));
    }
    exit(0);
}

if (! in_array($scenario, $scenarioNames, true)) {
    fwrite(STDERR, "unknown scenario $scenario\n");
    exit(1);
}

$n = (int) (getenv('BENCH_ROWS') ?: 50_000);
$rows = [];
for ($i = 0; $i < $n; $i++) {
    $rows[] = [
        'id' => $i,
        'name' => "name$i",
        'code' => "C$i",
        'group' => 'g'.($i % 50),
        'price' => ($i % 1000) + 0.5,
        'active' => $i % 3 !== 0,
    ];
}

$source = static function () use ($rows): Generator {
    yield from $rows;
};

$scenarios = [
    'construct' => static fn () => $eager::make($rows)->count(),
    'filter_map' => static fn () => $eager::make($rows)->filter(static fn (array $r): bool => $r['active'])->map(static fn (array $r): string => $r['name'])->values()->all(),
    'where_map' => static fn () => $eager::make($rows)->where('active', true)->map(static fn (array $r): string => $r['name'])->values()->all(),
    'pluck' => static fn () => $eager::make($rows)->pluck('name')->all(),
    'key_by' => static fn () => $eager::make($rows)->keyBy('code')->all(),
    'group_by' => static fn () => $eager::make($rows)->groupBy('group')->map(static fn ($g): int => $g->count())->all(),
    'sort_by' => static fn () => $eager::make($rows)->sortBy('price')->take(100)->all(),
    'unique' => static fn () => $eager::make($rows)->unique('group')->count(),
    'sum' => static fn () => $eager::make($rows)->sum('price'),
    'reduce' => static fn () => $eager::make($rows)->reduce(static fn (float $c, array $r): float => $c + $r['price'], 0.0),
    'contains' => static fn () => $eager::make($rows)->contains(static fn (array $r): bool => $r['id'] === $n - 1),
    'first_match' => static fn () => $eager::make($rows)->first(static fn (array $r): bool => $r['id'] === $n - 1)['name'],

    'lazy_filter_map_take' => static fn () => $lazy::make($source)->filter(static fn (array $r): bool => $r['active'])->map(static fn (array $r): string => $r['name'])->take(1_000)->all(),
    'lazy_filter_map_sum' => static fn () => $lazy::make($source)->filter(static fn (array $r): bool => $r['active'])->map(static fn (array $r): float => $r['price'])->sum(),
    'lazy_pluck_take' => static fn () => $lazy::make($source)->pluck('name')->take(1_000)->all(),
    'lazy_group_by' => static fn () => $lazy::make($source)->groupBy('group')->map(static fn ($g): int => $g->count())->all(),
    'lazy_chunk' => static fn () => $lazy::make($source)->chunk(1_000)->take(5)->all(),
    'lazy_count' => static fn () => $lazy::make($source)->count(),

    'mem_lazy_sum_1m' => static function () use ($lazy): float {
        $gen = (static function (): Generator {
            for ($i = 0; $i < 1_000_000; $i++) {
                yield ['id' => $i, 'price' => $i % 1000];
            }
        })();

        return (float) $lazy::make(static fn (): Generator => $gen)->sum('price');
    },
    'mem_lazy_take_1m' => static function () use ($lazy): array {
        $gen = (static function (): Generator {
            for ($i = 0; $i < 1_000_000; $i++) {
                yield ['id' => $i, 'price' => $i % 1000];
            }
        })();

        return $lazy::make(static fn (): Generator => $gen)->take(10)->all();
    },
    'mem_eager_50k' => static fn () => $eager::make($rows)->all(),
];

$times = [];
for ($i = 0; $i < $reps; $i++) {
    gc_collect_cycles();
    $t = hrtime(true);
    $scenarios[$scenario]();
    $times[] = (hrtime(true) - $t) / 1e6;
}

sort($times);

printf("%s\n", json_encode([
    'impl' => $impl,
    'scenario' => $scenario,
    'min_ms' => round($times[0], 3),
    'median_ms' => round($times[intdiv(count($times), 2)], 3),
    'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    'times' => array_map(static fn (float $x): float => round($x, 3), $times),
]));
