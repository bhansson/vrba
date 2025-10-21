<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;

class GenerateProductJsonCommand extends Command
{
    protected $signature = 'products:generate-public-json {--force : Rewrite files even if unchanged}';

    protected $description = 'Generate public JSON exports for products with their latest AI generations.';

    /**
     * Map of AI prompt types to their corresponding latest relation on Product.
     *
     * @var array<string, string>
     */
    protected array $aiRelationMap = [
        ProductAiJob::PROMPT_DESCRIPTION_SUMMARY => 'latestAiDescriptionSummary',
        ProductAiJob::PROMPT_DESCRIPTION => 'latestAiDescription',
        ProductAiJob::PROMPT_USPS => 'latestAiUsp',
        ProductAiJob::PROMPT_FAQ => 'latestAiFaq',
        ProductAiJob::PROMPT_REVIEW_SUMMARY => 'latestAiReviewSummary',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $written = 0;
        $skipped = 0;

        $relations = array_values(array_filter(array_unique($this->aiRelationMap)));

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

            Product::query()
                ->where('team_id', $team->id)
                ->whereNotNull('sku')
                ->with($relations)
                ->orderBy('id')
                ->chunkById(100, function ($products) use ($team, $directory, $force, &$written, &$skipped): void {
                    foreach ($products as $product) {
                        $filePath = $this->productFilePath($directory, $product->sku);

                        if (! $force && $this->shouldSkipProduct($product, $filePath)) {
                            $skipped++;

                            continue;
                        }

                        $payload = $this->buildPayload($product, $team);

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

    protected function buildPayload(Product $product, Team $team): array
    {
        $aiPayload = [];

        foreach ($this->aiRelationMap as $promptType => $relation) {
            if (! $relation) {
                continue;
            }

            $record = $product->{$relation};

            if (! $record) {
                continue;
            }

            $aiPayload[$promptType] = [
                'content' => $record->content,
                'meta' => $record->meta ?? [],
                'updated_at' => optional($record->updated_at)->toIso8601String(),
            ];
        }

        return [
            'id' => $product->id,
            'team_id' => $product->team_id,
            'sku' => $product->sku,
            'gtin' => $product->gtin,
            'title' => $product->title,
            'description' => $product->description,
            'url' => $product->url,
            'price' => $product->price,
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
            $hash = Str::lower(Str::random(32));
        } while (Team::query()->where('public_hash', $hash)->exists());

        return $hash;
    }
}
