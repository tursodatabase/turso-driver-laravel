# Turso Driver for Laravel — isolated proof of usage.
#
# Build context must be the repository ROOT (the driver tree under review).
#   docker build -f docker/proof/Dockerfile .
#
# Build args:
#   DRIVER_SOURCE  "path" (default — install from this checkout) or
#                  "packagist" (install the published package instead)
#   DRIVER_VERSION Version constraint for the driver. Default "*":
#                  resolves the version this checkout declares (path) or the
#                  latest stable (packagist).
#
# The image builds a fresh Laravel 12 application, installs the driver the
# same way a user would (composer → Artisan → turso-php:install), and keeps
# the container alive so `docker compose exec` can drive proof phases.

FROM php:8.3-cli-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        ca-certificates \
        libsqlite3-dev \
        libzip-dev \
        libssl-dev \
    && docker-php-ext-install pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 1) Fresh Laravel 12 application (pinned via the skeleton's version constraint).
RUN composer create-project laravel/laravel /app "12.*" --prefer-dist --no-progress

# 2) Leave the skeleton's default environment untouched during the build —
#    driver installer commands eagerly open the default connection when
#    Artisan instantiates them, and the config/database.php patch happens
#    below. The switch to libsql happens in the final step.

# 3) The driver source. Copied for the `path` build; harmless for packagist.
COPY . /driver-src

ARG DRIVER_SOURCE=path
ARG DRIVER_VERSION=*

# 4) Install the driver the same way a user would.
RUN if [ "$DRIVER_SOURCE" = "path" ]; then \
        composer config repositories.turso-proof '{"type":"path","url":"/driver-src","options":{"symlink":false}}'; \
    fi \
    && composer require "tursodatabase/turso-driver-laravel:${DRIVER_VERSION}" --no-progress

# 5) Register the libsql connection — the same block the driver README tells
#    users to add to config/database.php (the package has no publishable
#    config; the connection MUST be declared manually).
RUN printf '%s\n' \
        "        'libsql' => [" \
        "            'driver' => 'libsql'," \
        "            'database' => env('DB_DATABASE', '/app/proof.sqlite')," \
        "            'prefix' => ''," \
        "            'url' => env('DB_SYNC_URL', '')," \
        "            'authToken' => env('DB_AUTH_TOKEN', '')," \
        "            'syncInterval' => env('DB_SYNC_INTERVAL', 5)," \
        "            'read_your_writes' => env('DB_READ_YOUR_WRITES', true)," \
        "            'encryptionKey' => env('DB_ENCRYPTION_KEY', '')," \
        "            'sqldOfflineMode' => env('DB_SQLD_OFFLINE_MODE', false)," \
        "            'offlineWrites' => env('DB_OFFLINE_WRITES', false)," \
        "        ]," > /tmp/libsql-connection.php \
    && sed -i "/^    'connections' => \[$/r /tmp/libsql-connection.php" /app/config/database.php \
    && rm /tmp/libsql-connection.php \
    && php -l /app/config/database.php

# 6) Bake the proof into the application: migration + console command,
#    with the Commands directory registered in bootstrap/app.php.
COPY docker/proof/app/create_proof_notes_table.php /app/database/migrations/2026_01_01_000000_create_proof_notes_table.php
COPY docker/proof/app/ProofRun.php /app/app/Console/Commands/ProofRun.php
RUN sed -i "s#->create();#->withCommands([__DIR__.'/../app/Console/Commands'])\n    ->create();#" /app/bootstrap/app.php \
    && php /app/artisan list --raw 2>&1 | grep -q 'proof:run'

# 7) Install the libSQL PHP extension inside the container (the real user flow).
RUN php /app/artisan turso-php:install -n

# 8) Final step: switch the default connection to the driver's local file —
#    after this point no artisan call happens during the build.
RUN printf '%s\n' \
        'APP_NAME="Turso Driver Proof"' \
        'APP_ENV=local' \
        'APP_DEBUG=true' \
        'LOG_CHANNEL=stack' \
        'DB_CONNECTION=libsql' \
        'DB_DATABASE=/app/proof.sqlite' \
        'DB_AUTH_TOKEN=proof-token' \
        'DB_SYNC_URL=' \
        "$(grep '^APP_KEY=' /app/.env)" > /app/.env.tmp \
    && mv /app/.env.tmp /app/.env \
    && touch /app/proof.sqlite

# 9) Keep the container alive for `docker compose exec`.
CMD ["tail", "-f", "/dev/null"]
