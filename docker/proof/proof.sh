#!/usr/bin/env bash
#
# Isolated proof of usage for the Turso Driver for Laravel.
#
#   docker/proof/proof.sh            full proof, keeps containers running
#   docker/proof/proof.sh --teardown full proof, then wipes the environment
#
# What it does:
#   1. wipes volumes and builds a fresh image (Laravel 12 + driver)
#   2. boots the libSQL server (official ghcr image)
#   3. installs the extension inside the app container, runs `php artisan
#      migrate --force` through the driver (real artisan flow)
#   4. executes `php artisan proof:run` and prints the PROOF: {json} line
#   5. copies the JSON report into docker/proof/proof-report/

set -euo pipefail
cd "$(dirname "$0")"

TEARDOWN=0
FORWARD_ARGS=()
for arg in "$@"; do
    case "$arg" in
        --teardown) TEARDOWN=1 ;;
        *) FORWARD_ARGS+=("$arg") ;;
    esac
done

export SQLD_PORT="${SQLD_PORT:-8081}"

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

mkdir -p proof-report
REPORT_NAME="proof-$(date +%Y%m%d-%H%M%S).json"
docker compose cp app:/app/storage/app/proof-report.json "proof-report/${REPORT_NAME}" >/dev/null
echo "==> Report written to docker/proof/proof-report/${REPORT_NAME}"

if [ "$TEARDOWN" = "1" ]; then
    echo "==> Tearing down (removes containers and volumes)"
    docker compose down -v --remove-orphans
else
    echo "==> Done. Containers are kept running. Wipe with: docker/proof/proof.sh --teardown"
fi
