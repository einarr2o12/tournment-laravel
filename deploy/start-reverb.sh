#!/usr/bin/env sh
# =============================================================================
# Reverb websocket service start script (Railway).
#
# This runs on a SEPARATE Railway service using the SAME image as the web
# service. It does NOT serve HTTP via nginx — it runs the Laravel Reverb
# websocket server directly.
#
# Railway injects $PORT for this service; Reverb does not read $PORT on its
# own, so we map it explicitly. The server binds 0.0.0.0:$PORT.
#
# No migrate/cache here — the web service owns the release steps. We do cache
# config so reverb reads the production broadcasting credentials fast.
# =============================================================================
set -e

cd /var/www/html

REVERB_PORT_BIND="${PORT:-8080}"

php artisan config:cache || true

echo "[start-reverb] Starting Reverb on 0.0.0.0:${REVERB_PORT_BIND}..."
exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_PORT_BIND}"
