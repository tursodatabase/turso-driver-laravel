---
title: Offline Writes
description: Two opt-in offline-write flavors — Turso Cloud journaling and self-hosted sqld journal replay.
eyebrow: Connections
section: Connections
---

Offline-write support is **beta** in the libSQL PHP extension (1.7.0+) and
comes in **two flavors**. They are not interchangeable: each writes to a
different system and needs different setup.

## Flavor 1 — Turso Cloud (`remote_replica` mode)

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
DB_AUTH_TOKEN=<cloud-token>
DB_SYNC_URL=libsql://<db-name>-<org>.turso.io
DB_OFFLINE_WRITES=true
# Leave DB_SQLD_OFFLINE_MODE=false or omit it
```

Activation: `offlineWrites => true` without `sqldOfflineMode`.

Behavior: writes are journaled locally against the embedded replica.
`DB::sync()` pushes the journal to Turso Cloud.

```php
use Illuminate\Support\Facades\DB;

DB::sync();
```

### Fresh-replica ordering matters

On a **fresh** local replica file, call `DB::sync()` **before** any write.
Otherwise the local frames are uploaded with the wrong base index and the next
sync fails with:

```text
server returned a conflict: sent=1, got=17
```

Delete the replica file before re-seeding if you have already hit this state.

### Verification status

Verified live against a real Turso Cloud database during the 1.7.0 work:
bootstrap pull, journal push, remote read-back, and a clean-up `DROP TABLE`
through `sync()`. `tests/Feature/TursoCloudOfflineWriteTest.php` is skipped by
default in CI because no cloud credentials are available. Run it manually with:

```bash
TURSO_CLOUD_DB_URL=... TURSO_CLOUD_DB_TOKEN=... \
    ./vendor/bin/pest --filter=TursoCloudOfflineWriteTest
```

## Flavor 2 — Self-hosted sqld (`offline_write` mode)

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
DB_AUTH_TOKEN=<token>
DB_SYNC_URL=http://127.0.0.1:8080
DB_SQLD_OFFLINE_MODE=true
DB_OFFLINE_WRITES=true
```

Activation: both `sqldOfflineMode => true` and `offlineWrites => true`.

Behavior: writes apply to a local write journal. `DB::sync()` replays the
journal to sqld. The underlying connection exposes `isOnline()` and
`getPendingOperationsCount()`.

```php
DB::sync(); // drains the pending journal
```

The Docker proof verifies the outage window from the host side with an
independent Hrana client: sqld sees zero unsynced rows while the app is
offline, then holds the exact drained rows after reconnect.

## What is not supported

- Laravel 10/11.
- The Turso Cloud flavor against plain `libsql-server`. It speaks the
  HTTPS/libSQL protocol to Turso Cloud only.
- Raw `prepare()` in `offline_write` mode. The extension refuses it by design;
  the driver binds parameters and routes through `execute()`/`query()` instead.

## Extension limitations

- Offline-write support is beta upstream in the extension itself.
- DDL (`CREATE TABLE`) journals the same way as DML.
- `syncInterval: 0` disables automatic background sync in the sqld flavor;
  manual `DB::sync()` still works.

See [Known Limitations]({{ '/user-manual/known-limitations/' | relative_url }})
and [Troubleshooting]({{ '/user-manual/troubleshooting/' | relative_url }}) for
the failure signatures.
