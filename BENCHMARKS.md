# Benchmarks

This package vs `illuminate/collections` v13.43.0, measured on PHP 8.5.12 (CLI, OPcache enabled) on one Linux machine.

**These are microbenchmarks, not production profiling.** Laravel does more work on purpose: dot-notation field access, `ArrayAccess` support, operator tables, and the `Macroable`/`Enumerable` machinery. The gaps below mostly measure the features this package omits.

## Results

50,000 array rows shaped `['id' => int, 'name' => string, 'code' => string, 'group' => string, 'price' => float, 'active' => bool]`.
Times are wall-clock milliseconds, minimum of 7 runs in a fresh process per scenario. Peak memory is `memory_get_peak_usage(true)` for that process and includes the ~28 MB fixture. **Bold = clearly faster (over 5%); near-ties are left plain.**

| Scenario | Ours (ms) | Laravel (ms) | Ratio | Ours MB | Laravel MB |
| --- | ---: | ---: | ---: | ---: | ---: |
| `make()` + `count()` | 0.003 | 0.003 | 1.00× | 28 | 28 |
| `filter()` → `map()` → `values()` | 7.31 | 7.53 | ~1.0× | 30 | 34 |
| `where()` → `map()` → `values()` | **17.85** | 41.31 | 0.43× | 30 | 34 |
| `pluck('name')` | **3.16** | 9.93 | 0.32× | 28 | 28 |
| `keyBy('code')` | **7.04** | 15.08 | 0.47× | 30.5 | 32.5 |
| `groupBy('group')` + count | **5.85** | 20.42 | 0.29× | 28 | 28 |
| `sortBy('price')` + `take(100)` | **13.78** | 21.10 | 0.65× | 33 | 30.5 |
| `unique('group')` + `count()` | **9.30** | 21.26 | 0.44× | 28 | 28 |
| `sum('price')` | **3.43** | 15.27 | 0.22× | 28 | 28 |
| `reduce()` over `price` | **2.40** | 3.62 | 0.66× | 28 | 28 |
| `contains()` with callback | 2.44 | 2.48 | 0.99× | 28 | 28 |
| `first()` with callback | 2.48 | 2.49 | 0.99× | 28 | 28 |
| Lazy `filter()` → `map()` → `take(1000)` → `all()` | **0.25** | 0.27 | 0.92× | 28 | 28 |
| Lazy `filter()` → `map()` → `sum()` | 7.44 | 7.60 | 0.98× | 28 | 28 |
| Lazy `pluck('name')` → `take(1000)` → `all()` | **0.19** | 0.32 | 0.58× | 28 | 28 |
| Lazy `groupBy('group')` + count | **6.50** | 21.76 | 0.30× | 28 | 30 |
| Lazy `chunk(1000)` → `take(5)` → `all()` | **0.20** | 0.52 | 0.39× | 28 | 28 |
| Lazy `count()` | 0.59 | 0.59 | 1.00× | 28 | 28 |
| Lazy `sum('price')` over a 1M-row generator | **131** | 328 | 0.40× | 26 | 28 |
| Lazy `take(10)` from a 1M-row generator | 0.002 | 0.002 | 1.00× | 26 | 28 |
| Eager `all()` on 50k rows | 0.004 | 0.004 | 1.00× | 28 | 28 |

Reading the table:

- Anything that reads one field per row (`pluck`, `sum`, `where`, `keyBy`, `groupBy`, `unique`) is 2–4.5× faster because Laravel routes every read through `data_get()`/`valueRetriever()`.
- Work that runs inside your own callback (`filter`, `map`, `contains`, `first`, `reduce`, `sort`) is at parity — the callback dominates.
- Both lazy implementations stream: peak memory for a 1M-row pass stays at the process baseline. Ours starts ~2 MB lower because far fewer files are loaded.
- `filter_map` varies by ±5% between runs; treat it as parity.

Footprint for reference: this package is ~1,700 source lines and 3 Composer requirements (`php` only at runtime); the Illuminate slice used here is ~8,100 lines plus 7 requirements (`illuminate/contracts`, `macroable`, `conditionable`, three Symfony polyfills).

## Reproducing

The package itself has no Illuminate dependency. Install Illuminate in a scratch directory and point the benchmark at its autoloader:

```bash
mkdir -p /tmp/collections-bench && cd /tmp/collections-bench
composer require illuminate/collections:^13

cd /path/to/simple-collections

# Ours
php benchmarks/bench.php ours all 7

# Laravel
ILLUMINATE_AUTOLOAD=/tmp/collections-bench/vendor/autoload.php \
    php benchmarks/bench.php laravel all 7
```

Each scenario runs in its own process when `all` is used, so peak memory is not shared. Pass a scenario name instead of `all` with a custom rep count:

```bash
php benchmarks/bench.php ours sum 15
php benchmarks/bench.php ours mem_lazy_sum_1m 5
```

`BENCH_ROWS` changes the fixture size (default 50,000); the two 1M-row memory scenarios are fixed:

```bash
BENCH_ROWS=200000 php benchmarks/bench.php ours filter_map 5
```

Results are JSON lines, one per scenario, so they can be aggregated or diffed:

```bash
php benchmarks/bench.php ours all 7 | jq -s 'map({scenario, min_ms, peak_mb})'
```

Absolute numbers depend on PHP version, CPU, OPcache, and the fixture. Compare two implementations on the same machine in the same session, as above.
