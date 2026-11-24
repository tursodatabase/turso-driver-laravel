---
title: Basic Usage
description: Query Builder, Eloquent, migrations, transactions, write errors, vectors, and manual sync.
eyebrow: Usage
section: Usage
---

Everything below is exercised by `tests/Feature/*` or the `docker/proof/`
integration harness.

## Query Builder

```php
use Illuminate\Support\Facades\DB;

// insert
DB::table('users')->insert([
    'name' => 'Budi Dalton',
    'email' => 'budi.dalton@duck.com',
]);

// read
DB::table('users')->get();
DB::table('users')->where('id', 2)->first();
DB::table('users')->orderBy('id', 'DESC')->limit(2)->get();

// update
DB::table('users')->where('id', 2)->update(['name' => 'Doni Mandala']);

// delete
DB::table('users')->where('id', 2)->delete();
```

## Eloquent

Models work the standard way. The suite covers `HasMany`, `HasManyThrough`,
`BelongsToMany`, attribute casts (array, bool, int, float, string, date,
datetime, enum), soft deletes, collections, and deletes.

```php
$user = User::query()->create([
    'name' => 'Budi Dalton',
    'email' => 'budi.dalton@duck.com',
]);

$user->posts()->create(['title' => 'Hello libSQL']);
```

## Migrations

```bash
php artisan make:migration create_movies_table
php artisan migrate
```

The Docker proof runs `php artisan migrate --force` through the driver, so the
full Artisan migration path is verified end to end.

## Transactions

```php
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    DB::table('users')->insert(['name' => 'Budi Dalton']);
    DB::table('profiles')->insert(['user_id' => DB::getPdo()->lastInsertId()]);

    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    throw $e;
}
```

Covered by `DatabaseTransactionsTest` and integer data tests.

## Write failures throw

Since 1.4.0, failed `insert()`, `update()`, and `delete()` calls throw
`PDOException`, which Laravel wraps as `QueryException`. They no longer return
`false`.

```php
try {
    DB::table('users')->insert([
        'email' => 'duplicate@example.com',
    ]);
} catch (\Illuminate\Database\QueryException $e) {
    report($e);
}
```

## Vector types and nearest-neighbour search

libSQL vector values are supported. The package registers a `vectorIndex()`
schema macro and a `nearest()` query macro:

```php
Schema::table('documents', function (Blueprint $table) {
    $table->vectorIndex('embedding', 'documents_embedding_idx');
});

$neighbours = DB::table('documents')
    ->nearest('documents_embedding_idx', $embedding, 10)
    ->get();
```

Vector casts are available for Eloquent through
`src/Database/Casts/VectorCast.php`.

## Streaming cursor

`cursor()` streams rows through the same decoding path as `select()`:

```php
foreach (DB::table('events')->cursor() as $event) {
    // process one row at a time
}
```

The 1.7.0 path fixes blob transport with an explicit `libsqlblob:` marker and
keeps plain alphabetic text from being decoded twice.

## Manual sync

On remote replica connections:

```php
DB::sync();
```

See [Connection Types]({{ '/user-manual/connection-types/' | relative_url }})
and [Offline Writes]({{ '/user-manual/offline-writes/' | relative_url }}) for
when and why you need it.
