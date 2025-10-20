# VRBA Laravel Stack

This repository ships with a Docker Compose setup that runs Laravel Octane on Swoole for a fast local development loop. The stack also includes dedicated services for queues, scheduled tasks, Redis, and the Vite dev server.

## Prerequisites
- Docker Desktop 24+ (or compatible linux Docker Engine)
- Make sure ports `8000`, `5173`, and `6379` are free

## First-Time Setup
1. Copy the environment file if you have not already: `cp .env.example .env`
2. Build the PHP image: `docker compose build octane`
3. Boot the stack: `docker compose up octane vite redis -d`
   - The first container start will automatically `composer require laravel/octane`, install PHP dependencies, and create an app key if missing. Commit the updated `composer.json` / `composer.lock` files afterwards.

Visit `http://localhost:8000` for Octane and `http://localhost:5173` for the Vite dev server.

## Running Supporting Services
- **Queue worker**: `docker compose up queue -d` (runs `php artisan horizon`)
- **Scheduler**: `docker compose up scheduler -d` (runs `php artisan schedule:work`)
- **Logs / shell access**: `docker compose exec octane bash`
- **Supabase Postgres**: `docker compose up supabase-db -d` (bundled development database)

## Common Commands
```bash
# Run a one-off artisan command
docker compose exec octane php artisan migrate

# Run the PHPUnit test suite
docker compose exec octane php artisan test

# Compile production assets
docker compose exec vite npm run build
```

## Jetstream (Livewire + Teams)
- Jetstream ships with the Livewire stack, Sanctum-powered API tokens, and team management enabled (invitations on by default).
- UI is rendered through Blade and Livewire components under `resources/views` (dashboard, profile, terms, policy) with reusable view components in `resources/views/components`.
- Livewire + Alpine handle interactivity; core scripts live in `resources/js/app.js` and `resources/js/bootstrap.js`.
- After pulling changes run `docker compose exec octane php artisan migrate` to stay current with Jetstream's auth + team tables.
- Fortify and Jetstream customization points remain in `bootstrap/providers.php` and `app/Actions/Jetstream/*` for extending registration, team roles, or onboarding flows.

## Product Feed Dashboard
- The authenticated dashboard now lets each team submit a Google Merchant feed (URL or XML upload), map feed fields to internal attributes, and import products.
- Imported products are stored per team in the `product_feeds` / `products` tables (`php artisan migrate` required). Re-importing a feed overwrites its previous product set.
- Feed parsing supports Google-style XML and CSV feeds; map any custom columns manually during import.
- If outbound HTTP is unavailable, upload the XML feed file directly in the dashboard form—the importer will parse the uploaded file instead of performing a remote request.

## Product Browser & AI Summaries
- Navigate to `/products` to browse all imported products for the current team. Each row expands to show full product details.
- Click “Generate Summary” beside a product to queue a short marketing snippet generation using OpenAI (defaults to model `gpt-5`). Track progress under **AI Jobs** (navigation) while Horizon works the queue.
- Configure your API key in `.env` (`OPENAI_API_KEY`, optional `OPENAI_MODEL`, `OPENAI_BASE_URL`). Without a key the button will show an error when you attempt to enqueue a job.

## Supabase Database (Self-Hosted)
- A local Supabase Postgres instance is bundled as `supabase-db` in `docker-compose.yml`. Start it with `docker compose up supabase-db -d`. Default credentials live in `.env` (`SUPABASE_DB_USER=postgres`, `SUPABASE_DB_PASSWORD=supabase`).
- The database listens on `localhost:54322` for host tooling and on `supabase-db:5432` from inside the Compose network. Update `.env` if you need different credentials or ports.
- `SUPABASE_URL`, `SUPABASE_ANON_KEY`, and `SUPABASE_SERVICE_ROLE_KEY` are placeholders so future integrations can talk to hosted Supabase services. For the local Postgres-only setup these values are optional.
- Apply and refresh migrations with `docker compose exec octane php artisan migrate`. Set `RUN_MIGRATIONS=true` if you want the Octane container to run migrations automatically on boot.
- Keep `DB_SSLMODE=prefer` for the local container. When targeting managed Supabase, change the host/port and set `DB_SSLMODE=require`.

## Environment Notes
- Redis, cache, and session hosts are already wired to the `redis` container – no need for local services.
- Set `RUN_MIGRATIONS=true` in your `.env` if you want the Octane container to migrate automatically on boot.
- Node.js is bundled in the PHP image, so Octane file watching runs by default; switch it off with `OCTANE_WATCH=false` if you want a quieter container.
- The PHP image extends `php:8.3-cli`, enabling Swoole, Redis, SQLite, MySQL, and PostgreSQL extensions. Tune PHP settings in `.docker/octane/php.ini`.
- Automated tests now run against the `supabase-db` Postgres container (see `.env.testing`); ensure the service is up before running `php artisan test`.
- OpenAI access is optional; set `OPENAI_API_KEY` and related env vars when you want to enable product summaries.

## Stopping & Cleanup
- Stop services without removing containers: `docker compose stop`
- Remove containers and volumes (including Redis data and node modules): `docker compose down -v`
