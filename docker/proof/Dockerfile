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
# same way a user would (composer → Artisan → turso-php:install), and runs
# php-fpm as the app tier behind nginx (the web proof). The CLI SAPI is part
# of the same image, so `docker compose exec app php artisan ...` (the CLI
# proof) keeps working unchanged.

FROM php:8.3-fpm-bookworm

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

# 6) Bake the proof into the application: migration + console commands +
#    web routes, with the Commands directory registered in bootstrap/app.php.
COPY docker/proof/app/create_proof_notes_table.php /app/database/migrations/2026_01_01_000000_create_proof_notes_table.php
COPY docker/proof/app/ProofRun.php /app/app/Console/Commands/ProofRun.php
COPY docker/proof/app/ProofHttp.php /app/app/Console/Commands/ProofHttp.php
COPY docker/proof/app/ProofOfflineWrite.php /app/app/Console/Commands/ProofOfflineWrite.php
COPY docker/proof/app/ProofOfflineSync.php /app/app/Console/Commands/ProofOfflineSync.php
COPY docker/proof/app/web-routes.php /app/routes/web.php
RUN sed -i "s#->create();#->withCommands([__DIR__.'/../app/Console/Commands'])\n    ->create();#" /app/bootstrap/app.php \
    && php /app/artisan list --raw 2>&1 | grep -q 'proof:run' \
    && php /app/artisan list --raw 2>&1 | grep -q 'proof:http' \
    && php /app/artisan list --raw 2>&1 | grep -q 'proof:offline-write' \
    && php /app/artisan list --raw 2>&1 | grep -q 'proof:offline-sync'

# 7) Install the libSQL PHP extension inside the container (the real user flow).
RUN php /app/artisan turso-php:install -n

# 8) Final step: switch the default connection to the driver's local file —
#    after this point no artisan call happens during the build. Note the
#    empty DB_AUTH_TOKEN: database + token + empty url matches no connection
#    mode rule (the driver reports "unknown"); a bare local file must have
#    no token. The remote/replica phases set their own credentials at runtime.
RUN printf '%s\n' \
        'APP_NAME="Turso Driver Proof"' \
        'APP_ENV=local' \
        'APP_DEBUG=true' \
        'LOG_CHANNEL=stack' \
        'DB_CONNECTION=libsql' \
        'DB_DATABASE=/app/proof.sqlite' \
        'DB_AUTH_TOKEN=' \
        'DB_SYNC_URL=' \
        "$(grep '^APP_KEY=' /app/.env)" > /app/.env.tmp \
    && mv /app/.env.tmp /app/.env \
    && touch /app/proof.sqlite

# 9) Hand the application tree to the fpm worker user — php-fpm (www-data)
#    cannot write storage/ or bootstrap/cache otherwise (requests 500).
RUN chown -R www-data:www-data /app

# 10) Run php-fpm in the foreground as the app tier (nginx fronts it).
CMD ["php-fpm", "-F"]
