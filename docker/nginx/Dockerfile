# syntax=docker/dockerfile:1

# nginx serves public/ itself, so it needs those files — it does not share a
# volume with php-fpm. The build arg points at the app image so both always
# come from the same release.
ARG APP_IMAGE=hayzeeonline-pos-api-app:latest
FROM ${APP_IMAGE} AS app

FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Only public/ — the application code has no business inside the web root.
COPY --from=app /var/www/public /var/www/public

# Uploads live on a volume mounted at /var/www/storage/app/public in this
# container too, so this symlink resolves and nginx can serve product images
# directly instead of routing static files through PHP.
RUN mkdir -p /var/www/storage/app/public \
    && ln -sfn /var/www/storage/app/public /var/www/public/storage

ARG SOURCE_URL=""
ARG VERSION="dev"
ARG REVISION=""
LABEL org.opencontainers.image.source="$SOURCE_URL" \
      org.opencontainers.image.version="$VERSION" \
      org.opencontainers.image.revision="$REVISION" \
      org.opencontainers.image.title="hayzeeonline-pos-api-nginx"

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://127.0.0.1/healthz >/dev/null 2>&1 || exit 1
