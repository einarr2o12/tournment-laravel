# Deploying tournment-laravel to Railway

Architecture: **one Docker image, two Railway services** (web + reverb) sharing
this repo, plus a managed **Postgres** plugin. Real-time scoring runs over a
Reverb websocket; the frontend reads the reverb domain at **runtime** (Inertia
shared props), so the reverb host is never baked into the Vite build.

```
                 ┌────────────┐  HTTP   ┌──────────────┐
   browser ─────▶│  WEB svc   │────────▶│   Postgres   │
        │        │ nginx+fpm  │         │   (plugin)   │
        │  WS    └────────────┘         └──────────────┘
        └───────▶┌────────────┐               ▲
                 │ REVERB svc │───────────────┘
                 │ reverb:start
                 └────────────┘
```

Both services build from the same `Dockerfile`. The web service runs
`deploy/start-web.sh` (default CMD); the reverb service overrides the start
command with `deploy/start-reverb.sh`.

---

## 1. Prerequisites

```bash
npm i -g @railway/cli        # or: brew install railway
railway login
```

Generate an app key (you set the SAME value on both services):

```bash
php artisan key:generate --show
# -> base64:XXXXXXXX...   copy this
```

## 2. Create the project + Postgres

```bash
railway init                 # creates a project, link this repo
railway add --database postgres
```

This provisions the Postgres plugin. It exposes `DATABASE_URL`, `PGHOST`,
`PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD` for cross-service references.

## 3. Web service

1. In the Railway project, create a service from this repo (GitHub or
   `railway up`). It auto-detects `railway.json` → builds the `Dockerfile`
   and starts `start-web.sh`.
2. Generate a public domain: service → **Settings → Networking → Generate
   Domain**. This is your `WEB_DOMAIN` (e.g. `tournment-web.up.railway.app`).
3. Set env vars (Variables tab). Use `.env.railway.example` as the source of
   truth. Minimum:
   ```
   APP_NAME=Tournment
   APP_ENV=production
   APP_KEY=base64:...            # from step 1
   APP_DEBUG=false
   APP_URL=https://WEB_DOMAIN
   ASSET_URL=https://WEB_DOMAIN
   LOG_CHANNEL=stderr
   DB_CONNECTION=pgsql
   DB_URL=${{Postgres.DATABASE_URL}}
   SESSION_DRIVER=database
   SESSION_SECURE_COOKIE=true
   SESSION_DOMAIN=
   SANCTUM_STATEFUL_DOMAINS=WEB_DOMAIN
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=...            # pick any numeric/string id
   REVERB_APP_KEY=...           # random
   REVERB_APP_SECRET=...        # random
   REVERB_HOST=REVERB_DOMAIN    # filled after step 4
   REVERB_PORT=443
   REVERB_SCHEME=https
   ```
   `start-web.sh` runs `migrate --force` + `config/route/view:cache` on every
   boot, so migrations apply automatically on deploy.

## 4. Reverb service (same repo, different start command)

1. Create a SECOND service in the same project, from the **same repo**.
2. Settings → **Deploy → Custom Start Command**:
   ```
   /usr/local/bin/start-reverb.sh
   ```
   (Overrides the image's default `start-web.sh`.)
3. Generate a public domain for it → this is `REVERB_DOMAIN`
   (e.g. `tournment-reverb.up.railway.app`).
4. Set its env vars: copy ALL the `[BOTH]` vars from the web service
   (`APP_KEY`, `DB_*`, `REVERB_APP_*`, `BROADCAST_CONNECTION`, etc.). The
   reverb server binds `0.0.0.0:$PORT` automatically (Railway injects `$PORT`;
   `start-reverb.sh` passes `--host=0.0.0.0 --port=$PORT`).

## 5. Cross-reference the domains

- On the **web** service, set `REVERB_HOST=<REVERB_DOMAIN>` (no scheme).
- Confirm `APP_URL` / `ASSET_URL` / `SANCTUM_STATEFUL_DOMAINS` use the
  **web** domain.
- Redeploy both. The web service shares `{ key, host, port, scheme }` to the
  browser via Inertia props; the Echo client connects to
  `wss://REVERB_DOMAIN:443`.

## 6. Seed (optional, one-off)

```bash
railway run --service web php artisan db:seed --force
```

## 7. Verify

- Web domain loads the Inertia app over HTTPS.
- A referee scoring update on `/referee/court/...` propagates live to the
  public `/t/...` page (websocket), not just on the 5s poll fallback.
- If the websocket fails to connect, the pages still update via polling — that
  is the designed fallback when the `reverb` prop is null or the WS is down.

---

### Env split cheat-sheet

| Var | Web | Reverb |
|-----|-----|--------|
| `APP_KEY`, `DB_*`, `CACHE_STORE`, `QUEUE_CONNECTION` | ✅ | ✅ |
| `REVERB_APP_ID/KEY/SECRET`, `BROADCAST_CONNECTION` | ✅ | ✅ |
| `REVERB_HOST/PORT/SCHEME` | ✅ (shared to browser) | ✅ |
| `APP_URL`, `ASSET_URL`, `SESSION_*`, `SANCTUM_STATEFUL_DOMAINS` | ✅ | optional |
| Start command | `start-web.sh` (default) | `start-reverb.sh` (override) |
| `$PORT` use | nginx listen port | reverb bind port |
