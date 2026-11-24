---
title: Troubleshooting
description: Real failure signatures from this repository and the fixes that worked.
eyebrow: Operations
section: Operations
---

Only failures that actually happened in this repository are listed here.

## `Can not sync a database without a wal_index, please delete the database and attempt again`

**Cause:** the local replica file is left over from a previous run, or the
migration that creates the server-side schema has not run on a fresh sqld.

**Fix:** delete the local replica file and retry. The proof harness always
wipes its volumes; CI seeds sqld with a `_boot` table so a new server can
build WAL metadata.

## `server returned a conflict: sent=1, got=17`

**Cause:** writes were made to a fresh local replica before the first
`DB::sync()`, or a stale WAL sidecar remains.

**Fix:** call `DB::sync()` once on a fresh replica **before** any write.
Delete the replica file before re-seeding.

## `Unsupported driver [http]`

**Cause:** `DB_SYNC_URL=http://host:port` in `.env`. Laravel parses the `url`
config value and uses its scheme as the driver.

**Fix:** use `libsql://…` for Turso Cloud. For self-hosted sqld, combine the
plain HTTP URL with `DB_SQLD_OFFLINE_MODE=true` as shown in
[Offline Writes]({{ '/user-manual/offline-writes/' | relative_url }}).

## Writes silently dropped or `false` returned

**Symptom:** on versions before 1.4.0, failed `INSERT`/`UPDATE`/`DELETE`
returned `false` instead of throwing.

**Fix:** upgrade to 1.4.0 or newer. Failed writes now throw `PDOException`,
wrapped by Laravel as `QueryException`.

## Replica tests pass locally but fail in CI (or vice versa)

**Cause:** `LocalRemoteReplicaTest` and `MultiConnectionTest` share a running
sqld. A crashed earlier run leaves stale files; CI starts fresh.

**Fix:** start a fresh sqld instance, or run `docker/proof/proof.sh`, which
wipes volumes first.

## Encryption config seems ignored

**Cause:** `DB_ENCRYPTION_KEY` is applied at database creation time only.

**Fix:** create a fresh database file with the key set. An existing
unencrypted file cannot be converted retroactively.

## `syncInterval: 0` means no automatic sync

This is intentional in the sqld flavor. It disables background sync;
`DB::sync()` still drains the journal manually.

## Text reads back as binary garbage

**Symptom:** a stored word such as `bzwobkku` comes back as two garbage bytes
on 1.6.0 or older.

**Cause:** the old driver treated any printable, canonical base64-looking
string as a double-encoded blob.

**Fix:** upgrade to 1.7.0+. Blob transfers now use an explicit
`libsqlblob:` marker and plain alphabetic text is never double-decoded.

## Still stuck?

Open an issue with your PHP version, Laravel version, extension version,
connection block (with tokens removed), and the exact error text:
https://github.com/tursodatabase/turso-driver-laravel/issues
