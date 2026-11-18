# Simple Collections

<div align="center">

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php)](https://www.php.net/)
[![Latest Version](https://img.shields.io/packagist/v/daika7ana/simple-collections?style=flat-square)](https://packagist.org/packages/daika7ana/simple-collections)
[![PHPUnit 12](https://img.shields.io/badge/PHPUnit-12-0A7BBB?style=flat-square)](https://phpunit.de/)
[![PHPStan level 8](https://img.shields.io/badge/PHPStan-level%208-4B5563?style=flat-square)](https://phpstan.org/)
[![Zero Dependencies](https://img.shields.io/badge/dependencies-none-brightgreen?style=flat-square)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE)

A small, dependency-free **Collection** and **LazyCollection** for PHP 8.3+

</div>

> 🚀 A drop-in replacement for the hand-rolled collection helpers in the Ecolet and DPD SDKs — familiar Laravel method names, no Illuminate, no framework.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Collection](#collection)
- [LazyCollection](#lazycollection)
- [Laravel Interop](#laravel-interop)
- [Differences from illuminate/collections](#differences-from-illuminatecollections)
- [Performance](#performance)
- [Testing](#testing)
- [License](#license)

## Features

### Core Strengths

- ✅ **Typed and Dependency-Free** — PHP 8.3+, no runtime dependencies, PHPStan level 8, no macros, no `Enumerable`/`Arrayable`, no global `collect()`
- ✅ **Familiar API** — `map`, `filter`, `pluck`, `where`, `groupBy`, and the rest
- ✅ **Eager and Lazy** — `Collection` for arrays, generator-backed `LazyCollection` for streams
- ✅ **Key-Preserving `pluck()`** — matches the SDK helpers, unlike Laravel
- ✅ **Laravel-Friendly** — converts to and from Illuminate collections; the eager class stays subclassable

### Perfect For

- 🎯 Framework-free packages that want a fluent array API, or streaming large responses without buffering
- 🎯 Anywhere you would reach for `illuminate/collections` but not for the dependency

## Requirements

PHP 8.3+. No non-default extensions, no framework, no service container.

## Installation

```bash
composer require daika7ana/simple-collections
```

## Quick Start

```php
use Daika7ana\SimpleCollections\{Collection, LazyCollection};

$services = Collection::make($response['services'])
    ->filter(fn (array $s) => $s['active'])
    ->keyBy('code')
    ->map(fn (array $s) => $s['name']);

$services->get('express');   // 'Express delivery'

// Lazy: nothing runs until ->all().
$ids = LazyCollection::make($response['parcels'])
    ->filter(fn (array $p) => $p['status'] === 'delivered')
    ->pluck('id')->take(100)->all();
```

`Collection` implements `ArrayAccess`, `Countable`, `IteratorAggregate`, `JsonSerializable`, and `__toString()` (JSON). `LazyCollection` is `final` and implements `Countable`, `IteratorAggregate`, `JsonSerializable`, and `__toString()` — no `ArrayAccess`.

## Collection

Eager: transformations return a new instance; only the mutators and offset writes change it in place. Create with `new Collection($items)`, `Collection::make()`, or `Collection::collect()`; the constructor takes any `iterable`, `wrap()` handles a scalar or `null`, and `times()`/`range()` build sequences. `get()` with no key returns all items; `hasKey()` is an alias of `has()`.

| Group | Methods |
| --- | --- |
| Reading | `all()`, `toArray()`, `get()`, `first()`, `last()`, `count()`, `isEmpty()`, `isNotEmpty()`, `has()`, `hasKey()`, `contains()`, `keys()`, `values()`, `search()` |
| Transforming | `map()`, `mapWithKeys()`, `flatMap()`, `filter()`, `reject()`, `where()`, `pluck()`, `keyBy()`, `groupBy()`, `sort()`, `sortDesc()`, `sortBy()`, `sortByDesc()`, `reverse()`, `unique()`, `merge()`, `concat()`, `only()`, `except()`, `slice()`, `take()`, `skip()`, `chunk()`, `collapse()`, `zip()` |
| Aggregating | `sum()`, `reduce()`, `implode()` |
| Mutating | `push()`, `prepend()`, `pop()`, `shift()`, `put()`, `forget()` |
| Conditional | `when()`, `unless()`, `tap()`, `each()` |
| Conversion | `toJson()`, `jsonSerialize()`, `lazy()` |

- `when()`/`unless()` return a new instance when no branch applies; callbacks receive `($collection, $value)`. `each()` stops when the callback returns `false`.
- `sum()` counts numeric values and booleans, skipping the rest — `'12,50'` contributes `0` with no warning. `implode('field', ', ')` reads that field on array rows and non-`Stringable` objects; scalar and `Stringable` items use the first argument as the glue.
- `toJson()` always ORs `JSON_THROW_ON_ERROR`, so unencodable data throws; `toArray()` and `jsonSerialize()` are shallow.

## LazyCollection

The same names, deferred until a terminal consumes the sequence. Exceptions: `each()`, `tap()`, and the `when()`/`unless()` branch callbacks run immediately; `groupBy()` consumes the source as soon as its result is iterated.

Create with `new LazyCollection($source)`, `LazyCollection::make()`, or `LazyCollection::collect()`; `$source` is any `iterable` or a `Closure` returning one. `LazyCollection::empty()` starts empty.

| Group | Methods |
| --- | --- |
| Transforming | `map()`, `mapWithKeys()`, `flatMap()`, `filter()`, `reject()`, `where()`, `pluck()`, `keys()`, `values()`, `unique()`, `take()`, `skip()`, `chunk()`, `concat()`, `collapse()`, `groupBy()`, `each()`, `tap()`, `when()`, `unless()` |
| Terminals | `all()`, `toArray()`, `first()`, `last()`, `count()`, `isEmpty()`, `isNotEmpty()`, `sum()`, `reduce()`, `implode()`, `contains()`, `toJson()`, `toCollection()` |

Differences from the eager class:

- `chunk()` yields plain arrays, not `Collection` chunks; `groupBy()` buffers and yields `Collection` groups.
- Negative limits are eager-only: `Collection::take(-1)`/`skip(-1)` read from the end, while a non-positive `LazyCollection::take()` is empty and a non-positive `skip()` is a no-op. `chunk()` below 1 yields nothing in both.
- `when()`/`unless()` return `$this` when no branch applies.
- No `get()`, `has()`, or any `sort*`. `slice()`, `keyBy()`, `merge()`, `only()`, `except()`, `reverse()`, `search()`, `zip()`, and the mutators are eager-only — call `->toCollection()` when you need them.

A generator-backed instance is effectively single-pass: `all()`, `count()`, `last()`, `toJson()`, and a full `foreach` exhaust it. `take(1)`, `first()` without a callback, and `isEmpty()` are safe, but `take(2)` or a `first()` callback that looks further exhausts it too. Build it from an array, or a `Closure` returning a fresh generator, when you need more than one pass. `count()` counts array/`Countable` sources directly and otherwise walks with `iterator_count()`; it never returns on an infinite source.

## Laravel Interop

No Laravel dependency is needed. `Illuminate\Support\Collection` accepts ours via `JsonSerializable`, and the reverse works through the iterable constructor:

```php
$laravel = new \Illuminate\Support\Collection($ours);   // or collect($ours)
$ours = Collection::collect($laravel);
```

Conversion is shallow: nested collections are not rewritten. Do not keep both views alive and mutate one.

## Differences from `illuminate/collections`

Intentional, to keep the library small:

| Laravel | Here |
| --- | --- |
| `Macroable`, `Enumerable`, `Arrayable` | Not implemented |
| `collect()` global helper | `Collection::make()` / `LazyCollection::make()` |
| Dot notation (`pluck('user.name')`, `where('a.b', ...)`) | Single top-level key only |
| `where()` operators plus `whereIn()`, `whereBetween()`, `whereNull()`, `firstWhere()` | `where()` supports only `=`, `==`, `===`, `!=`, `!==`, `<>`, `<`, `<=`, `>`, `>=`, plus a callable key |
| `contains($key, $operator, $value)` | `contains($valueOrCallback, $strict = false)` |
| `pluck()` reindexes with `0..n` | `pluck()` preserves original keys |
| `toArray()` recurses into `Arrayable` items | `toArray()`/`jsonSerialize()` are shallow |
| Macro-driven; subclassing discouraged | Non-final `Collection`, `new static()` keeps subclasses |
| `LazyCollection` has `remember()`, `tapEach()`, `eager()`, `throttle()`, `takeUntilTimeout()` | Not implemented |

Also worth knowing:

- `where()` never matches an item that lacks the key, but `=`/`==` stay loose (`'5'` matches `5`); `unique()` is loose too — `unique(null, true)` is strict.
- `keyBy()` casts scalar keys and keeps rows with `null`/non-scalar keys under their original key; `groupBy()` buckets non-scalar keys under `''`; `zip()` pads shorter inputs with `null`.

## Performance

Against `illuminate/collections` v13.43.0 on PHP 8.5, 50k array rows, minimum of 7 runs per scenario (all 21 scenarios, memory, and methodology: [BENCHMARKS.md](BENCHMARKS.md)):

| Operation | Ours | Laravel |
| --- | ---: | ---: |
| `sum('price')` | 3.4 ms | 15.3 ms |
| `pluck('name')` | 3.2 ms | 9.9 ms |
| `where()` → `map()` | 17.9 ms | 41.3 ms |
| `groupBy('group')` + count | 5.9 ms | 20.4 ms |
| `filter()` → `map()` | 7.3 ms | 7.5 ms |
| Lazy `sum('price')` over 1M rows | 131 ms | 328 ms |

Field reads are 2–4.5× faster because this package skips dot notation and `data_get()`; work that runs inside your callback is at parity; both lazy classes stream without buffering. The comparison is not feature-equivalent — Laravel supports APIs this package deliberately omits (see the table above).

## Testing

```bash
composer ci      # pint --test → phpstan level 8 (src only) → phpunit
composer pint    # style check; vendor/bin/pint fixes
composer stan    # PHPStan level 8
composer test    # full PHPUnit suite

vendor/bin/phpunit --filter test_unique
vendor/bin/phpunit tests/Unit/CollectionTest.php
```

PHPUnit runs with `failOnWarning` and `failOnRisky`.

## License

MIT. See [LICENSE](LICENSE).
