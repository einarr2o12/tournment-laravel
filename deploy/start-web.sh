#!/usr/bin/env sh
# =============================================================================
# Web service start script (Railway).
#
# Runs release-time artisan steps once, then serves via the built-in PHP
# server on Railway's injected $PORT. APP_KEY comes from a Railway env var —
# we never run key:generate here (it would rotate the key every boot).
# =============================================================================
cd /var/www/html

TARGET_PORT="${PORT:-8080}"

# ---- Release steps (migrate is fatal; caches are best-effort) --------------
echo "[start-web] Running migrations..."
php artisan migrate --force || { echo "[start-web] migrate FAILED"; exit 1; }

echo "[start-web] Optimizing (storage link + caches)..."
php artisan storage:link 2>/dev/null || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan event:cache || true

# ---- Serve -----------------------------------------------------------------
echo "[start-web] Serving on 0.0.0.0:${TARGET_PORT}..."
exec php artisan serve --host=0.0.0.0 --port="${TARGET_PORT}"
