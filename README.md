# Tournment (Laravel)

Badminton tournament management — Laravel 11 + Inertia + Vue 3 + Postgres + Reverb.

## Quick start

```bash
# 1. Install PHP dependencies
composer install

# 2. Install JS dependencies (Bun)
bun install

# 3. Start the Sail stack (Postgres, Redis, Mailpit, Reverb)
./vendor/bin/sail up -d

# 4. Run migrations + seeders
./vendor/bin/sail artisan migrate --seed

# 5. Start the Vite dev server
bun run dev
```

The app is now reachable at `http://localhost`.

## Services

- **App**: http://localhost
- **Telescope** (request/query/job inspector): http://localhost/telescope
- **Reverb** (WebSocket server for real-time scoring): ws://localhost:8080
- **Mailpit** (mail catcher): http://localhost:8025

## Roles

Seeded users (see `database/seeders/`):

- `ADMIN` — full tournament/court/category/player/team/match/user CRUD
- `REFEREE` — assigned-court dashboard + live scoring actions
- Public — browse tournaments, view live scores (no auth)

## Real-time channels

Public Reverb channels (no auth needed):

- `tournament.{tournamentId}` — match/standings updates
- `court.{courtId}` — live scoring for one court

## Useful commands

```bash
# Run the test suite
./vendor/bin/sail artisan test

# Tail Reverb logs
./vendor/bin/sail logs -f reverb

# Open a tinker shell
./vendor/bin/sail artisan tinker
```
