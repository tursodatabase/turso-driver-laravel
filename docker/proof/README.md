# Isolated Proof of Usage — Turso Driver for Laravel

**Try the driver before you install it.** This harness boots a real Laravel
application against a live libSQL server — entirely inside Docker — installs
the driver the same way you would in your own app, and asserts that it works
end to end, both through the console (`php artisan`) and through a real web
tier (**nginx → php-fpm → Laravel → the driver**). Nothing is installed on
your host beyond Docker itself.

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
7. write JSON reports to `docker/proof/proof-report/` (gitignored — never
   commit them).

Expected ending (assert counts may vary):

```
PROOF: {"ok":N,"fail":0,"all_ok":true}
PROOF-HTTP: {"ok":M,"fail":0,"all_ok":true}
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

- **Offline-write modes** (`DB_OFFLINE_WRITES`, `DB_SQLD_OFFLINE_MODE`),
  including the outage windows (`docker network disconnect`) exercised by the
  extension repository's `docker/production-proof/` in
  `tursodatabase/turso-client-php`. This harness covers fpm + remote Hrana,
  not offline journaling.
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
| `compose.yaml` | `sqld` + `app` (php-fpm) + `web` (nginx) services; project name `turso-driver-proof` |
| `proof.sh` | Orchestrator: wipe → build → server readiness → install → migrate → CLI assert → web tier → HTTP assert → reports |
| `default.conf` | nginx tier: front controller to php-fpm (`fastcgi_pass app:9000`) |
| `app/ProofRun.php` | Console assert phases (`php artisan proof:run`) |
| `app/ProofHttp.php` | HTTP/fpm assert phases (`php artisan proof:http`) |
| `app/web-routes.php` | Routes served over HTTP: `/proof/health`, `/proof/remote`, `/proof/throw` |
| `app/create_proof_notes_table.php` | Migration created by `php artisan migrate --force` through the driver |
| `proof-report/` | Generated reports (gitignored) |
