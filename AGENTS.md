# Repository Guidelines

## Project Structure & Module Organization
Backend code lives in `app/` with tests mirrored in `tests/Feature` and `tests/Unit`. Jetstream’s Livewire views live under `resources/views` (dashboard, profile, teams) with shared components in `resources/views/components`, while Alpine-driven scripts stay in `resources/js/app.js`. Product feed logic resides in `app/Livewire/ManageProductFeeds.php` with products stored via `ProductFeed` / `Product` models. Database migrations are in `database/migrations`; keep factories under `database/factories` aligned with model changes.

## Build, Test, and Development Commands
Use the provided containers for consistency:

```sh
docker compose exec octane composer install
docker compose exec octane php artisan migrate
docker compose run --rm vite npm install
docker compose exec octane php artisan test
docker compose run --rm vite npm run build
```

Use `bin/php.sh` for ad-hoc Artisan or tinker commands inside the Octane container. Keep `composer run lint` / `composer run analyse` handy if you add coding standards later.

## Coding Style & Naming Conventions
Adhere to PSR-12 with four-space indentation in PHP. Align namespaces with directory structure (`App\\Http\\...`). Livewire components should keep PascalCase class names and blade views under `resources/views/livewire`. If you introduce linting/formatting tooling (Pint, ESLint, Prettier), document it and bake it into Composer/NPM scripts instead of relying on local IDE rules.

## Testing Guidelines
Mirror production namespaces in `tests/` to keep autoloaders simple. Feature tests already cover authentication, password resets, API tokens, and team flows—extend them when you touch those areas. Target ~80 % statement coverage and call out gaps in PR descriptions. Use factories for setup; seed data via helpers instead of hardcoding IDs, and stub outbound HTTP calls (feed ingestion, OpenRouter summaries) instead of hitting real services.

## Commit & Pull Request Guidelines
The history is new, so adopt Conventional Commits (`feat:`, `fix:`, `chore:`) to make changelog generation effortless. Scope each commit to a single logical change and describe the behaviour adjustment, not the implementation. PRs must include a summary, testing evidence (`composer test` output or screenshots for UI work), linked issues, and roll-back considerations. Request review before merging and wait for CI to pass; use draft PRs for work in progress.

## Environment & Tooling
JetBrains project settings already track PHP quality tools—avoid committing per-user files under `.idea/`. Share tooling via Composer scripts or Docker helpers so everyone has the same defaults. The local Postgres instance runs as `supabase-db`; credentials live in `.env` (`SUPABASE_DB_*`) and are mounted into containers. Keep the anon/service role placeholders empty unless you integrate hosted Supabase services, and rotate credentials in lockstep across `.env`, Supabase, and deployment secrets. Configure `OPENROUTER_API_KEY` (plus optional `OPENROUTER_MODEL`, `OPENROUTER_API_ENDPOINT`, `OPENROUTER_API_TITLE`, `OPENROUTER_API_REFERER`, `OPENROUTER_API_TIMEOUT`) if you want the product AI summary button to call OpenRouter; without it the UI surfaces an inline error instead of making the request.
