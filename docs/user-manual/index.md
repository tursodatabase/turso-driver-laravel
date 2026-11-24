---
title: User Manual
description: Source-of-truth documentation for the native libSQL database driver for Laravel.
eyebrow: Turso Driver Laravel
section: Documentation
---

## What this package is

`tursodatabase/turso-driver-laravel` is a Laravel database driver backed by the
native `libsql_php` extension. It adds a `libsql` connection to Laravel's
database layer, so Eloquent, the Query Builder, migrations, schema operations,
and transactions keep working the way you already use them.

It is **not** an ORM replacement and **not** a pure-PHP HTTP client. The
extension is installed by the bundled Artisan installer, and the driver wires
that extension into Laravel.

## Compatibility

Verified by the repository CI matrix (`1.7.0`):

| Component | Requirement |
|---|---|
| PHP | 8.2, 8.3, 8.4, 8.5 |
| Laravel | 12 or 13 (13 requires PHP 8.3+) |
| `libsql_php` extension | 1.7.0+, installed automatically via Artisan |
| Turso PHP installer | 2.2+, bundled with the package |

Laravel 10 and 11 are not supported. They are EOL upstream and conflict with
the installer's dependencies.

## Where to go next

- [Getting Started]({{ '/user-manual/getting-started/' | relative_url }}) — install the package and extension.
- [Configuration]({{ '/user-manual/configuration/' | relative_url }}) — `.env` variables and `config/database.php`.
- [Connection Types]({{ '/user-manual/connection-types/' | relative_url }}) — in-memory, local, remote, and replica.
- [Basic Usage]({{ '/user-manual/basic-usage/' | relative_url }}) — Query Builder, Eloquent, migrations, transactions, vectors.
- [Offline Writes]({{ '/user-manual/offline-writes/' | relative_url }}) — the two opt-in flavors and their ordering rules.
- [Artisan Commands]({{ '/user-manual/commands/' | relative_url }}) — installer, token, sqld, and certificate commands.
- [Verification]({{ '/user-manual/verification/' | relative_url }}) — what the test suite and proof harness actually prove.
- [Known Limitations]({{ '/user-manual/known-limitations/' | relative_url }}) and [Troubleshooting]({{ '/user-manual/troubleshooting/' | relative_url }}).

## Source of truth

This manual is the source of truth for package documentation. The GitHub wiki
is kept as a legacy reference; when the two disagree, trust this manual and
the repository tests.
