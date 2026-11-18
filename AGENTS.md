# AGENTS.md

Zero-dependency PHP 8.3+ library: an eager `Collection` and a lazy `LazyCollection` that replace `illuminate/collections` for two courier SDKs. Not a Laravel package — no macros, no `Enumerable`/`Arrayable`, no `Arr`, no global `collect()`, no Illuminate dependency anywhere in `composer.json`. Laravel interop works through `JsonSerializable` (into Laravel) and the iterable constructor (out of Laravel); do not add an interop method that references Illuminate.

## Commands

```bash
composer ci        # pint --test → phpstan level 8 → phpunit (this order)
vendor/bin/pint    # fix formatting (composer pint only checks)
vendor/bin/phpunit --filter test_name
vendor/bin/phpunit tests/Unit/CollectionTest.php
```

- phpstan analyses `src` only (level 8); tests are not type-checked.
- PHPUnit 12 with `failOnWarning`/`failOnRisky`. Prefer `assertSame`; there is no loose `assertEquals` in the suite. Use the dedicated assertion for booleans, nulls, counts, and instances.
- The directory is not a git repo and has no CI or Packagist release. `composer validate --strict` passes.

## Layout

- `src/Collection.php` — non-final, eager `array $items`, `new static()` everywhere to keep subclasses, `@phpstan-consistent-constructor`.
- `src/LazyCollection.php` — `final`, backed by `Closure(): iterable`, `new self()`; every transform wraps a generator.
- `src/Concerns/ComparesWhere.php` — `where()` predicate plus `valueFrom()`/`hasValue()`, the single key-extraction path used by `pluck/keyBy/groupBy/sortBy/unique/sum/where`. Change key semantics there, not per method.
- `benchmarks/bench.php` — dev-only comparison harness against `illuminate/collections`; not part of `composer ci`, needs a separate Illuminate checkout (see `BENCHMARKS.md`).
- `tests/Unit/*.php`, namespace `Daika7ana\SimpleCollections\Tests\Unit`.

## Deliberate divergences (do not "fix" toward Laravel)

- `pluck()` preserves original keys; `concat()` appends int keys and assigns string keys; `zip()` always wraps values and pads with `null`.
- `contains($value, $strict)`; `where()` supports only `=, ==, ===, !=, !==, <>, <, <=, >, >=` (loose `=`/`==`), no dot notation, no `like`/`whereIn`/`firstWhere`. Unsupported operators throw `InvalidArgumentException`.
- `where()` never matches an item that lacks the key (Laravel does) — keep this; it was a deliberate fix.
- `get()` with no argument returns all items, and `hasKey()` is an alias of `has()`: both exist only for the Ecolet helper's API. Keep them.
- `keyBy()` keeps rows with `null`/non-scalar keys under their original key; `groupBy()` buckets them under `''`.
- `LazyCollection`: no `ArrayAccess`, no `get()`/`has()`, no `sort*`; `chunk()` yields arrays; `groupBy()` buffers and yields `Collection` groups; negative limits are eager-only (eager `take`/`skip` read from the end; lazy `take` is empty, lazy `skip` is a no-op); non-positive `chunk()` yields nothing in both; `when()/unless()` return `$this` and callbacks get `($collection, $value)`. `Collection::when()/unless()` return a copy on the no-branch path — cannot return `$this`, phpstan rejects `$this` as `static` there.
- `unique()` is loose by default and O(n²); `sortBy()` is stable (PHP ≥ 8.0) — do not add a stability caveat.
- A generator-backed `LazyCollection` is effectively single-pass: once the source advances past its first value, later passes throw, so tests must not expect a second pass (only `take(1)`, callback-less `first()`, and `isEmpty()` are safe to repeat).

## Editing rules

- `composer ci` must pass before reporting done; never add phpstan ignores, baselines, `assert()`, inline `@var` overrides, or widening casts to silence errors.
- `Collection` methods that return `static` must build with `new static(...)`; aliases (`make`/`collect`) carry the generics docblock pattern so phpstan keeps `static<...>`.
- When behaviour changes, update `README.md` (it documents every divergence) and the corresponding test in the same pass.
- Formatting is Pint's `laravel` preset; do not hand-format against it.

## Risks already known (don't rediscover)

- Lazy single-use semantics are intentional and documented; `count()` uses `count()` for `Countable` sources and `iterator_count()` otherwise (no buffering), `all()` does not memoize.
- `toJson()` always ORs `JSON_THROW_ON_ERROR`; `toArray()`/`jsonSerialize()` are shallow.
- `Collection` accepts only iterables; `LazyCollection` accepts an iterable or a `Closure` returning one. `wrap()` is the scalar/null entry point.
