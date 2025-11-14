# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Magnifiq is a Laravel 12 + Jetstream (Livewire stack) application running on Laravel Octane with Swoole, designed for AI-powered product catalog management and marketing content generation. The platform features team-based multi-tenancy where users can import product feeds, generate marketing copy through AI templates, and create photorealistic product images via the Photo Studio feature.

## Development Environment

This project uses Docker Compose for local development with dedicated services for Octane, Vite, Redis, Horizon queue workers, scheduled tasks, and a bundled Supabase Postgres database.

### Essential Commands

**Start the development stack:**
```bash
docker compose up octane vite redis -d
```

**Run migrations:**
```bash
docker compose exec octane php artisan migrate
```

**Run tests:**
```bash
docker compose exec octane php artisan test
```

**Run a single test file:**
```bash
docker compose exec octane php artisan test --filter=TestClassName
```

**Access Octane container shell:**
```bash
docker compose exec octane bash
```

**Reload Octane workers (after code changes):**
```bash
docker compose exec octane php artisan octane:reload
```

**Start queue worker (Horizon):**
```bash
docker compose up queue -d
```

**View Horizon dashboard:**
Navigate to `http://localhost:8000/horizon` after starting the Octane service.

**Run scheduler:**
```bash
docker compose up scheduler -d
```

**Compile production assets:**
```bash
docker compose exec vite npm run build
```

**Install PHP dependencies:**
```bash
docker compose exec octane composer install
```

**Install Node dependencies:**
```bash
docker compose run --rm vite npm install
```

**Ad-hoc Artisan commands:**
```bash
docker compose exec octane php artisan tinker
docker compose exec octane php artisan make:model Example
```

(Optional: Create a `bin/php.sh` wrapper script for convenience)

**IMPORTANT**: After completing any coding task, reload Octane workers to pick up changes:
```bash
docker compose exec octane php artisan octane:reload
```

## Architecture Overview

### Multi-Tenancy Model

The application uses team-based multi-tenancy through Jetstream. All product-related data (`ProductFeed`, `Product`, `PhotoStudioGeneration`, `ProductAiJob`, `ProductAiTemplate`) is scoped to a `team_id`. Users can belong to multiple teams and switch between them via Jetstream's built-in team switcher.

### Product Feed System

The product feed architecture centers around two models:

- **ProductFeed** (`app/Models/ProductFeed.php`): Stores feed metadata including URL, language, and field mappings
- **Product** (`app/Models/Product.php`): Individual product records imported from feeds

The `ManageProductFeeds` Livewire component (`app/Livewire/ManageProductFeeds.php`) handles:
- Fetching feeds from remote URLs or uploaded files
- Parsing both XML (Google Merchant format, RSS) and CSV formats
- Auto-detecting delimiters and field mappings
- Intelligent field mapping suggestions (e.g., `g:id` → `sku`, `g:title` → `title`)
- Refreshing feeds by re-importing with preserved mappings

**Key architectural detail**: When importing or refreshing a feed, all existing products for that feed are deleted and replaced. Products are inserted in batches of 100 for performance.

### AI Content Generation System

The AI generation system supports two primary workflows:

1. **Product AI Templates** (`ProductAiTemplate` model): Reusable prompt templates for generating marketing content (summaries, descriptions, USPs, FAQs). Templates can be team-specific or global defaults. Managed via `ManageProductAiTemplates` Livewire component.

2. **Photo Studio** (`PhotoStudio` Livewire component): Multi-modal AI feature that:
   - Analyzes product images (uploaded or from catalog)
   - Extracts contextual prompts using vision models (default: `openai/gpt-4.1`)
   - Generates photorealistic product renders using image generation models (default: `google/gemini-2.5-flash-image`)
   - Stores generations in `PhotoStudioGeneration` model with team-scoped gallery
   - Supports optional creative briefs to guide prompt extraction

**Queue Architecture**: All AI generation jobs flow through the `ProductAiJob` model which tracks status, progress, and metadata. Jobs are dispatched to the `ai` queue (see `config/horizon.php`) and processed by dedicated Horizon supervisors with higher memory limits (256MB) and longer timeouts (120s).

The `GeneratePhotoStudioImage` job (`app/Jobs/GeneratePhotoStudioImage.php`) handles complex AI provider response parsing, supports multiple image payload formats (base64, URLs, attachment references), automatically converts PNGs to JPGs with white backgrounds, and stores final images on the configured disk (S3 by default).

### Livewire Component Patterns

Livewire components follow these conventions:
- Located in `app/Livewire/`
- Views in `resources/views/livewire/` (kebab-case)
- Component classes use PascalCase, views use kebab-case
- Heavy use of `#[Validate]` attributes for inline validation rules
- Real-time updates via `updatedPropertyName()` lifecycle hooks
- Team scoping enforced in `mount()` and query methods

Key components:
- `ManageProductFeeds`: Product feed import and management
- `ProductsIndex`: Product browsing with search
- `ProductShow`: Individual product detail view
- `ManageProductAiTemplates`: AI template CRUD
- `PhotoStudio`: Image analysis and generation workflow
- `AiJobsIndex`: AI job status monitoring

### Storage and File Handling

- Product feed files can be uploaded directly or fetched from URLs
- Photo Studio generations stored on configurable disk (`PHOTO_STUDIO_GENERATION_DISK`, defaults to `s3`)
- Storage paths follow pattern: `photo-studio/{team_id}/{Y/m/d}/{uuid}.{ext}`
- All generated images have public visibility for easy URL access

### Authentication and Authorization

- Jetstream with Sanctum for API token auth
- Fortify handles registration, login, password resets
- Team policies in `app/Policies/TeamPolicy.php`
- All authenticated routes require `['auth:sanctum', 'verified']` middleware
- Horizon dashboard protected by same auth middleware

### OpenRouter Integration

The application uses the `moe-mizrak/laravel-openrouter` package for AI provider access. Configure via:
- `OPENROUTER_API_KEY` (required for AI features)
- `OPENROUTER_MODEL` (default: `openrouter/auto`)
- `OPENROUTER_PHOTO_STUDIO_MODEL` (vision model, default: `openai/gpt-4.1`)
- `OPENROUTER_PHOTO_STUDIO_IMAGE_MODEL` (image gen model, default: `google/gemini-2.5-flash-image`)

The system gracefully degrades when API keys are missing—UI displays error messages rather than failing silently.

### Database and Migrations

- Primary database: Supabase Postgres (bundled in Docker Compose as `supabase-db`)
- Host: `localhost:54322` (from host), `supabase-db:5432` (from containers)
- Test database: Same Postgres instance, configured in `.env.testing`
- Migrations in `database/migrations/`
- Set `RUN_MIGRATIONS=true` for automatic migration on container start

Key tables:
- `users`, `teams`, `team_user`, `team_invitations` (Jetstream)
- `product_feeds`, `products` (catalog system)
- `product_ai_templates`, `product_ai_generations`, `product_ai_jobs` (AI content)
- `photo_studio_generations` (Photo Studio)

## Testing Strategy

Tests are organized in `tests/Feature/` and `tests/Unit/`. Feature tests cover:
- Authentication flows (Jetstream/Fortify)
- Team management (creation, invitations, member removal)
- API token management
- Product feed parsing and import
- AI template management
- Photo Studio workflows
- Product browsing and search

**Important**: Tests run against the `supabase-db` service. Ensure it's running before executing tests:
```bash
docker compose up supabase-db -d
docker compose exec octane php artisan test
```

### Testing Patterns

- Use factories for test data setup (located in `database/factories/`)
- Stub HTTP calls using `Http::fake()` for external services (OpenRouter, product feeds)
- Team scoping is critical—always create and authenticate users with teams in tests
- Livewire component tests use `Livewire::test()` for interaction testing

## Configuration Notes

### Environment Variables

Key variables beyond standard Laravel config:

**OpenRouter Configuration:**
- `OPENROUTER_API_KEY`: Required for all AI features (required)
- `OPENROUTER_MODEL`: Default model for product AI templates (default: `openrouter/auto`)
- `OPENROUTER_PHOTO_STUDIO_MODEL`: Vision model for prompt extraction (default: `openai/gpt-4.1`)
- `OPENROUTER_PHOTO_STUDIO_IMAGE_MODEL`: Image generation model (default: `google/gemini-2.5-flash-image`)
- `OPENROUTER_API_ENDPOINT`: Custom API endpoint if needed (optional)
- `OPENROUTER_API_TITLE`: App title for OpenRouter tracking (optional)
- `OPENROUTER_API_REFERER`: Referer header for OpenRouter (optional)
- `OPENROUTER_API_TIMEOUT`: Request timeout in seconds (optional)

**Storage:**
- `PHOTO_STUDIO_GENERATION_DISK`: Storage disk for generated images (default: `s3`)

**Database (Supabase):**
- `SUPABASE_DB_USER`: Postgres username (default: `postgres`)
- `SUPABASE_DB_PASSWORD`: Postgres password (default: `supabase`)
- `SUPABASE_URL`: Hosted Supabase URL (optional, for future integration)
- `SUPABASE_ANON_KEY`: Anon key for Supabase API (optional, keep empty for local dev)
- `SUPABASE_SERVICE_ROLE_KEY`: Service role key (optional, keep empty for local dev)

**Development:**
- `OCTANE_WATCH`: Enable file watching for auto-reload (default: `true`)
- `RUN_MIGRATIONS`: Auto-run migrations on container start (default: `false`)

**Important**: Rotate database credentials in lockstep across `.env`, Supabase configuration, and deployment secrets when moving to hosted services.

### Service Configuration

- **Octane**: Runs on port 8000, uses Swoole, file watching enabled by default
- **Vite**: Runs on port 5173, hot module replacement for frontend assets
- **Redis**: Port 6379, used for cache, sessions, and Horizon queue backend
- **Horizon**: Two supervisors—`supervisor-default` (default queue) and `supervisor-ai` (ai queue with higher resources)

## Frontend Stack

- **Tailwind CSS**: Utility-first styling
- **Alpine.js**: Lightweight JavaScript framework for interactivity
- **Livewire**: Server-side rendering with reactive components
- **Blade templates**: Server-side templating in `resources/views/`
- **Vite**: Asset bundling and hot reload

Frontend assets are in `resources/js/app.js` and `resources/css/app.css`. Livewire handles most interactivity, with Alpine sprinkled in for UI enhancements (dropdowns, modals, etc.).

## Coding Standards

### PHP Style

- **PSR-12** compliance with four-space indentation
- Namespace alignment with directory structure (e.g., `App\\Http\\Controllers`, `App\\Livewire`)
- Livewire components:
  - Class names: PascalCase (e.g., `ManageProductFeeds`)
  - Blade views: kebab-case under `resources/views/livewire/` (e.g., `manage-product-feeds.blade.php`)

### Code Quality Tools

If you introduce linting or formatting tools (Laravel Pint, ESLint, Prettier), document them and integrate via Composer/NPM scripts rather than relying on local IDE configuration. For example:
```bash
composer run lint    # Future: Laravel Pint
composer run analyse # Future: PHPStan or Larastan
```

### Testing Standards

- Mirror production namespaces in `tests/` for autoloader simplicity
- Target **~80% statement coverage**; document gaps in PR descriptions
- Use factories for test data setup (located in `database/factories/`)
- **Stub all outbound HTTP calls** using `Http::fake()` for:
  - Product feed ingestion
  - OpenRouter API calls
  - External service integrations
- Seed data via factory helpers instead of hardcoding IDs
- Always run tests inside the Octane container to match CI environment

### Commit Conventions

Use **Conventional Commits** format for clean changelog generation:
- `feat:` - New features
- `fix:` - Bug fixes
- `chore:` - Maintenance tasks
- `docs:` - Documentation updates
- `test:` - Test additions or updates

Scope each commit to a single logical change and describe the behavior adjustment, not the implementation details.

**Example:**
```
feat: add CSV delimiter auto-detection to product feed parser

Automatically detect comma, semicolon, tab, or pipe delimiters
when parsing uploaded CSV product feeds.
```

### Pull Request Requirements

PRs must include:
1. **Summary**: Clear description of changes and motivation
2. **Testing evidence**:
   - Output from `php artisan test` for backend changes
   - Screenshots or screen recordings for UI work
3. **Linked issues**: Reference related GitHub issues
4. **Rollback considerations**: Document any migration or deployment risks
5. **Review approval**: Request review before merging
6. **CI status**: Wait for all checks to pass

Use draft PRs for work in progress.

## IDE and Tooling Notes

### JetBrains Project Settings

JetBrains project settings already track PHP quality tools. **Avoid committing per-user files** under `.idea/` to the repository. Share tooling configuration via:
- Composer scripts for linting and analysis
- Docker Compose commands (documented above)
- Helper scripts in `bin/` directory if needed

This ensures consistent development experience across the team without IDE lock-in.

## Common Workflow Patterns

### Adding a New AI Feature

1. Create a job class extending `ShouldQueue` in `app/Jobs/`
2. Update `ProductAiJob` model constants if needed
3. Add queue dispatch in relevant Livewire component
4. Create corresponding model for storing results
5. Add migration for new table
6. Update Horizon config if custom queue required
7. Add monitoring in `AiJobsIndex` component

### Adding Product Feed Support for New Format

1. Update `ManageProductFeeds::parseFeed()` to detect new format
2. Add parser method (e.g., `parseJson()`) following existing patterns
3. Update `extractFieldsFromSample()` for field detection
4. Add test coverage in `tests/Unit/ManageProductFeedsTest.php`
5. Update field mapping suggestions in `suggestMappings()`

### Extending Product Attributes

1. Add column to `products` table via migration
2. Update `Product` model fillable/casts
3. Add mapping field to `ManageProductFeeds` component
4. Update `importFeed()` to map new field
5. Update product display views in `resources/views/products/`
6. Add corresponding tests
