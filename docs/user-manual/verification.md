---
title: Verification
description: Pest suite, Docker proof harness, CI matrix, and what CI does not prove.
eyebrow: Operations
section: Operations
---

The repository ships its own verification story. Everything on this page is
reproducible by anyone with Docker and this repository.

## Test suite (Pest + PHPUnit)

For 1.7.0 the suite is 178 tests: 171 default tests against `:memory:`, six
server-dependent tests against two seeded `libsql-server` instances on ports
8081 and 8082, and one opt-in Turso Cloud offline-write test. CI result:
**177 passed, 1 skipped, 556 assertions**, identical on all seven legs.

```bash
composer install
./vendor/bin/pest
```

Server-dependent tests are skipped by default. Enable them when two sqld
instances are running on `127.0.0.1:8081` and `127.0.0.1:8082`:

```bash
TURSO_SQLD_AVAILABLE=true ./vendor/bin/pest
```

Coverage includes data types, Query Builder, Eloquent relationships/casts/soft
deletes/collections, transactions, vector types, schema operations,
multi-connection behavior, and replica flows.

## Docker integration proof

`docker/proof/` builds a fresh Laravel 12 app, installs the driver from the
tree under review, and proves it through HTTP, a web tier, a real outage, and
sync.

| Phase | Asserts | What is proven |
|---|---|---|
| `ProofRun` | 42 | Connection modes, remote reads, replica, transactions, second client, encryption at rest, write-failure rethrow |
| `ProofHttp` | 8 | nginx → php-fpm boot, remote read, write failure surfacing as HTTP 500 |
| `ProofOfflineWrite` | 9 | `isOnline()` false during outage, journal captures DDL + inserts, server sees zero unsynced rows |
| `ProofOfflineSync` | 5 | `isOnline()` true after reconnect, `sync()` drains journal, server holds exact rows |
| `proof:offline-cloud` | 12 | Turso Cloud bootstrap, journal, push, remote read, pushed drop (opt-in) |

```bash
docker/proof/proof.sh
```

Reports are written to `docker/proof/proof-report/` (gitignored). The outage
window is checked from the host side by an independent Hrana client.

## CI matrix

`.github/workflows/tests.yml`:

| Laravel | PHP |
|---|---|
| 12 | 8.2, 8.3, 8.4, 8.5 |
| 13 | 8.3, 8.4, 8.5 |

Every leg provisions two seeded `libsql-server` services.

## Not proven by CI

- Turso Cloud offline-write flavor: needs real cloud credentials and is run
  manually.
- Encryption at rest: verified inside `docker/proof/`, not as a standalone
  assertion across the PHP/Laravel matrix.

See [Offline Writes]({{ '/user-manual/offline-writes/' | relative_url }}) for
the cloud-flavor manual command.
