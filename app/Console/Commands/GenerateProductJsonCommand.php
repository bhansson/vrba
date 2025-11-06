<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductAiTemplate;
use App\Models\Team;
use App\Support\ProductAiContentParser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;

class GenerateProductJsonCommand extends Command
{
    protected $signature = 'products:generate-public-json {--force : Rewrite files even if unchanged}';

    protected $description = 'Generate public JSON exports for products with their latest AI generations.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $written = 0;
        $skipped = 0;

        $teams = Team::query()
            ->orderBy('id')
            ->get();

        if ($teams->isEmpty()) {
            $this->info('No teams are configured for public exports.');

            return Command::SUCCESS;
        }

        foreach ($teams as $team) {
            $team = $this->ensureTeamHash($team);

            $directory = $this->teamDirectory($team);
            File::ensureDirectoryExists($directory);

            ProductAiTemplate::syncDefaultTemplates();

            $templates = $this->templatesForTeam($team);

            if ($templates->isEmpty()) {
                $this->comment(sprintf('Skipping team %d (%s): no AI templates available for export.', $team->id, $team->name));

                continue;
            }

            $templateIds = $templates->pluck('id');

            Product::query()
                ->where('team_id', $team->id)
                ->whereNotNull('sku')
                ->with([
                    'feed:id,language',
                    'aiGenerations' => static function ($query) use ($templateIds) {
                        $query->with('template')
                            ->whereIn('product_ai_template_id', $templateIds)
                            ->orderByDesc('updated_at')
                            ->orderByDesc('id');
                    },
                ])
                ->orderBy('id')
                ->chunkById(100, function ($products) use ($team, $directory, $force, $templates, &$written, &$skipped): void {
                    foreach ($products as $product) {
                        $filePath = $this->productFilePath($directory, $product->sku);

                        if (! $force && $this->shouldSkipProduct($product, $filePath)) {
                            $skipped++;

                            continue;
                        }

                        $payload = $this->buildPayload($product, $team, $templates);

                        try {
                            $json = json_encode(
                                $payload,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                            );
                        } catch (JsonException $exception) {
                            $this->error('Failed to encode JSON for product '.$product->sku.': '.$exception->getMessage());
                            $skipped++;

                            continue;
                        }

                        File::put($filePath, $json.PHP_EOL);
                        $written++;
                    }
                });
        }

        $this->info(sprintf('Generated %d product export(s); skipped %d unchanged product(s).', $written, $skipped));

        return Command::SUCCESS;
    }

    protected function templateSlugs(): array
    {
        return [
            ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY,
            ProductAiTemplate::SLUG_DESCRIPTION,
            ProductAiTemplate::SLUG_USPS,
            ProductAiTemplate::SLUG_FAQ,
        ];
    }

    protected function templatesForTeam(Team $team): Collection
    {
        $slugs = $this->templateSlugs();

        $templates = ProductAiTemplate::query()
            ->forTeam($team->id)
            ->active()
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');

        return collect($slugs)
            ->map(static fn (string $slug) => $templates->get($slug))
            ->filter();
    }

    protected function buildPayload(Product $product, Team $team, Collection $templates): array
    {
        $aiPayload = [];

        foreach ($templates as $template) {
            $generation = $product->aiGenerations
                ->where('product_ai_template_id', $template->id)
                ->sortByDesc(fn ($record) => (($record->updated_at?->timestamp ?? 0) * 1000000) + $record->id)
                ->first();

            if (! $generation) {
                continue;
            }

            $aiPayload[$template->slug] = [
                'content' => $this->normalizePayloadContent($template, $generation->content),
                'updated_at' => optional($generation->updated_at)->toIso8601String(),
            ];
        }

        return [
            'id' => $product->id,
            'team_id' => $product->team_id,
            'team_hash' => $team->public_hash,
            'sku' => $product->sku,
            'gtin' => $product->gtin,
            'title' => $product->title,
            'description' => $product->description,
            'url' => $product->url,
            'language' => optional($product->feed)->language,
            'created_at' => optional($product->created_at)->toIso8601String(),
            'updated_at' => optional($product->updated_at)->toIso8601String(),
            'ai' => $aiPayload,
        ];
    }

    protected function shouldSkipProduct(Product $product, string $filePath): bool
    {
        if (! File::exists($filePath)) {
            return false;
        }

        if (! $product->updated_at instanceof Carbon) {
            return false;
        }

        $fileUpdatedAt = Carbon::createFromTimestamp(File::lastModified($filePath));

        return $product->updated_at->lessThanOrEqualTo($fileUpdatedAt);
    }

    protected function productFilePath(string $directory, string $sku): string
    {
        $filename = $this->sanitizeSkuForFilename($sku).'.json';

        return $directory.DIRECTORY_SEPARATOR.$filename;
    }

    protected function sanitizeSkuForFilename(string $sku): string
    {
        $clean = str_replace(
            ['/', '\\', ':', '*', '?', '"', '<', '>', '|'],
            '-',
            $sku
        );

        $clean = preg_replace('/\s+/', '_', $clean ?? '');
        $clean = preg_replace('/[^\pL\pN._-]/u', '_', $clean ?? '');
        $clean = trim((string) $clean, '._-');

        if ($clean === '') {
            return Str::lower(Str::random(16));
        }

        return $clean;
    }

    protected function teamDirectory(Team $team): string
    {
        return public_path('edge'.DIRECTORY_SEPARATOR.$team->public_hash);
    }

    protected function normalizePayloadContent(ProductAiTemplate $template, mixed $content): mixed
    {
        return match ($template->contentType()) {
            'usps' => ProductAiContentParser::parseUsps($content),
            'faq' => ProductAiContentParser::parseFaq($content),
            default => is_string($content) ? trim($content) : $content,
        };
    }

    protected function ensureTeamHash(Team $team): Team
    {
        if ($team->public_hash) {
            return $team;
        }

        $team->forceFill([
            'public_hash' => $this->generateUniqueTeamHash(),
        ])->save();

        return $team->refresh();
    }

    protected function generateUniqueTeamHash(): string
    {
        do {
            $hash = Str::uuid()->toString();
        } while (Team::query()->where('public_hash', $hash)->exists());

        return $hash;
    }
}
