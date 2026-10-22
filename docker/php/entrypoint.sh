#!/bin/sh
set -e

# Runs before php-fpm, the queue worker and the scheduler alike, so it must be
# safe to run concurrently in several containers. It deliberately does NOT run
# migrations: those happen once per release, from deploy.sh, before any of
# these containers start.

APP_USER=www-data

# A volume mounted over storage/ arrives empty, so the framework's scratch
# directories have to be recreated on every start.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache 2>/dev/null || true

# A named volume mounted over a path arrives owned by root, whatever the image
# said — so www-data cannot create anything inside it, and the first product
# image upload fails with "Unable to create a directory at
# storage/app/public/...". The image cannot fix that at build time because the
# volume does not exist yet; it has to happen here, on every start, because a
# fresh volume can appear on any deploy.
#
# Only the mount points, not the whole tree: chown -R over a large uploads
# volume would add seconds to every container start for no benefit.
if [ "$(id -u)" = "0" ]; then
    chown "$APP_USER":"$APP_USER" \
        storage \
        storage/app \
        storage/app/public \
        storage/logs \
        bootstrap/cache 2>/dev/null || true

    # New volumes only: an existing tree is already right, and walking it on
    # every start is wasted time once there are thousands of uploads.
    find storage/app/public storage/logs -maxdepth 1 ! -user "$APP_USER" \
        -exec chown -R "$APP_USER":"$APP_USER" {} + 2>/dev/null || true
fi

# public/storage is gitignored and excluded from the image, so recreate the
# link rather than shipping one. Left alone if the path is already a real
# directory (someone bind-mounted uploads straight in).
if [ ! -e public/storage ]; then
    ln -s /var/www/storage/app/public public/storage 2>/dev/null || true
fi

# Only the long-running roles need a configured application. Anything else —
# `php -v`, `artisan migrate`, `artisan tinker`, a shell for debugging — is run
# deliberately by a human or by deploy.sh and must not be blocked.
case "$1" in
    php-fpm|php-fpm.*)
        serving=1 ;;
    php)
        case "$*" in
            *queue:work*|*queue:listen*|*schedule:work*) serving=1 ;;
            *) serving=0 ;;
        esac ;;
    *)
        serving=0 ;;
esac

if [ "$serving" = "1" ]; then
    if [ -z "${APP_KEY:-}" ]; then
        echo "entrypoint: APP_KEY is not set — refusing to serve traffic." >&2
        echo "            Generate one with: php artisan key:generate --show" >&2
        exit 1
    fi

    # jwt-auth signs every token with this. Without it the app boots and then
    # fails on the first login with a secret-not-set exception, which looks
    # like a code fault rather than a missing value.
    if [ -z "${JWT_SECRET:-}" ]; then
        echo "entrypoint: JWT_SECRET is not set — every login would fail." >&2
        echo "            Generate one with: php artisan jwt:secret --show" >&2
        exit 1
    fi

    # Warm the caches at start rather than baking them into the image: they
    # embed environment values, which are only known now.
    #
    # Failures are tolerated on purpose — a cold cache is slower but still
    # correct, and a container that will not start is worse than a slow one.
    # As the app user: a cache file written by root is one www-data cannot
    # replace on the next start, which turns a warm cache into a hard failure.
    as_app() {
        if [ "$(id -u)" = "0" ]; then gosu "$APP_USER" "$@"; else "$@"; fi
    }

    as_app php artisan config:cache 2>&1 || echo "entrypoint: config:cache failed, continuing uncached" >&2
    as_app php artisan route:cache  2>&1 || echo "entrypoint: route:cache failed, continuing uncached" >&2
    as_app php artisan event:cache  2>&1 || true

    echo "entrypoint: ready (${CONTAINER_ROLE:-app})"
fi

# Drop to the application user — except for php-fpm.
#
# php-fpm's master is meant to run as root: it needs that to open the log
# stream the container hands it on stderr, and it drops its own workers to
# www-data from the pool config (`user = www-data`). Forcing the master down
# instead fails at startup with "failed to open error_log (/proc/self/fd/2):
# Permission denied" and the container exits 78.
#
# `gosu` replaces the process rather than forking, so signals and exit codes
# still reach the command exactly as they would without it.
case "$1" in
    php-fpm|php-fpm.*)
        exec "$@" ;;
esac

if [ "$(id -u)" = "0" ]; then
    exec gosu "$APP_USER" "$@"
fi

exec "$@"
