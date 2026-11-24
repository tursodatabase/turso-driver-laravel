---
title: Connection Types
description: In-memory, local file, remote Turso Cloud, and remote replica connection modes.
eyebrow: Connections
section: Connections
---

The driver supports four connection modes. All four are exercised by the
feature tests and/or the `docker/proof/` harness.

## In-memory

```env
DB_CONNECTION=libsql
DB_DATABASE=:memory:
```

Classic SQLite behavior in memory. Most feature tests use this mode.

## Local file

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
```

No auth token or sync URL needed. The file is created if it does not exist.
Local writes are used by the embedded replica flow and by
`LocalRemoteReplicaTest`.

## Remote (Turso Cloud)

```env
DB_CONNECTION=libsql
DB_AUTH_TOKEN=<token>
DB_SYNC_URL=libsql://<db-name>-<org>.turso.io
```

No local replica file is involved. Traffic goes over HTTPS to Turso Cloud.
A real cloud query round-trip was verified during the 1.7.0 release work.

## Remote replica (embedded replica)

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
DB_AUTH_TOKEN=<token>
DB_SYNC_URL=libsql://<db-name>-<org>.turso.io
DB_SYNC_INTERVAL=5
DB_READ_YOUR_WRITES=true
```

Reads hit the local file; writes go through the replica and sync with the
primary. Manual sync is explicit:

```php
use Illuminate\Support\Facades\DB;

DB::sync();
```

`DB::sync()` is valid only on the remote replica connection. On other modes it
errors. The `replica` phases of the Docker proof verify bootstrap pull, local
journaled writes, sync drain, and read-back from a second client.

## Notes

- `libsql://` is the scheme for Turso Cloud and implies HTTPS/TLS.
- A bare `http://` sync URL passed to `url` is parsed by Laravel as the driver
  name and can produce `Unsupported driver [http]`.
- Self-hosted sqld is supported through the offline-write configuration; see
  [Offline Writes]({{ '/user-manual/offline-writes/' | relative_url }}).
- Replication state can go stale. If you see WAL or frame conflicts, read
  [Troubleshooting]({{ '/user-manual/troubleshooting/' | relative_url }}).
