# Isolated Proof of Usage — Turso Driver for Laravel

**Try the driver before you install it.** This harness boots a real Laravel
application against a live libSQL server — entirely inside Docker — installs
the driver the same way you would in your own app, and asserts that it works
end to end: through the console (`php artisan`), through a real web tier
(**nginx → php-fpm → Laravel → the driver**), and through an **offline-write
outage window** (`docker network disconnect`, journaling, drain). Nothing is
installed on your host beyond Docker itself.

If you are evaluating whether this package fits your stack, this is the
fastest, most honest way to find out. It is also used by maintainers as a
release-to-release integration check.

## Prerequisites

| Requirement | Why |
|---|---|
| Docker with Compose v2 | Runs the whole proof; nothing else needed |
| ~2 GB free disk | First build downloads PHP + Laravel + the extension payload |
| Internet access at build time | `composer`, GitHub release downloads, Packagist |

No local PHP, no local Composer, no libSQL extension on your machine.

## Quick start

```bash
git clone https://github.com/tursodatabase/turso-driver-laravel.git
cd turso-driver-laravel
docker/proof/proof.sh
```

The script will:

1. build a fresh image (`php:8.3-fpm` + Laravel 12 + this driver from the
   checkout you just cloned);
2. start `ghcr.io/tursodatabase/libsql-server` (pinned as `latest`) on
   port **8081** (override with `SQLD_PORT`);
3. wait for the server to accept Hrana traffic, install the libSQL extension
   inside the app container (`php artisan turso-php:install -n` — the exact
   flow you will run in your own app);
4. run `php artisan migrate --force` through the driver (real artisan flow);
5. run `php artisan proof:run` and print a one-line `PROOF: {...}` summary;
6. bring up the web tier (nginx → php-fpm) on port **8080** (override with
   `HTTP_PORT`) and run `php artisan proof:http`, printing `PROOF-HTTP: {...}`;
7. **cut the app ↔ sqld link** (`docker network disconnect` on the server-net
   only) and run `php artisan proof:offline-write` — writes must journal
   locally while the server stays blind (the web tier keeps serving);
8. **reconnect** and run `php artisan proof:offline-sync` — `sync()` must
   drain the journal; a second Hrana client (from the host) reads the
   journaled rows back from the server;
9. write JSON reports to `docker/proof/proof-report/` (gitignored — never
   commit them).

Expected ending (assert counts may vary):

```
PROOF: {"ok":N,"fail":0,"all_ok":true}
PROOF-HTTP: {"ok":M,"fail":0,"all_ok":true}
PROOF-OFFLINE-WRITE: {"ok":P,"fail":0,"all_ok":true}
PROOF-OFFLINE-SYNC: {"ok":Q,"fail":0,"all_ok":true}
PROOF-OFFLINE-CLOUD: {"ok":R,"fail":0,"all_ok":true}   # only with TURSO_CLOUD_DB_URL/TOKEN set
```

Finish with a clean slate (containers **and** volumes removed):

```bash
docker/proof/proof.sh --teardown
```

## What the proof asserts

| # | Phase | What it proves |
|---|-------|----------------|
| 1 | `health` | The extension loads inside the app; the driver's service provider is registered; the `LibSQL` class is available |
| 2 | `memory_local` | `:memory:` and local-file connections round-trip data (insert/select/update/delete) through Eloquent & Query Builder |
| 3 | `remote` | The remote connection talks Hrana to a real libSQL server: 50 inserts, count, update, read-back, delete |
| 4 | `replica` | An embedded replica pulls with `DB::sync()` (`sync()` via the default connection forwards to it) — reads local, write local, push upstream |
| 5 | `transaction` | `DB::beginTransaction()`/`commit()`/`rollBack()` behave as expected |
| 6 | `second_client` | A second, independent Hrana client (raw HTTP, no shared state with the driver) reads what the driver wrote to the server |
| 7 | `encryption` | `DB_ENCRYPTION_KEY` encrypts at rest (driver ≥ 1.4.0): the on-disk file contains **no** plaintext marker, and the row decrypts on re-open with the same key |
| 8 | `write_failures` | Failed writes throw a `QueryException` instead of returning `false` (driver ≥ 1.4.0, fixes #113) |

### HTTP/fpm phases (`php artisan proof:http`)

Driven through **nginx → php-fpm** (curl from inside the app container to the
web tier — a separate process from the CLI asserts):

| # | Phase | What it proves |
|---|-------|----------------|
| 1 | `fpm_boot` | php-fpm (SAPI `fpm-fcgi`, not cli) boots Laravel with the extension loaded; the default local libsql file answers queries through HTTP |
| 2 | `remote_over_http` | An HTTP request reaches the live libSQL server through the driver and reads back the 50 rows written by the console phases |
| 3 | `throw_over_http` | A duplicate unique insert over HTTP surfaces as HTTP 500 with the debug page showing the `UNIQUE constraint failed` message (Laravel maps the driver's `QueryException` to `UniqueConstraintViolationException`) |

`proof:http` depends on the data written by `proof:run` (50 rows on sqld, the
`proof_notes` table in the local file) and runs right after it in `proof.sh`.
Re-running the harness always wipes and rebuilds from scratch, so ordering is
deterministic.

### Turso Cloud offline-writes flavor (`php artisan proof:offline-cloud`, opt-in)

Separate from the sqld phases above, this one exercises the driver's **Turso
Cloud** offline-writes flavor — the extension's `new_synced_database` over
HTTPS, in `remote_replica` mode. It needs a **real Turso Cloud database** — it
cannot be emulated — and a **manual first sync** (bootstrap) before any write
against a non-empty cloud database, otherwise the push fails with a frame
conflict. The driver itself needs nothing extra beyond `offlineWrites=true`
without `sqldOfflineMode=true`.

It asserts: bootstrap pull from remote → local journaled writes →
`sync()` push → plain remote read-back → pushed drop, all against your own
Turso Cloud database (12 asserts). The table it creates is dropped and the
drop is pushed, so the cloud database is left untouched.

| Phase | What it proves |
|-------|----------------|
| `cloud_bootstrap` | Connection is the Turso Cloud offline-writes flavor (`remote_replica` mode) and `sync()` pulls the remote state down |
| `cloud_journal` | `CREATE TABLE` + 2 inserts journal to the local replica |
| `cloud_push` | `sync()` pushes the journaled frames to Turso Cloud |
| `cloud_remote_read` | A plain `remote` (non-replica) connection reads the 2 rows |
| `cloud_cleanup` | The drop is journaled and pushed; cloud is left untouched |

```bash
TURSO_CLOUD_DB_URL=libsql://<db>.turso.io TURSO_CLOUD_DB_TOKEN=<token> \
    docker/proof/offline-cloud.sh
```

The phase is **opt-in**: without those two environment variables the script
prints a skip notice and exits 0 (the harness stays green). It is deliberately
not wired into `proof.sh` — it talks to a live cloud database, so it never
runs in CI.

### Offline-write phases (`php artisan proof:offline-write` / `proof:offline-sync`)

Run during a **real outage window** — `proof.sh` disconnects the app from the
server-net (sqld only; the nginx→fpm path on web-net keeps serving):

| # | Phase | What it proves |
|---|-------|----------------|
| 1 | `offline_outage` | The outage is real: `isOnline() === false` inside the app |
| 2 | `offline_journal` | `CREATE TABLE` + 3 inserts journal locally (`pending` 1 → 4); journaled rows read back offline |
| 3 | `offline_blind_window` | The app cannot reach sqld at all during the outage (raw Hrana fails) |
| 4 | host-side check (in `proof.sh`) | From **outside** the outage: `jour` absent/empty on sqld — the journal never leaked; `proof_notes` still 50 — the server stands |
| 5 | `offline_reconnected` | `isOnline() === true` after the reconnect |
| 6 | `offline_drain` | `sync()` drains the journal (`pending` == 0); the driver reads the rows back |
| 7 | `offline_server_drained` | A second, independent Hrana client (raw HTTP) reads the 3 journaled rows from the server, key+value exact |
| 8 | host-side check (in `proof.sh`) | `jour` count == 3 on sqld — the full round-trip replicated |

Two things worth knowing, both enforced from the driver's side:

- **The extension refuses `prepare()` in offline-write mode.** The driver
  therefore binds parameters and routes statements through the connection's
  `execute()`/`query()` methods (which journal writes, DDL included) instead
  of prepared statements. Online connections are untouched.
- The offline-write flavor used here is the **sqld (self-hosted libsql-server)
  variant** — `sqldOfflineMode=true` **and** `offlineWrites=true`. The
  `offline_writes` flag is Turso Cloud-only; the `sqld_offline_mode`+`offline_writes`
  pair is the documented self-hosted combination.

The final report lives in `docker/proof/proof-report/proof-<timestamp>.json`:

```json
{
    "driver": "tursodatabase/turso-driver-laravel",
    "phases": {
        "health": { "ok": true, "asserts": 3, "error": null },

    },
    "ok": N,
    "fail": 0,
    "all_ok": true
}
```

## Proving the published release instead of this checkout

By default the image installs the driver from **this checkout** (path
repository) — the proof answers "does this tree work?". To prove exactly what
a user gets from Packagist instead:

```bash
DRIVER_SOURCE=packagist DRIVER_VERSION=^1.4 docker compose -f docker/proof/compose.yaml build --progress plain
docker/proof/proof.sh          # reuses the built image, but wipes containers/volumes first
```

Note: `proof.sh` always rebuilds with its own build args. To bake the
packagist variant into the default flow, export `DRIVER_SOURCE`/`DRIVER_VERSION`
before calling `proof.sh`:

```bash
DRIVER_SOURCE=packagist DRIVER_VERSION=^1.4 docker/proof/proof.sh --teardown
```

## Configuration (environment variables)

| Variable | Default | Meaning |
|---|---|---|
| `SQLD_PORT` | `8081` | Host port mapped to the server's `8080` |
| `HTTP_PORT` | `8080` | Host port mapped to nginx's `80` |
| `DRIVER_SOURCE` | `path` | `path` = this checkout, `packagist` = published package |
| `DRIVER_VERSION` | `*` | Version constraint for the driver (`*` = resolve from source/latest stable) |

## What this does NOT prove

- **The default harness does NOT prove the Turso Cloud offline-write flavor.**
  `proof.sh` covers only the **sqld (self-hosted) offline-write** combination;
  the Turso Cloud combination is exercised separately, and only by
  `offline-cloud.sh` / `TursoCloudOfflineWriteTest`, because it requires a real
  Turso Cloud database (it cannot be emulated or run in CI). Your own
  credentials are never substituted, simulated, or fabricated by this harness.
- **Your application.** The proof uses a vanilla Laravel 12 skeleton served by
  fpm. Your app's middleware, opcache setup and connection pooling may differ.
- **Debug error pages are environment-specific.** `throw_over_http` asserts
  the 500 status and the presence of `UNIQUE constraint failed` in the debug
  page; it is not a contract on error-page markup.

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| Build fails downloading the extension | GitHub release unreachable — check your network/proxy, or retry |
| `sqld did not become ready within 60s` | Port `8081` in use — rerun with `SQLD_PORT=8090` |
| `Unsupported driver [http]` inside a phase | Never happens in this harness (configs are built in-phase); file an issue if you see it |
| `proof:run` reports `fail > 0` | Read the `✗` lines above it; each names the failing assertion. The JSON report records per-phase outcomes |
| Permission errors writing `/tmp/*.sqlite` in the container | Volume is root-owned? Remove the container (`proof.sh --teardown`) — files live inside the container filesystem only |

## File map

| File | Role |
|---|---|
| `Dockerfile` | Builds the Laravel 12 + driver image on `php:8.3-fpm` (build args: `DRIVER_SOURCE`, `DRIVER_VERSION`) |
| `Dockerfile.dockerignore` | Per-Dockerfile ignore: keeps the context small, app payload included |
| `compose.yaml` | `sqld` + `app` (php-fpm) + `web` (nginx) services, split across `web-net` / `server-net`; project name `turso-driver-proof` |
| `proof.sh` | Orchestrator: wipe → build → server readiness → install → migrate → CLI assert → web tier → HTTP assert → **offline window (disconnect/reconnect)** → reports |
| `offline-cloud.sh` | Opt-in Turso Cloud offline-writes proof (probes `php artisan proof:offline-cloud`) — needs your own cloud DB; never in CI |
| `default.conf` | nginx tier: front controller to php-fpm (`fastcgi_pass app:9000`) |
| `host-hrana-check.php` | Host-side raw Hrana probe for the server-side asserts (outside the outage) |
| `app/ProofRun.php` | Console assert phases (`php artisan proof:run`) |
| `app/ProofHttp.php` | HTTP/fpm assert phases (`php artisan proof:http`) |
| `app/ProofOfflineWrite.php` | Offline-write assertions, run during the outage (`proof:offline-write`) |
| `app/ProofOfflineSync.php` | Journal drain + server round-trip (`proof:offline-sync`) |
| `app/ProofOfflineCloud.php` | Turso Cloud offline-writes assertions (`proof:offline-cloud`) — 12 asserts against your DB |
| `app/web-routes.php` | Routes served over HTTP: `/proof/health`, `/proof/remote`, `/proof/throw` |
| `app/create_proof_notes_table.php` | Migration created by `php artisan migrate --force` through the driver |
| `proof-report/` | Generated reports (gitignored) |
