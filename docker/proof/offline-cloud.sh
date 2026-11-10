#!/usr/bin/env bash
#
# Turso Cloud offline-writes flavor proof for the driver.
#
# Unlike every other phase in this harness, this one talks to a REAL Turso
# Cloud database — it cannot be emulated locally. It is opt-in: it only
# runs when both environment variables are set. Without them it prints a
# skip notice and exits 0 (the harness stays green).
#
# Usage:
#   TURSO_CLOUD_DB_URL=libsql://<db>.turso.io \
#   TURSO_CLOUD_DB_TOKEN=<token> \
#   docker/proof/offline-cloud.sh
#
# The script connects the app container to the given database via the
# driver's Turso Cloud offline-write flavor (sqldOfflineMode=false +
# offlineWrites=true), bootstraps the local replica from the cloud,
# performs journaled writes, pushes with sync(), verifies from a plain
# remote connection, then drops the table and pushes the drop.

set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

if [ -z "${TURSO_CLOUD_DB_URL:-}" ] || [ -z "${TURSO_CLOUD_DB_TOKEN:-}" ]; then
    echo "SKIPPED — the Turso Cloud offline-writes proof requires a real Turso Cloud database."
    echo "Set TURSO_CLOUD_DB_URL and TURSO_CLOUD_DB_TOKEN, then rerun:"
    echo "  TURSO_CLOUD_DB_URL=libsql://<db>.turso.io TURSO_CLOUD_DB_TOKEN=... $0"
    exit 0
fi

echo "==> Resetting environment (wiped volumes)"
docker compose down -v --remove-orphans >/dev/null 2>&1 || true
echo "==> Building image (composer deps + extension from the tree under review)"
docker compose build --progress plain app
echo "==> Starting app (php-fpm, factory-offline-writes proof)"
docker compose up -d app

echo "==> Turso Cloud offline-writes proof (remote_replica flavor)"
docker compose exec -T \
    -e TURSO_CLOUD_DB_URL="$TURSO_CLOUD_DB_URL" \
    -e TURSO_CLOUD_DB_TOKEN="$TURSO_CLOUD_DB_TOKEN" \
    app php artisan proof:offline-cloud

STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p proof-report
docker compose cp app:/app/storage/app/proof-offline-cloud-report.json "proof-report/proof-offline-cloud-${STAMP}.json" 2>/dev/null || true
echo "==> Report (if written): docker/proof/proof-report/proof-offline-cloud-${STAMP}.json"

echo "==> Tearing down containers and volumes"
docker compose down -v --remove-orphans >/dev/null
