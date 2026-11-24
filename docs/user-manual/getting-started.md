---
title: Getting Started
description: Install the Laravel package, register the provider, and install the native libSQL PHP extension.
eyebrow: Start here
section: Getting started
---

## Requirements

- PHP 8.2, 8.3, 8.4, or 8.5
- Laravel 12 or 13 (Laravel 13 requires PHP 8.3+)
- Composer 2

The `libsql_php` extension is installed by this package's bundled installer,
so you do not need to compile it manually.

## 1. Install the package

```bash
composer require tursodatabase/turso-driver-laravel
```

## 2. Register the service provider

Laravel 11+ uses `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    Turso\Driver\Laravel\LibSQLDriverServiceProvider::class,
];
```

## 3. Install the extension

```bash
php artisan turso-php:install
```

The installer downloads the correct `.so`/`.dll` for your OS, PHP version, and
SAPI. Related lifecycle commands:

| Command | Purpose |
|---|---|
| `turso-php:install` | Install the extension |
| `turso-php:update` | Update the installed extension |
| `turso-php:uninstall` | Remove the installed extension |

Run `php artisan | grep turso-php` for the full command list. See
[Artisan Commands]({{ '/user-manual/commands/' | relative_url }}) for the grouped reference.

## 4. Configure a connection

Set `DB_CONNECTION=libsql` and add the `libsql` block shown in
[Configuration]({{ '/user-manual/configuration/' | relative_url }}). For a quick
local start, a file database is enough:

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
```

## 5. Verify the install

```bash
composer install
./vendor/bin/pest
php artisan db:show
```

`db:show` is overridden by this package to display libSQL platform metadata
when the active connection is `libsql`. See
[Verification]({{ '/user-manual/verification/' | relative_url }}) for CI and
Docker proof options.

## Next

Pick a [connection type]({{ '/user-manual/connection-types/' | relative_url }}),
then continue to [Basic Usage]({{ '/user-manual/basic-usage/' | relative_url }}).
