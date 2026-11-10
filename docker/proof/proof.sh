#!/usr/bin/env bash
#
# Isolated proof of usage for the Turso Driver for Laravel.
#
#   docker/proof/proof.sh            full proof, keeps containers running
#   docker/proof/proof.sh --teardown full proof, then wipes the environment
#
# What it does:
#   1. wipes volumes and builds a fresh image (Laravel 12 + driver, fpm SAPI)
#   2. boots the libSQL server (official ghcr image)
#   3. installs the extension inside the app container, runs `php artisan
#      migrate --force` through the driver (real artisan flow)
#   4. executes `php artisan proof:run` and prints the PROOF: {json} line
#   5. brings up the web tier (nginx → php-fpm) and executes
#      `php artisan proof:http` — HTTP assertions driven through the driver
#   6. cuts app ↔ sqld (server-net only) and executes
#      `php artisan proof:offline-write` — writes must journal locally,
#      the server must stay blind (the web tier keeps serving throughout)
#   7. reconnects and executes `php artisan proof:offline-sync` — sync()
#      drains the journal, rows land on the server
#   8. copies the JSON reports into docker/proof/proof-report/

set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

TEARDOWN=0
FORWARD_ARGS=()
for arg in "$@"; do
    case "$arg" in
        --teardown) TEARDOWN=1 ;;
        *) FORWARD_ARGS+=("$arg") ;;
    esac
done

export SQLD_PORT="${SQLD_PORT:-8081}"
export HTTP_PORT="${HTTP_PORT:-8080}"

echo "==> Resetting environment (wiped volumes)"
docker compose down -v --remove-orphans >/dev/null 2>&1 || true

echo "==> Building image (first run downloads Laravel + the extension payload; this can take a few minutes)"
docker compose build --progress plain

echo "==> Starting libSQL server on 127.0.0.1:${SQLD_PORT}"
docker compose up -d sqld

echo "==> Waiting for sqld to accept Hrana requests"
READY=0
for i in $(seq 1 60); do
    if curl -sf -m 2 -X POST "http://127.0.0.1:${SQLD_PORT}/v2/pipeline" \
        -H 'Content-Type: application/json' \
        -d '{"requests":[{"type":"execute","stmt":{"sql":"SELECT 1"}},{"type":"close"}]}' >/dev/null 2>&1; then
        READY=1
        break
    fi
    sleep 1
done
if [ "$READY" != "1" ]; then
    echo "error: sqld did not become ready within 60s" >&2
    exit 1
fi

echo "==> Starting app container"
docker compose up -d app

echo "==> Installing the libSQL extension inside the app container"
docker compose exec -T app php artisan turso-php:install -n

echo "==> Running migrations through the driver (default libsql connection, local file)"
docker compose exec -T app php artisan migrate --force

echo "==> Running proof assertions"
docker compose exec -T app php artisan proof:run

echo "==> Starting web tier (nginx → php-fpm)"
docker compose up -d web

echo "==> Waiting for the web tier to serve /proof/health"
READY=0
for i in $(seq 1 60); do
    if curl -sf -m 2 "http://127.0.0.1:${HTTP_PORT}/proof/health" >/dev/null 2>&1; then
        READY=1
        break
    fi
    sleep 1
done
if [ "$READY" != "1" ]; then
    echo "error: web tier did not become ready within 60s" >&2
    exit 1
fi

echo "==> Running HTTP/fpm assertions"
docker compose exec -T app php artisan proof:http

echo "==> Offline-write window: disconnect app from sqld (server-net only, web-net intact)"
APP_ID="$(docker compose ps -q app)"
NET_NAME="$(docker network ls --filter name=turso-driver-proof_server-net --format '{{.Name}}' | head -1)"
docker network disconnect "$NET_NAME" "$APP_ID"

echo "==> The web tier keeps serving during the outage"
if ! curl -sf -m 5 "http://127.0.0.1:${HTTP_PORT}/proof/health" >/dev/null; then
    echo "error: web tier died during the network outage" >&2
    exit 1
fi

echo "==> Running offline-write assertions (outage window)"
docker compose exec -T app php artisan proof:offline-write

echo "==> Server state from OUTSIDE the outage (host → sqld)"
JOUR="$(php "$DIR/host-hrana-check.php" jour)"
KV="$(php "$DIR/host-hrana-check.php" proof_notes)"
echo "    jour on server: $JOUR (expect no-such-table or 0 — the journal must not have drained)"
echo "    proof_notes on server: $KV (expect 50 — the outage must not hurt the server)"
if [ "$JOUR" != "no-such-table" ] && [ "$JOUR" != "0" ]; then
    echo "error: the server holds jour rows while app is offline — journal leaked to sqld" >&2
    exit 1
fi
if [ "$KV" != "50" ]; then
    echo "error: proof_notes on server changed during the outage ($KV, expected 50)" >&2
    exit 1
fi

echo "==> Reconnecting app to sqld"
docker network connect "$NET_NAME" "$APP_ID"

echo "==> Running offline-sync assertions"
docker compose exec -T app php artisan proof:offline-sync

echo "==> Server state after the drain (host → sqld)"
JOUR="$(php "$DIR/host-hrana-check.php" jour)"
echo "    jour on server: $JOUR (expect 3 — journal drained by sync())"
if [ "$JOUR" != "3" ]; then
    echo "error: jour rows on server after sync ($JOUR, expected 3)" >&2
    exit 1
fi

mkdir -p proof-report
STAMP="$(date +%Y%m%d-%H%M%S)"
docker compose cp app:/app/storage/app/proof-report.json "proof-report/proof-${STAMP}.json" >/dev/null
docker compose cp app:/app/storage/app/proof-http-report.json "proof-report/proof-http-${STAMP}.json" >/dev/null
docker compose cp app:/app/storage/app/proof-offline-write-report.json "proof-report/proof-offline-write-${STAMP}.json" >/dev/null
docker compose cp app:/app/storage/app/proof-offline-sync-report.json "proof-report/proof-offline-sync-${STAMP}.json" >/dev/null
echo "==> Reports written to docker/proof/proof-report/ (proof/proof-http/proof-offline-write/proof-offline-sync ${STAMP})"

# Optional: Turso Cloud offline-writes flavor (requires real Cloud credentials)
echo "==> Tip: Turso Cloud offline-writes proof is opt-in — it needs a real Cloud database:"
echo "    TURSO_CLOUD_DB_URL=libsql://<db>.turso.io TURSO_CLOUD_DB_TOKEN=<token> docker/proof/offline-cloud.sh"

if [ "$TEARDOWN" = "1" ]; then
    echo "==> Tearing down (removes containers and volumes)"
    docker compose down -v --remove-orphans
else
    echo "==> Done. Containers are kept running. Wipe with: docker/proof/proof.sh --teardown"
fi
