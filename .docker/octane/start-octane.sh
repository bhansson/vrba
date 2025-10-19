#!/usr/bin/env bash
set -euo pipefail

WORKDIR=${APP_PATH:-/var/www/html}

cd "${WORKDIR}"

if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    cp .env.example .env
fi

(
    flock 200

    if ! grep -q '"laravel/octane"' composer.json; then
        composer require laravel/octane --no-interaction --no-progress
    fi

    if [ ! -f "vendor/autoload.php" ]; then
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi
) 200>/tmp/composer.install.lock

php artisan optimize:clear || true

if [ -f ".env" ] && grep -q "^APP_KEY=$" .env; then
    php artisan key:generate --force --ansi
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --ansi
fi

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

CMD=(
    php
    artisan
    octane:start
    "--server=${OCTANE_SERVER:-swoole}"
    "--host=0.0.0.0"
    "--port=${OCTANE_PORT:-8000}"
    "--max-requests=${OCTANE_MAX_REQUESTS:-500}"
)

if [ "${OCTANE_WATCH:-false}" = "true" ]; then
    if command -v node >/dev/null 2>&1; then
        CMD+=("--watch")
    else
        echo "Octane watch requested but Node.js is not available; starting without watch mode." >&2
    fi
fi

exec "${CMD[@]}"
