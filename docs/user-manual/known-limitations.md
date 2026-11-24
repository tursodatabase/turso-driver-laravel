---
title: Known Limitations
description: Unsupported Laravel versions, beta offline writes, prepare() behavior, WAL conflicts, and blob downgrade caveat.
eyebrow: Operations
section: Operations
---

These limits are documented explicitly, not hidden.

## Laravel 10 and 11 are not supported

`darkterminal/turso-php-installer` requires `laravel/prompts ^0.3.5`, which is
incompatible with Laravel 10's `^0.1`. Both majors are EOL upstream. Use
Laravel 12 or 13.

## Offline writes are beta

Both flavors depend on `libsql_php` 1.7.0+, whose offline-write support is
itself beta. Upstream bugs can surface here as they appear in the extension.

## `prepare()` is refused in `offline_write` mode

The extension deliberately throws from `prepare()` in this mode so a prepared
statement cannot bypass the write journal. This driver works around it by
binding parameters and routing through `execute()`/`query()`. Avoid raw
`prepare()` calls in self-hosted sqld offline mode.

## Fresh Turso Cloud replica must sync before writing

A write made before the first `sync()` on a fresh replica uploads frames with
an incorrect base index. The next sync fails with a frame conflict. See
[Offline Writes]({{ '/user-manual/offline-writes/' | relative_url }}).

## Cloud flavor needs real Turso Cloud

It cannot be emulated locally and is not exercised in CI. The self-hosted sqld
flavor is the one the Docker proof verifies.

## URL scheme constraints

A plain `http://localhost:8080` value in `DB_SYNC_URL` is parsed by Laravel as
an unknown driver. Use `libsql://…` for Turso Cloud. Plain HTTP is only used
with the sqld offline-write configuration.

## Blob transport changed in 1.7.0

Blobs are written with an explicit `libsqlblob:` marker so reads decode them
unambiguously. Data written by 1.7.0+ needs 1.7.0+ to read back correctly;
older drivers surface the marker string. Unmarked data from earlier releases
is still decoded through the legacy double-base64 heuristic.

## No offline-write CI for the cloud flavor

The cloud flavor is verified manually, not on every CI run. Treat it as beta
with a manual proof step.
