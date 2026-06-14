# =============================================================================
# Stage 1: frontend — compile Vite/Tailwind/Vue assets with Bun
# Produces public/build (manifest + hashed JS/CSS). The reverb domain is read
# at RUNTIME via Inertia shared props, so nothing reverb-specific is baked in.
# =============================================================================
FROM oven/bun:1 AS frontend
WORKDIR /app

# Install JS deps (not --frozen: Railway's bun image may re-resolve slightly).
COPY package.json bun.lock ./
RUN bun install

# Copy the sources Vite needs (config + frontend code).
COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
RUN bun run build

# =============================================================================
# Stage 2: runtime — PHP 8.4 CLI + `php artisan serve`
# Deliberately simple and predictable for Railway: no nginx/s6/entrypoint
# magic. The built-in PHP server binds $PORT directly and serves both the
# Laravel front controller AND the static build assets in public/. Fine for
# club-scale traffic; can be swapped for FrankenPHP/Octane later if needed.
# =============================================================================
FROM php:8.4-cli-bookworm AS runtime

# ---- PHP extensions via the mlocati helper (handles system deps) ----------
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions pdo_pgsql pcntl posix intl bcmath zip opcache gd

# ---- Composer -------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---- PHP dependencies (layer-cached on composer manifests) ----------------
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --no-scripts

# ---- Application code + compiled assets -----------------------------------
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Finalize autoload + package discovery (scripts were skipped above).
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi || true

# ---- Deploy/start scripts -------------------------------------------------
COPY deploy/start-web.sh /usr/local/bin/start-web.sh
COPY deploy/start-reverb.sh /usr/local/bin/start-reverb.sh
RUN chmod +x /usr/local/bin/start-web.sh /usr/local/bin/start-reverb.sh \
    && chmod -R ug+rwX storage bootstrap/cache

# Railway injects $PORT; the start script reads it. Default for local runs.
ENV PORT=8080
EXPOSE 8080

# Web service default. The reverb Railway service overrides this with
# start-reverb.sh (see DEPLOY.md).
CMD ["/usr/local/bin/start-web.sh"]
