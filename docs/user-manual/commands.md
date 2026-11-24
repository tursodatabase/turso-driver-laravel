---
title: Artisan Commands
description: Installer, token, sqld environment/runner, certificate, and database inspection commands.
eyebrow: Tooling
section: Usage
---

The service provider registers commands from `src/Commands/` automatically.
Run `php artisan | grep turso-php` to see the exact list in your installation.

## Extension lifecycle

| Command | Purpose |
|---|---|
| `turso-php:install` | Install the native libSQL PHP extension |
| `turso-php:update` | Update the installed extension |
| `turso-php:uninstall` | Remove the installed extension |

`install` supports `--unstable`, `--thread-safe`, `--php-ini=`,
`--php-version=`, and `--extension-dir=` when you need to target a specific
PHP configuration instead of the detected one.

## Local server tokens

| Command | Purpose |
|---|---|
| `turso-php:token-list` | List generated database tokens |
| `turso-php:token-create {db-name}` | Create a token (`--expire=` days, default 7) |
| `turso-php:token-show {db-name}` | Show tokens (`--fat`, `--roa`, `--pkp`, `--pkb`) |
| `turso-php:token-delete {db-name?}` | Delete tokens (`--all`, `-f\|--force`) |

## sqld environments and daemons

| Command | Purpose |
|---|---|
| `turso-php:env-create {name}` | Create a named sqld environment (`--variables=`, `--force`) |
| `turso-php:env-list` | List environments |
| `turso-php:env-show {name-or-id}` | Show one environment |
| `turso-php:env-edit {name-or-id}` | Edit an environment |
| `turso-php:env-delete {name-or-id}` | Delete an environment |
| `turso-php:server-check` | Check server requirements |
| `turso-php:server-run {env} {db-name}` | Run sqld (`-d\|--daemon`) |
| `turso-php:daemon-list` | List running daemons |
| `turso-php:daemon-kill {pid}` | Kill a daemon |
| `turso-php:open-db {env} {db-name}` | Open the database CLI |

## TLS certificates

| Command | Purpose |
|---|---|
| `turso-php:ca-cert-create {name=ca}` | Create a CA certificate (`--expiry=`, default 30 days) |
| `turso-php:ca-peer-cert-create {name}` | Create a peer certificate |
| `turso-php:ca-cert-list` | List CA certificates |
| `turso-php:ca-cert-show` | Show a CA certificate (`--raw`) |
| `turso-php:ca-cert-delete {name=ca}` | Delete a CA certificate |
| `turso-php:cert-store-get` | Show the certificate store path |
| `turso-php:cert-store-set {path?}` | Set the certificate store path |

## Database inspection

`db:show` is overridden by this package. When the active connection is
`libsql`, it adds author, support, and repository metadata to the usual
platform/table report.

```bash
php artisan db:show
php artisan db:show --counts --views --types
php artisan db:show --json
```

## Command discovery

`CommandFactory::collect()` scans `src/Commands/` recursively and registers
every loadable command class. Adding a command file under that directory is
enough; no manual provider edit is needed.
