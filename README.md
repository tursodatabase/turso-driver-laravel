> [!IMPORTANT]
> Offline writes are currently in **beta**. They are supported in this driver
> via the libSQL PHP extension v1.7.0 or newer (see *Offline-Write Modes*
> below). A new Laravel adapter that requires no PHP extension is also in
> development — follow along and share feedback on the
> [Turso Discord](https://discord.gg/9eAaMnJtfY).

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
  <a href="LICENSE">
    <picture>
      <img src="https://img.shields.io/github/license/tursodatabase/turso-driver-laravel?color=F05340" alt="MIT License" />
    </picture>
  </a>
  <a href="https://tur.so/discord-php">
    <picture>
      <img src="https://img.shields.io/discord/933071162680958986?color=F05340" alt="Discord" />
    </picture>
  </a>
  <a href="#contributors">
    <picture>
      <img src="https://img.shields.io/github/contributors/tursodatabase/turso-driver-laravel?color=F05340" alt="Contributors" />
    </picture>
  </a>
  <a href="https://packagist.org/packages/tursodatabase/turso-driver-laravel">
    <picture>
      <img src="https://img.shields.io/packagist/dt/tursodatabase/turso-driver-laravel?color=F05340" alt="Total downloads" />
    </picture>
  </a>
  <a href="https://github.com/tursodatabase/turso-driver-laravel/tags">
    <picture>
      <img src="https://img.shields.io/github/v/tag/tursodatabase/turso-driver-laravel?label=version&color=F05340" alt="Version Tag" />
    </picture>
  </a>
  <a href="https://github.com/tursodatabase/turso-driver-laravel/actions/workflows/tests.yml">
    <picture>
      <img src="https://github.com/tursodatabase/turso-driver-laravel/actions/workflows/tests.yml/badge.svg" alt="Testing" />
    </picture>
  </a>
</p>

<p align="center">
  SQLite for Production. Powered by <a href="https://turso.tech/libsql">libSQL</a> and the <a href="https://github.com/tursodatabase/turso-client-php/">libSQL PHP extension</a>.
</p>

<p align="center">
  libSQL is an open-contribution fork of SQLite. This package provides a
  first-party Laravel database driver built on the native libSQL PHP extension,
  with full support for Eloquent, the Query Builder, migrations, and
  transactions.
</p>

---

## Requirements

| Component | Requirement |
|---|---|
| PHP | ^8.2 (extension assets available for PHP 8.2 – 8.5) |
| Laravel | 10, 11, 12, or 13 |
| libSQL PHP extension | Installed automatically via Artisan (see below) |
| Turso PHP installer | 2.2+ (bundled with this package) |

## Installation

Install the package with Composer:

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

The package ships with `darkterminal/turso-php-installer`. Install the libSQL
PHP extension with Artisan:

```bash
php artisan turso-php:install
```

Manage the extension lifecycle with the bundled commands:

| Command | Description |
|---|---|
| `turso-php:install` | Install the libSQL PHP extension |
| `turso-php:update` | Update the installed extension |
| `turso-php:uninstall` | Remove the installed extension |

The installer also bundles environment, token, certificate, and `sqld`-server
management commands (`turso-php:env-*`, `turso-php:token-*`,
`turso-php:cert-*`, `turso-php:server-*`). Run
`php artisan | grep turso-php` for the full list, or read the
[command reference](https://darkstation.mintlify.app/dark-extensions/tools/installer/reference).

### Laravel Sail

Using [Laravel Sail](https://laravel.com/docs/sail)? Follow the step-by-step
guide: [Sailing with LibSQL](https://darkstation.mintlify.app/dark-extensions/tools/installer/platforms/laravel-sail).

## Configuration

The driver reads its configuration from your `.env` file. Laravel provides the
connection selection; the driver contributes the variables listed below.

### Environment Variables

**Laravel core**

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
```

-   **`DB_CONNECTION`** — selects the database connection (`libsql`, `sqlite`,
    `mysql`, `mariadb`, `pgsql`, `sqlsrv`).
-   **`DB_DATABASE`** — path to the database file, or `:memory:` for an
    in-memory database.

**LibSQL driver**

```env
DB_AUTH_TOKEN=<your-database-auth-token-from-turso>
DB_SYNC_URL=<your-database-url-from-turso>
DB_SYNC_INTERVAL=5
DB_READ_YOUR_WRITES=true
DB_ENCRYPTION_KEY=<your-encryption-key>
DB_SQLD_OFFLINE_MODE=false
DB_OFFLINE_WRITES=false
```

-   **`DB_AUTH_TOKEN`** — authentication token for the database. Generate one
    with `turso db tokens create <database-name>`, or from the
    [Turso Dashboard](https://dashboard.turso.io) by selecting your database
    and creating a token.
-   **`DB_SYNC_URL`** — database URL, assigned by Turso at creation time.
    Retrieve it with `turso db show --url <database-name>`.
-   **`DB_SYNC_INTERVAL`** — interval in seconds at which an embedded replica
    synchronizes with the primary database. **Default:** `5`.
-   **`DB_READ_YOUR_WRITES`** — makes writes from a connection immediately
    visible to subsequent reads on the same connection, preserving consistency
    in distributed systems. **Default:** `true`.
-   **`DB_ENCRYPTION_KEY`** — key for encrypting the database at rest. Passed
    directly to the libSQL extension (the v1.7.0 positional
    `encryption_key` argument) and effective at database-creation time.
    **Default:** empty.
-   **`DB_SQLD_OFFLINE_MODE`** — enables `sqld_offline_mode` for self-hosted
    `sqld` offline-write replicas (extension ≥ 1.7.0). **Default:** `false`.
-   **`DB_OFFLINE_WRITES`** — enables offline writes for Turso Cloud databases
    (extension ≥ 1.7.0). **Default:** `false`.

New to Turso? Create a database via the
[Turso quickstart](https://docs.turso.tech/quickstart).

### Connection Types

libSQL supports **four connection types**: *in-memory*, *local*, *remote*, and
*remote replica* (embedded replica).

<details>
  <summary><strong>In-Memory Connection</strong></summary>
  <p>To run libSQL fully in memory, set the following <code>.env</code> values:</p>
  <pre><code>DB_CONNECTION=libsql
DB_DATABASE=:memory:
  </code></pre>
</details>

<details>
  <summary><strong>Local Connection</strong></summary>
  <p>To use libSQL against a local database file, set the following <code>.env</code> values:</p>
  <pre><code>DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
  </code></pre>
  <p>All other LibSQL driver variables can be omitted.</p>
</details>

<details>
  <summary><strong>Remote Connection</strong></summary>
  <p>To use a remote-only connection, define the following <code>.env</code> variables:</p>
  <pre><code>DB_CONNECTION=libsql
DB_AUTH_TOKEN=&lt;your-database-auth-token-from-turso&gt;
DB_SYNC_URL=&lt;your-database-url-from-turso&gt;
  </code></pre>
</details>

<details>
  <summary><strong>Remote Replica Connection (Embedded Replica)</strong></summary>
  <p>To configure an embedded replica, define the following <code>.env</code> variables:</p>
  <pre><code>DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
DB_AUTH_TOKEN=&lt;your-database-auth-token-from-turso&gt;
DB_SYNC_URL=&lt;your-database-url-from-turso&gt;
DB_SYNC_INTERVAL=5
DB_READ_YOUR_WRITES=true
DB_ENCRYPTION_KEY=
DB_SQLD_OFFLINE_MODE=false
DB_OFFLINE_WRITES=false
  </code></pre>
  <p>Set `DB_OFFLINE_WRITES=true` (Turso Cloud) or
  `DB_SQLD_OFFLINE_MODE=true` (self-hosted sqld) when you need offline-write
  capability — both require libSQL PHP extension 1.7.0 or newer. See
  <strong>Offline-Write Modes</strong> below.</p>
</details>

### Offline-Write Modes (libSQL Extension ≥ 1.7.0)

Both modes are **opt-in** and default to `false`. They require libSQL PHP
extension **1.7.0+** — older extension versions ignore these keys silently.

| Env variable | Value | Target | Behavior |
|---|---|---|---|
| `DB_OFFLINE_WRITES` | `true` | **Turso Cloud** only | Writes queue locally and replay to the cloud when connectivity returns. |
| `DB_SQLD_OFFLINE_MODE` | `true` | **sqld** (self-hosted libsql-server) | Enables offline-write mode for self-managed libsql instances. |

> [!NOTE]
> These modes are **not** for plain `libsql-server` without the offline-write
> provider. `DB_OFFLINE_WRITES` targets Turso Cloud databases exclusively;
> `DB_SQLD_OFFLINE_MODE` targets self-hosted sqld replicas. Both need the
> driver's `sqldOfflineMode` / `offlineWrites` connection keys to be enabled
> (see the `config/database.php` snippet below).

### Database File Configuration

Add this block to `config/database.php` inside the `connections` array:

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

> Copy this block as-is. Arbitrary edits to the interpolation values can break
> the connection or cause unexpected behavior.

## Usage

Database operations use the standard `Illuminate\Support\Facades\DB`
interface. When connected via Remote Replica (embedded replica), the driver
adds a `DB::sync()` method for triggering a manual replica synchronization.

> [!IMPORTANT]
> **Write failures throw since v1.4.0** — failed `insert()`, `update()`,
> `delete()` calls raise a `PDOException` (Laravel wraps it as
> `QueryException`) instead of silently returning `false`. Wrap write
> operations in `try/catch` the same way you would with the SQLite driver.

```php
try {
    DB::table('users')->insert([
        'name' => 'John Doe',
        'email' => 'john.doe@gmail.com',
    ]);
} catch (\Illuminate\Database\QueryException $e) {
    // Example: "UNIQUE constraint failed: users.email"
    report($e);
}
```

```php
use Illuminate\Support\Facades\DB;

// Create
DB::table('users')->insert([
    'name' => 'Budi Dalton',
    'email' => 'budi.dalton@duck.com'
]);

// Read
DB::table('users')->get();
DB::table('users')->where('id', 2)->first();
DB::table('users')->orderBy('id', 'DESC')->limit(2)->get();

// Update
DB::table('users')->where('id', 2)->update(['name' => 'Doni Mandala']);

// Delete
DB::table('users')->where('id', 2)->delete();

// Transaction
try {
    DB::beginTransaction();

    $updated = DB::table('users')->where('id', 9)->update(['name' => 'Doni Kumala']);

    if ($updated) {
        echo "It's updated";
        DB::commit();
    } else {
        echo "Not updated";
        DB::rollBack();
    }

    $data = DB::table('users')->orderBy('id', 'DESC')->limit(2)->get();
    dump($data);
} catch (\Exception $e) {
    DB::rollBack();
    echo "An error occurred: " . $e->getMessage();
}

// Sync (Remote Replica / embedded replica only)
DB::sync();
```

## Testing

Install development dependencies and run the test suite:

```bash
composer install
./vendor/bin/pest
```

Tests that require a running libsql server are skipped automatically when no
server is available.

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
