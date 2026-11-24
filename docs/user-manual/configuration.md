---
title: Configuration
description: Environment variables, the libsql connection block, and multiple connection rules.
eyebrow: Setup
section: Getting started
---

All driver-specific configuration lives in `.env` and the `connections` block
of `config/database.php`.

## Core Laravel variables

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite   # or :memory:
```

## Driver variables

```env
DB_AUTH_TOKEN=<your-database-auth-token-from-turso>
DB_SYNC_URL=<your-database-url-from-turso>
DB_SYNC_INTERVAL=5
DB_READ_YOUR_WRITES=true
DB_ENCRYPTION_KEY=<your-encryption-key>
DB_SQLD_OFFLINE_MODE=false
DB_OFFLINE_WRITES=false
```

| Variable | Meaning | Default | Verified by |
|---|---|---|---|
| `DB_AUTH_TOKEN` | Turso Cloud database token | empty | remote / replica tests |
| `DB_SYNC_URL` | Remote database URL | empty | remote / replica tests |
| `DB_SYNC_INTERVAL` | Replica sync interval in seconds | `5` | embedded replica tests |
| `DB_READ_YOUR_WRITES` | Writes visible to later reads on the same connection | `true` | embedded replica tests |
| `DB_ENCRYPTION_KEY` | Encrypts the database at rest at creation time | empty | Docker proof `encryption` phase |
| `DB_SQLD_OFFLINE_MODE` | Enable the self-hosted sqld offline flavor | `false` | Docker proof + `MultiConnectionTest` |
| `DB_OFFLINE_WRITES` | Enable an offline-write flavor | `false` | replica + cloud offline tests |

## `config/database.php`

Add this block to the `connections` array:

```php
'libsql' => [
    'driver' => 'libsql',
    'database' => env('DB_DATABASE', database_path('database.sqlite')),
    'prefix' => '',
    'url' => env('DB_SYNC_URL', ''),
    'authToken' => env('DB_AUTH_TOKEN', ''),
    'syncInterval' => env('DB_SYNC_INTERVAL', 5),
    'read_your_writes' => env('DB_READ_YOUR_WRITES', true),
    'encryptionKey' => env('DB_ENCRYPTION_KEY', ''),
    'sqldOfflineMode' => env('DB_SQLD_OFFLINE_MODE', false),
    'offlineWrites' => env('DB_OFFLINE_WRITES', false),
],
```

Copy the block as-is. Arbitrary extra keys or renaming existing ones can break
the connection because the connector reads these exact names.

## Encryption caveat

`DB_ENCRYPTION_KEY` is applied when the database file is **created**. Setting
it on an existing unencrypted file does not retroactively encrypt that file.
The Docker proof verifies encryption on a freshly created database.

## Multiple libSQL connections

Since 1.6.0, every statically configured connection with `driver => libsql` is
extended by name with its own raw configuration. Connections no longer reuse
the default connection's settings.

```php
'connections' => [
    'libsql' => [ /* ... */ ],
    'libsql_analytics' => [
        'driver' => 'libsql',
        'database' => database_path('analytics.sqlite'),
        'prefix' => '',
        'url' => env('ANALYTICS_SYNC_URL', ''),
        'authToken' => env('ANALYTICS_AUTH_TOKEN', ''),
    ],
],
```

This behavior is covered by `tests/Feature/MultiConnectionTest.php`.

## URL scheme

Use `libsql://…` for Turso Cloud URLs. Laravel treats the scheme of a `url`
config value as the driver name, so a bare `http://…` sync URL can surface as
`Unsupported driver [http]`. The self-hosted sqld offline flavor is the
exception; see [Offline Writes]({{ '/user-manual/offline-writes/' | relative_url }}).
