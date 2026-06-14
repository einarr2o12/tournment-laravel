# =============================================================================
# Stage 1: frontend — compile Vite/Tailwind/Vue assets with Bun
# Produces public/build (manifest + hashed JS/CSS) consumed by the runtime
# stage. The reverb domain is read at RUNTIME via Inertia shared props, so
# nothing reverb-specific needs to be baked in here.
# =============================================================================
FROM oven/bun:1 AS frontend
WORKDIR /app

# Install JS deps from the committed lockfile (no package-lock/yarn.lock).
# Not --frozen: Railway's bun image version may differ from local and
# re-resolve the lock slightly; a plain install tolerates that.
COPY package.json bun.lock ./
RUN bun install

# Copy the sources Vite needs to build (config + frontend code).
# vite.config.ts globs resources/js/Pages/**, so resources/ is required.
COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
# Ziggy route generation is not needed at build time (routes are resolved at
# runtime via @routes in the blade view), so we only need the JS sources.

RUN bun run build

# =============================================================================
# Stage 2: runtime — serversideup PHP 8.4 (nginx + php-fpm, non-root)
# https://serversideup.net/open-source/docker-php/
# This image bundles nginx + php-fpm + s6 supervision and is designed for
# Laravel on PaaS. It runs as the non-root "www-data" user by default.
# =============================================================================
FROM serversideup/php:8.4-fpm-nginx AS runtime

# ---- PHP extensions -------------------------------------------------------
# serversideup's -fpm-nginx variant already bundles the Laravel core
# extensions (pdo, mbstring, tokenizer, ctype, openssl, bcmath, fileinfo,
# dom, xml, curl, zip, intl, pcntl, posix) AND pdo_pgsql. We install
# pdo_pgsql explicitly via the image's install-php-extensions helper so the
# build fails loudly if a future base tag drops it (Postgres is mandatory).
USER root
RUN install-php-extensions pdo_pgsql pcntl posix intl bcmath zip
USER www-data

# ---- PORT handling --------------------------------------------------------
# Railway injects a $PORT env var the web server MUST listen on. The
# serversideup image reads the nginx listen port from the env var
# SSL_MODE/AUTORUN aside, the relevant knob is:
#   APP_BASE_DIR     -> docroot parent
#   PHP_OPCACHE_*    -> opcache tuning
# and crucially the nginx listen port is controlled by the env var that the
# image's nginx template substitutes. For serversideup/php the web port is
# set via the `NGINX_HTTP_PORT_NUMBER`-style template; in these images the
# supported variable is `PORT`-aware through the s6 nginx config which reads
# the env var below. We export it from $PORT in the start script (see
# deploy/start-web.sh) and also default it here for local runs.
#
# NOTE FOR VERIFY: serversideup nginx listens on 8080 by default. The exact
# env var name to override the listen port has changed across image
# versions (older: implicit 8080 only; newer 3.x: configurable). We handle
# this defensively in deploy/start-web.sh by rewriting the nginx vhost to
# $PORT before launching the entrypoint, which works regardless of whether
# the image natively honors a PORT env var. See that script's comment.
ENV PORT=8080
EXPOSE 8080

WORKDIR /var/www/html

# ---- PHP dependencies (composer) -----------------------------------------
# Copy composer manifests first for layer caching, install without dev deps.
# composer is preinstalled in the serversideup image.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --no-scripts

# ---- Application code ------------------------------------------------------
COPY --chown=www-data:www-data . .

# Bring in the compiled frontend assets from the frontend stage.
COPY --chown=www-data:www-data --from=frontend /app/public/build ./public/build

# Re-run autoloader now that all source + the artisan binary are present,
# and run package discovery (skipped above with --no-scripts).
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi || true

# ---- Deploy/start scripts -------------------------------------------------
COPY --chown=www-data:www-data deploy/start-web.sh /usr/local/bin/start-web.sh
COPY --chown=www-data:www-data deploy/start-reverb.sh /usr/local/bin/start-reverb.sh
USER root
RUN chmod +x /usr/local/bin/start-web.sh /usr/local/bin/start-reverb.sh
USER www-data

# ---- Permissions -----------------------------------------------------------
# Laravel must be able to write to storage + bootstrap/cache at runtime.
RUN chmod -R ug+rwX storage bootstrap/cache

# Default command: serve the web app. The reverb Railway service overrides
# this with start-reverb.sh (see railway.json / DEPLOY.md).
CMD ["/usr/local/bin/start-web.sh"]
