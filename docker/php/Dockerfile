# syntax=docker/dockerfile:1

###############################################################################
# Stage 1 — PHP extensions, shared by the builder and the runtime.
#
# Pinned to 8.2.35 rather than the moving `8.2-fpm` tag, so the runtime only
# changes when this line does. The local dev image pins the same version.
###############################################################################
FROM php:8.2.35-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        default-mysql-client \
        gosu \
        libbz2-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
        libfcgi-bin \
    && rm -rf /var/lib/apt/lists/*

# gd must be configured before it is built, or jpeg and freetype support are
# silently left out — product images are the main thing this API stores.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath bz2 gd intl opcache pcntl pdo_mysql pdo_pgsql pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# MySQL 8/9 presents a self-signed certificate the client refuses by default.
# The connection does not leave the host, so turn client TLS off rather than
# ship a CA. Debian's client is MariaDB's, hence skip-ssl.
RUN printf '[client]\nskip-ssl\n' > /root/.my.cnf

###############################################################################
# Stage 2 — Composer dependencies, cached on the lock file alone.
###############################################################################
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app

# Only the manifests, so a code-only change does not re-resolve dependencies.
COPY composer.json composer.lock ./

# Two things guard this step, because it is the one that fails on someone
# else's infrastructure rather than ours:
#
#   COMPOSER_AUTH — without a token, Composer fetches zipballs from
#   api.github.com anonymously, which is rate-limited to 60 requests an hour
#   PER IP. CI runners share IPs, so a build can walk into somebody else's
#   exhausted quota. Mounted as a BuildKit secret, not a build-arg, so the
#   token never appears in the image history.
#
#   The retry — even authenticated, api.github.com returns the occasional 504.
#   A release should not fail because one zipball was briefly unavailable.
RUN --mount=type=secret,id=composer_auth \
    export COMPOSER_AUTH="$(cat /run/secrets/composer_auth 2>/dev/null || echo '{}')" \
    && for attempt in 1 2 3; do \
        if composer install \
            --no-dev \
            --no-scripts \
            --no-autoloader \
            --prefer-dist \
            --no-interaction \
            --no-progress; then ok=1; break; fi; \
        echo "composer install failed (attempt ${attempt}/3) — retrying"; \
        sleep $((attempt * 15)); \
    done; \
    [ "${ok:-0}" = 1 ]

COPY . ./

# --classmap-authoritative: every class is known at build time, so no
# filesystem lookups happen at runtime.
#
# It also fails loudly on a class whose name does not match its file, which is
# worth having. This upgrade found three such cases (a controller declared
# `attributeValueController`, and imports of `App\product` and
# `App\attributeValue`). They worked on a developer's case-insensitive macOS
# filesystem and would have failed on this image's case-sensitive one — the
# authoritative classmap turns that class of bug into a build-time warning
# instead of a 500 in production.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && composer run-script post-autoload-dump --no-dev

###############################################################################
# Stage 3 — Runtime.
###############################################################################
FROM base AS runtime

# php-fpm's default www-data is uid 33; keep the app files owned by it so the
# process never needs to write as root.
ARG APP_UID=33

WORKDIR /var/www

COPY docker/php/php.ini       /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/www.conf      /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

COPY --from=vendor --chown=www-data:www-data /app /var/www

# storage/ and bootstrap/cache are the only writable paths. Everything else is
# read-only to the application at runtime.
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# www-data has no home directory in the base image, so anything that wants to
# write dotfiles (artisan tinker's history, composer) fails. /tmp is writable
# and nothing here needs the contents to persist.
ENV HOME=/tmp

# Deliberately NOT `USER www-data`.
#
# A named volume mounted over storage/ arrives owned by root, and a process
# already running as www-data cannot chown its way out of that — which is the
# "Unable to create a directory" failure on a first deploy. So the entrypoint
# starts as root, fixes the mount points, and drops to www-data with gosu
# before exec'ing anything. Nothing in the app ever runs as root.

ARG SOURCE_URL=""
ARG VERSION="dev"
ARG REVISION=""
LABEL org.opencontainers.image.source="$SOURCE_URL" \
      org.opencontainers.image.version="$VERSION" \
      org.opencontainers.image.revision="$REVISION" \
      org.opencontainers.image.title="hayzeeonline-pos-api-app"

# Asks php-fpm's own ping endpoint (enabled in zz-www.conf), so a wedged pool
# is restarted rather than left as a black hole behind the proxy.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
