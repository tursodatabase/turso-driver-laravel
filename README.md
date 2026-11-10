> [!IMPORTANT]
> Offline writes are currently in **beta** and come in **two flavors**: the
> journaled flavor for **Turso Cloud** (`DB_OFFLINE_WRITES=true` alone) and the
> sqld flavor for **self-hosted libsql-server** (`DB_SQLD_OFFLINE_MODE=true`
> **and** `DB_OFFLINE_WRITES=true`). Both are supported via the libSQL PHP
> extension v1.7.0+ (see [Offline Writes](https://github.com/tursodatabase/turso-driver-laravel/wiki/Offline-Writes) in the Wiki).

<p align="center">
  <a href="https://discord.gg/9eAaMnJtfY">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://i.imgur.com/UhuW3zm.png">
      <source media="(prefers-color-scheme: light)" srcset="https://i.imgur.com/vljWbfr.png">
      <img alt="Shows a black logo in light color mode and a white one in dark color mode." src="https://i.imgur.com/vGCC0I4.png">
    </picture>
  </a>
</p>

<p align="center">
  <img alt="Turso + Laravel" src="https://i.imgur.com/dpml6YK.png" width="1000">
</p>

<p align="center">
  <a href="LICENSE.md"><img src="https://img.shields.io/github/license/tursodatabase/turso-driver-laravel?color=F05340" alt="MIT License" /></a>
  <a href="https://tur.so/discord-php"><img src="https://img.shields.io/discord/933071162680958986?color=F05340" alt="Discord" /></a>
  <a href="#contributors"><img src="https://img.shields.io/github/contributors/tursodatabase/turso-driver-laravel?color=F05340" alt="Contributors" /></a>
  <a href="https://packagist.org/packages/tursodatabase/turso-driver-laravel"><img src="https://img.shields.io/packagist/dt/tursodatabase/turso-driver-laravel?color=F05340" alt="Total downloads" /></a>
  <a href="https://github.com/tursodatabase/turso-driver-laravel/tags"><img src="https://img.shields.io/github/v/tag/tursodatabase/turso-driver-laravel?label=version&color=F05340" alt="Version Tag" /></a>
  <a href="https://github.com/tursodatabase/turso-driver-laravel/actions/workflows/tests.yml"><img src="https://github.com/tursodatabase/turso-driver-laravel/actions/workflows/tests.yml/badge.svg" alt="Testing" /></a>
</p>

<p align="center">
  SQLite for Production. Powered by <a href="https://turso.tech/libsql">libSQL</a> and the <a href="https://github.com/tursodatabase/turso-client-php/">libSQL PHP extension</a>.
</p>

<p align="center">
  libSQL is an open-contribution fork of SQLite. This package provides a
  Laravel database driver built on the native libSQL PHP extension; Eloquent,
  the Query Builder, migrations and transactions are exercised by the test
  suite and the <code>docker/proof/</code> integration harness.
</p>

---

## Requirements

| Component | Requirement |
|---|---|
| PHP | 8.2, 8.3, 8.4 or 8.5 |
| Laravel | 12 or 13 (13 requires PHP 8.3+) |
| libSQL PHP extension | installed automatically via Artisan (see below) |
| Turso PHP installer | 2.2+ (bundled) |

## Installation

```bash
composer require tursodatabase/turso-driver-laravel
```

Register the service provider in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Turso\Driver\Laravel\LibSQLDriverServiceProvider::class, // add this line
];
```

Install the libSQL PHP extension (bundled with this package):

```bash
php artisan turso-php:install
```

The installer also provides `turso-php:update` and `turso-php:uninstall`, plus
environment, token, certificate and `sqld`-server management commands — see
`src/Commands/` for the implementations, or `php artisan | grep turso-php`
for the full list.

## Configuration

The driver reads its settings from `.env`:

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite   # or :memory:

DB_AUTH_TOKEN=<your-database-auth-token-from-turso>
DB_SYNC_URL=<your-database-url-from-turso>
DB_SYNC_INTERVAL=5
DB_READ_YOUR_WRITES=true
DB_ENCRYPTION_KEY=<your-encryption-key>
DB_SQLD_OFFLINE_MODE=false
DB_OFFLINE_WRITES=false
```

And the `connections` block of `config/database.php`:

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

**All options, defaults and their meaning are documented on the**
[**Wiki · Configuration**](https://github.com/tursodatabase/turso-driver-laravel/wiki/Configuration).

## Connection types

libSQL supports four connection types — **in-memory**, **local**, **remote**,
and **remote replica** (embedded replica). Examples and verified setups are on
the [Wiki · Connection Types](https://github.com/tursodatabase/turso-driver-laravel/wiki/Connection-Types).

## Usage

Database operations use the standard `Illuminate\Support\Facades\DB`
interface. When connected via remote replica (embedded replica), the driver
adds a `DB::sync()` method for triggering a manual replica sync.

Write failures **throw** since v1.4.0 — wrap writes in try/catch as you would
with the SQLite driver:

```php
try {
    DB::table('users')->insert(['name' => 'Budi Dalton', 'email' => 'budi.dalton@duck.com']);
} catch (\Illuminate\Database\QueryException $e) {
    report($e);
}
```

Query Builder, Eloquent (relations, casts, soft deletes, collections),
migrations, transactions, the schema builder, vector types and encryption at
rest are all covered by the Pest suite and the `docker/proof/` harness.

For the full tour — Eloquent examples, vector index syntax, offline-write
bootstrap, `DB::sync()` semantics and more — see
the [Wiki · Basic Usage](https://github.com/tursodatabase/turso-driver-laravel/wiki/Basic-Usage).

## Offline-write modes

Two opt-in flavors (both need libSQL Extension ≥ 1.7.0):

| Env variable | Value | Flavor |
|---|---|---|
| `DB_OFFLINE_WRITES` | `true` | Turso Cloud (beta) — `remote_replica` mode, `DB::sync()` pushes to the cloud |
| `DB_SQLD_OFFLINE_MODE` + `DB_OFFLINE_WRITES` | both `true` | Self-hosted sqld — `offline_write` mode, journal replay via `DB::sync()` |

The Turso Cloud flavor bootstraps from the cloud on first sync — see
[Wiki · Offline Writes](https://github.com/tursodatabase/turso-driver-laravel/wiki/Offline-Writes) for the required ordering and verified output.

## Documentation

The full user manual & tutorial lives on the **[Wiki](https://github.com/tursodatabase/turso-driver-laravel/wiki)**:

- [Installation](https://github.com/tursodatabase/turso-driver-laravel/wiki/Installation)
- [Configuration](https://github.com/tursodatabase/turso-driver-laravel/wiki/Configuration)
- [Connection Types](https://github.com/tursodatabase/turso-driver-laravel/wiki/Connection-Types)
- [Basic Usage](https://github.com/tursodatabase/turso-driver-laravel/wiki/Basic-Usage)
- [Offline Writes (two flavors)](https://github.com/tursodatabase/turso-driver-laravel/wiki/Offline-Writes)
- [Verification & Proof Harness](https://github.com/tursodatabase/turso-driver-laravel/wiki/Verification)
- [Known Limitations](https://github.com/tursodatabase/turso-driver-laravel/wiki/Known-Limitations)
- [Troubleshooting](https://github.com/tursodatabase/turso-driver-laravel/wiki/Troubleshooting)

## Testing

```bash
composer install
./vendor/bin/pest
```

Server-dependent tests are skipped by default; they run in CI against a
seeded `libsql-server`. See [Wiki · Verification](https://github.com/tursodatabase/turso-driver-laravel/wiki/Verification).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed
recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](https://github.com/tursodatabase/turso-driver-laravel/security/policy)
on how to report security vulnerabilities.

## Contributors

![Contributors](https://contrib.nn.ci/api?no_bot=true&repo=tursodatabase/turso-driver-laravel)

## License

Licensed under the MIT License. See [LICENSE.md](LICENSE.md) for details.

<!-- GitAds-Verify: QMVSQYBI166XXYYWVHF4E5PWL9UJFRL8 -->
