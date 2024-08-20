<p align="center">
  <a href="https://docs.turso.tech/sdk/php/guides/laravel">
    <img alt="Turso + Laravel" src="https://i.imgur.com/T2pzJid.png" width="1000">
    <h3 align="center">Turso + Laravel</h3>
  </a>
</p>

<p align="center">
  SQLite for Production. Powered by <a href="https://turso.tech/libsql">libSQL</a> and <a href="https://github.com/tursodatabase/turso-driver-laravel">libSQL Extension</a> for PHP.
</p>

<p align="center">
  <a href="https://turso.tech"><strong>Turso</strong></a> ·
  <a href="https://docs.turso.tech/sdk/php/guides/laravel"><strong>Quickstart</strong></a> ·
  <a href="https://docs.turso.tech/sdk/php/guides/laravel"><strong>Examples</strong></a> ·
  <a href="https://docs.turso.tech/sdk/php/guides/laravel"><strong>Docs</strong></a> ·
  <a href="https://discord.com/invite/4B5D7hYwub"><strong>Discord</strong></a> ·
  <a href="https://blog.turso.tech/"><strong>Blog &amp; Tutorials</strong></a>
</p>

---

<p align="center">
  <a href="https://tur.so/dt" target="_blank">🚀 I'll give you 10% off Turso Scaler and Pro for 1 Year 🚀</a>
</p>

---

<h1 id="a-libsql-driver-for-laravel" align="center">A LibSQL Driver for Laravel</h1>

LibSQL is a fork of SQLite and this package is **#1 LibSQL Driver** that run natively using LibSQL Native Extension/Driver/Whatever and support Laravel Ecosystem.

## Requirement

Before using this package, you need to install and configure LibSQL Native Extension for PHP. You can download from [LibSQL Extension - Release](https://github.com/tursodatabase/turso-driver-laravel)

1. Download based on you distribution (Linux/Macos/Darwin)
2. The archive is contains the extension and `libsql_php_extension.stubs.php`
3. Save the extension file in your desire directory
4. Save the `libsql_php_extension.stubs.php` in your project or somewhare that can help you to use stand-alone LibSQL driver as an IDE helper. By default this stubs is come inside this package.
5. Configure `php.ini` file and added the extension address with relative path that pointed to the extension
6. Enjoy!

or you can use [Turso PHP Install](https://github.com/darkterminal/turso-php-installer) (Linux and MacOS Only) to install libSQL Extension for PHP without worry.

## Installation

You can install the package via composer:

```bash
composer require tursodatabase/turso-driver-laravel
```

Then register the service provider at `bootstrap/providers.php` array:

```php
return [
    App\Providers\AppServiceProvider::class,
    Turso\Driver\Laravel\LibSQLDriverServiceProvider::class, // Here
];
```

## Environment Variable Overview

You need to know the additional configuration in `.env` file, which come from Laravel and which come from LibSQL Driver. And here is the overview of `.env`:

**Laravel**

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
```

- `DB_CONNECTION` key is represent default database connection like `libsql`, `sqlite`, `mysql`, `mariadb`, `pgsql`, and `sqlsrv`.
- `DB_DATABASE` key is represent the location of database name or in this case is database filename.

**LibSQL Driver**

```env
DB_AUTH_TOKEN=<your-database-auth-token-from-turso>
DB_SYNC_URL=<your-database-url-from-turso>
DB_SYNC_INTERVAL=5
DB_READ_YOUR_WRITES=true
DB_ENCRYPTION_KEY=
DB_REMOTE_ONLY=false
```

Create a new Turso Database [here](https://docs.turso.tech/quickstart)

- `DB_AUTH_TOKEN` - You can generate using `turso db tokens create <database-name>` command or you can visit your Turso Dashboard and select database you want to used and generate the token from there.
- `DB_SYNC_URL` - This generate by Turso when you craete a new database, you can get the database URL by using this command `turso db show --url <database-name>`
- `DB_SYNC_INTERVAL` - This variable defines the interval at which an embedded replica synchronizes with the primary database. It sets a duration for automatic synchronization of the database in the background. When configured, the embedded replica will periodically sync its local state with the state of the primary database to ensure it has the latest data. This is particularly useful for ensuring that replicas remain up-to-date with minimal manual intervention. Default is: 5 seconds.
- `DB_READ_YOUR_WRITES` - This variable configures the database connection to ensure that writes made by a connection are immediately visible to subsequent read operations initiated by the same connection. This is important in distributed systems to ensure consistency from the perspective of the writing process. When enabled, after a write operation is performed, any reads that follow from the same connection will see the results of that write. **This option is typically enabled by default** to ensure that clients always see their latest writes.
- `DB_ENCRYPTION_KEY` - This variable is defined for specifying the encryption key used in database encryption. It represents the secret key that is used to encrypt and decrypt the database content, ensuring that the data stored in the database is protected and can only be accessed by individuals who possess the correct key. This key is a critical component of encryption-at-rest strategies, where the goal is to secure data while it is stored on disk, preventing unauthorized access. Default is: empty.
- `DB_REMOTE_ONLY` - This variable is define to use remote connection only, if you only want to read and write the database from remote database. Default: false.

## Configure The Connection

LibSQL has 3 types of connections to interact with the database: _In-Memory Connection_, _Local Connection_, _Remote Connection_, and _Remote Replica Connection (Embedded Replica)_

### In-Memory Connection

To be able to use LibSQL in-memory as if you were using SQLite, simply change the following `.env`:
```env
DB_CONNECTION=libsql
DB_DATABASE=:memory:
```

### Local Connection

To be able to use LibSQL locally as if you were using SQLite, simply change the following `.env`:
```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
```

Ignore other LibSQL `.env` variables.

### Remote Connection

To use LibSQL Remote Connection only, you can define the following `.env` variables:
```env
DB_CONNECTION=libsql
DB_AUTH_TOKEN=<your-database-auth-token-from-turso>
DB_SYNC_URL=<your-database-url-from-turso>
DB_REMOTE_ONLY=true
```

### Remote Replica Connection (Embedded Replica)

To configure remote replica connection (embedded replica), you can simply use the following `.env`:
```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
DB_AUTH_TOKEN=<your-database-auth-token-from-turso>
DB_SYNC_URL=<your-database-url-from-turso>
DB_SYNC_INTERVAL=5
DB_READ_YOUR_WRITES=true
DB_ENCRYPTION_KEY=
DB_REMOTE_ONLY=false
```

That's it! How easy to make different connection using LibSQL Driver in Laravel, right?!

## Database Configuration

Add this configuration at `config/database.php` inside the `connections` array:

```php
'libsql' => [
    'driver' => 'libsql',
    'url' => env('DB_DATABASE', database_path('database.sqlite')),
    'authToken' => env('DB_AUTH_TOKEN', ''),
    'syncUrl' => env('DB_SYNC_URL', ''),
    'syncInterval' => env('DB_SYNC_INTERVAL', 5),
    'read_your_writes' => env('DB_READ_YOUR_WRITES', true),
    'encryptionKey' => env('DB_ENCRYPTION_KEY', ''),
    'remoteOnly' => env('DB_REMOTE_ONLY', false),
    'database' => null,
    'prefix' => '',
],
```

> Copy and Paste and do not change it! Or try to change it and will broke your app or give you malfunction.

## Usage

For database operation usage, everything have same interface like usual when you using `Illuminate\Support\Facades\DB` in your database model. But remember, this is LibSQL they have `sync` method that can be used when you connect with Remote Replica Connection (Embedded Replica).
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

// Sync
DB::sync();
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Imam Ali Mustofa](https://github.com/darkterminal)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
