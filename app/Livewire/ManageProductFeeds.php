<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductFeed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use SimpleXMLElement;

class ManageProductFeeds extends Component
{
    use WithFileUploads;

    #[Validate('nullable|string|max:255')]
    public string $feedName = '';

    #[Validate('nullable|url|max:2048')]
    public string $feedUrl = '';

    #[Validate('nullable|file|max:5120|mimetypes:text/xml,application/xml,application/rss+xml,text/csv,text/plain,application/octet-stream')]
    public $feedFile;

    public array $availableFields = [];

    public array $mapping = [
        'sku' => '',
        'gtin' => '',
        'title' => '',
        'description' => '',
        'url' => '',
        'price' => '',
    ];

    public bool $showMapping = false;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public Collection $feeds;

    protected array $lastParsed = [
        'type' => 'xml',
        'items' => null,
        'namespaces' => [],
    ];

    protected ?string $lastContentType = null;
    protected ?string $lastContentSample = null;
    protected array $lastFetchInfo = [];
    protected bool $isRefreshing = false;
    protected array $refreshStatus = [];

    public function mount(): void
    {
        $this->loadFeeds();
    }

    public function updatedFeedFile(): void
    {
        $this->reset(['statusMessage', 'errorMessage', 'availableFields', 'showMapping']);
    }

    public function loadFeeds(): void
    {
        $team = $this->currentTeam();

        $this->feeds = ProductFeed::query()
            ->withCount('products')
            ->where('team_id', $team->id)
            ->latest()
            ->get();
    }

    public function refreshFeed(int $feedId): void
    {
        $feed = ProductFeed::query()
            ->where('team_id', $this->currentTeam()->id)
            ->findOrFail($feedId);

        if (! $feed->feed_url) {
            $this->errorMessage = 'Cannot refresh uploaded feeds without a URL.';
            return;
        }

        $previousUrl = $this->feedUrl;
        $previousFile = $this->feedFile;

        $this->isRefreshing = true;
        $this->refreshStatus[$feedId] = 'refreshing';
        $this->feedUrl = $feed->feed_url;
        $this->feedFile = null;

        try {
            $content = $this->retrieveFeedContent();
            $parsed = $this->parseFeed($content);

            if ($parsed['items']->isEmpty()) {
                throw new \RuntimeException('No products were found in the supplied feed.');
            }

            $fields = $this->extractFieldsFromSample($parsed);

            if (empty($fields)) {
                throw new \RuntimeException('Could not determine available fields in the feed.');
            }

            $mapping = $feed->field_mappings ?? [];

            foreach (['sku', 'title', 'url', 'price'] as $required) {
                if (empty($mapping[$required])) {
                    throw new \RuntimeException('Feed is missing a mapping for ' . $required . '.');
                }
            }

            DB::transaction(function () use ($feed, $parsed, $mapping): void {
                $feed->forceFill([
                    'field_mappings' => $mapping,
                ])->save();

                $feed->products()->delete();

                $chunks = $parsed['items']->chunk(100);
                $type = $parsed['type'];
                $namespaces = $parsed['namespaces'];

                foreach ($chunks as $chunk) {
                    $payload = [];
                    foreach ($chunk as $item) {
                        $sku = $this->extractValue($type, $namespaces, $item, $mapping['sku'] ?? '');
                        $title = $this->extractValue($type, $namespaces, $item, $mapping['title'] ?? '');
                        $link = $this->extractValue($type, $namespaces, $item, $mapping['url'] ?? '');

                        if ($sku === '' || $title === '' || $link === '') {
                            continue;
                        }

                        $payload[] = [
                            'product_feed_id' => $feed->id,
                            'team_id' => $feed->team_id,
                            'sku' => $sku,
                            'gtin' => $this->maybeValue($type, $namespaces, $item, 'gtin', $mapping),
                            'title' => $title,
                            'description' => $this->maybeValue($type, $namespaces, $item, 'description', $mapping),
                            'url' => $link,
                            'price' => $this->extractPrice($this->extractValue($type, $namespaces, $item, $mapping['price'] ?? '')),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (! empty($payload)) {
                        Product::insert($payload);
                    }
                }

                $feed->touch();
            });

            $this->statusMessage = 'Feed refreshed successfully.';
            $this->loadFeeds();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Unable to refresh feed: ' . $e->getMessage();
        } finally {
            $this->feedUrl = $previousUrl;
            $this->feedFile = $previousFile;
            $this->isRefreshing = false;
            $this->refreshStatus[$feedId] = 'idle';
        }
    }

    public function deleteFeed(int $feedId): void
    {
        $feed = ProductFeed::query()
            ->where('team_id', $this->currentTeam()->id)
            ->withCount('products')
            ->findOrFail($feedId);

        DB::transaction(function () use ($feed): void {
            $feed->products()->delete();
            $feed->delete();
        });

        $this->statusMessage = 'Feed deleted successfully.';
        $this->loadFeeds();
    }

    public function fetchFields(): void
    {
        if ($this->isRefreshing) {
            // During automated refresh we skip interactive mapping display.
            return;
        }

        $this->resetMessages();

        if (! $this->feedUrl && ! $this->feedFile) {
            $this->errorMessage = 'Provide a feed URL or upload a feed file.';
            return;
        }

        try {
            $content = $this->retrieveFeedContent();
            logger()->debug('Feed content preview', [
                'sample' => Str::limit($content, 120),
                'starts_with' => Str::of($content)->trim()->substr(0, 5),
                'content_type' => $this->lastContentType,
                'fetch_info' => $this->lastFetchInfo,
            ]);
            $parsed = $this->parseFeed($content);

            if ($parsed['items']->isEmpty()) {
                $this->errorMessage = 'No products were found in the supplied feed.';
                return;
            }

            $fields = $this->extractFieldsFromSample($parsed);

            if (empty($fields)) {
                $this->errorMessage = 'Could not determine available fields in the feed.';
                return;
            }

            $this->availableFields = $fields;
            $this->suggestMappings($fields);
            $this->showMapping = true;
            $this->lastParsed = $parsed;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Unable to read feed: '.$e->getMessage();
            if ($this->lastContentType) {
                $this->errorMessage .= ' (Content-Type: '.$this->lastContentType.')';
            }
            if ($this->lastContentSample) {
                $this->errorMessage .= ' Sample: '.Str::limit(Str::replace('\n', ' ', $this->lastContentSample), 120);
            }
        }
    }

    public function importFeed(): void
    {
        $this->resetMessages();

        if (! $this->isRefreshing) {
            $this->validate();
        }

        if (! $this->feedUrl && ! $this->feedFile) {
            $this->errorMessage = 'Provide a feed URL or upload a feed file.';
            return;
        }

        if (! $this->isRefreshing) {
            foreach (['sku', 'title', 'url', 'price'] as $required) {
                if (empty($this->mapping[$required])) {
                    $this->errorMessage = 'Please select a field for '.$required.'.';
                    return;
                }
            }
        }

        $team = $this->currentTeam();

        try {
            $content = $this->retrieveFeedContent();
            $parsed = $this->parseFeed($content);
            $items = $parsed['items'];

            if ($items->isEmpty()) {
                $this->errorMessage = 'No products found to import.';
                return;
            }

            DB::transaction(function () use ($team, $items, $parsed): void {
                $feed = $this->findOrCreateFeed($team->id);

                $feed->forceFill([
                    'name' => $this->resolveFeedName(),
                    'feed_url' => $this->feedUrl ?: null,
                    'field_mappings' => $this->mapping,
                ])->save();

                $feed->products()->delete();

                $chunks = $items->chunk(100);

                foreach ($chunks as $chunk) {
                    $payload = [];
                    foreach ($chunk as $item) {
                        $sku = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $this->mapping['sku'] ?? '');
                        $title = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $this->mapping['title'] ?? '');
                        $link = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $this->mapping['url'] ?? '');

                        if ($sku === '' || $title === '' || $link === '') {
                            continue;
                        }

                        $payload[] = [
                            'product_feed_id' => $feed->id,
                            'team_id' => $team->id,
                            'sku' => $sku,
                            'gtin' => $this->maybeValue($parsed['type'], $parsed['namespaces'], $item, 'gtin'),
                            'title' => $title,
                            'description' => $this->maybeValue($parsed['type'], $parsed['namespaces'], $item, 'description'),
                            'url' => $link,
                            'price' => $this->extractPrice($this->extractValue($parsed['type'], $parsed['namespaces'], $item, $this->mapping['price'] ?? '')),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (! empty($payload)) {
                        Product::insert($payload);
                    }
                }
            });

            $this->reset(['feedFile']);
            $this->statusMessage = 'Feed imported successfully.';
            $this->loadFeeds();
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Failed to import feed: '.$e->getMessage();
        }
    }

    protected function resolveFeedName(): string
    {
        if ($this->feedName) {
            return $this->feedName;
        }

        if ($this->feedUrl) {
            return Str::of($this->feedUrl)->after('//')->before('?')->trim('/')->value() ?: 'Product Feed';
        }

        return 'Product Feed';
    }

    protected function retrieveFeedContent(): string
    {
        if ($this->feedFile) {
            return file_get_contents($this->feedFile->getRealPath());
        }

        $response = Http::withOptions([
            'verify' => false,
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
            ],
            'timeout' => 20,
        ])->withHeaders([
            'User-Agent' => 'VRBA-FeedFetcher/1.0 (+https://example.com)',
            'Accept' => 'text/xml,application/xml,application/rss+xml,text/csv,application/csv,text/plain;q=0.9,*/*;q=0.8',
        ])->get($this->feedUrl);

        if (! $response->successful()) {
            logger()->warning('Feed request failed', [
                'url' => $this->feedUrl,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Feed responded with status '.$response->status());
        }

        $body = $response->body();
        $this->lastContentType = $response->header('Content-Type');
        $encodingHeader = $response->header('Content-Encoding');
        $charset = $response->header('charset') ?? $response->encoding();

        $this->lastFetchInfo = [
            'url' => $this->feedUrl,
            'status' => $response->status(),
            'content_type' => $this->lastContentType,
            'encoding_header' => $encodingHeader,
            'charset' => $charset,
            'length' => strlen($body),
        ];

        $body = $this->maybeDecodeBody($body, $encodingHeader);

        logger()->debug('Feed HTTP response', [
            'url' => $this->feedUrl,
            'status' => $response->status(),
            'content_type' => $this->lastContentType,
            'encoding_header' => $encodingHeader,
            'charset' => $charset,
            'length' => strlen($body),
        ]);

        if ($charset && Str::lower($charset) !== 'utf-8') {
            $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
            if ($converted !== false) {
                $body = $converted;
            }
        }

        $this->lastContentSample = Str::limit($body, 1000);

        return $body;
    }

    protected function parseFeed(string $content): array
    {
        $trimmed = ltrim($content);

        $looksLikeCsv = $this->isLikelyCsv($trimmed);
        $looksLikeXml = str_starts_with($trimmed, '<');

        if ($looksLikeCsv) {
            $csv = $this->parseCsv($content);
            if ($csv['items']->isNotEmpty()) {
                return $csv;
            }
            logger()->debug('CSV parse returned no items despite heuristic', ['url' => $this->feedUrl]);
        }

        if ($looksLikeXml) {
            $xml = $this->parseXml($content);
            if ($xml['items']->isNotEmpty()) {
                return $xml;
            }
            logger()->debug('XML parse returned no items despite heuristic', ['url' => $this->feedUrl]);
        }

        if (! $looksLikeCsv) {
            $csv = $this->parseCsv($content);
            if ($csv['items']->isNotEmpty()) {
                return $csv;
            }
        }

        if (! $looksLikeXml) {
            $xml = $this->parseXml($content);
            if ($xml['items']->isNotEmpty()) {
                return $xml;
            }
        }

        logger()?->error('Feed parse failed for both CSV and XML', [
            'url' => $this->feedUrl,
            'content_type' => $this->lastContentType,
            'fetch_info' => $this->lastFetchInfo,
        ]);

        throw new \RuntimeException('Feed could not be parsed as XML or CSV.');
    }

    protected function parseXml(string $content): array
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (! $xml) {
            $errors = collect(libxml_get_errors())->map(fn ($err) => trim($err->message));
            libxml_clear_errors();
            if ($errors->isNotEmpty()) {
                logger()->debug('XML parse errors', ['errors' => $errors->take(5)]);
            }
            return ['type' => 'xml', 'items' => collect(), 'namespaces' => []];
        }

        if (isset($xml->channel->item)) {
            $items = $this->collectXmlItems($xml->channel->item);
        } elseif (isset($xml->entry)) {
            $items = $this->collectXmlItems($xml->entry);
        } else {
            $items = collect();
        }

        return [
            'type' => 'xml',
            'items' => $items,
            'namespaces' => $xml->getNameSpaces(true),
        ];
    }

    protected function collectXmlItems($element): Collection
    {
        if ($element instanceof SimpleXMLElement) {
            $items = [];

            foreach ($element as $child) {
                $items[] = $child;
            }

            if (empty($items)) {
                $items[] = $element;
            }

            return collect($items);
        }

        if (is_iterable($element)) {
            return collect($element);
        }

        return collect();
    }

    protected function parseCsv(string $content): array
    {
        $rows = collect();
        $headers = [];

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, str_replace(["\r\n", "\r"], "\n", $content));
        rewind($handle);

        $firstLine = '';
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $firstLine = $line;
            break;
        }

        if ($firstLine === '') {
            fclose($handle);
            return ['type' => 'csv', 'items' => collect(), 'namespaces' => []];
        }

        $delimiters = [',', ';', "\t", '|'];
        $delimiter = ',';
        $maxColumns = 0;
        $headerCandidates = [];

        foreach ($delimiters as $candidate) {
            $fields = str_getcsv($firstLine, $candidate);
            $count = count(array_filter($fields, fn ($value) => $value !== null && trim((string) $value) !== ''));

            if ($count > $maxColumns) {
                $maxColumns = $count;
                $delimiter = $candidate;
                $headerCandidates = $fields;
            }
        }

        if ($maxColumns === 0) {
            fclose($handle);
            return ['type' => 'csv', 'items' => collect(), 'namespaces' => []];
        }

        $headers = array_map(function ($header) {
            $clean = trim((string) $header);
            return ltrim($clean, "\xEF\xBB\xBF");
        }, $headerCandidates);

        $rowCount = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty(array_filter($data, fn ($value) => $value !== null && trim((string) $value) !== ''))) {
                continue;
            }

            if (count($data) !== count($headers)) {
                continue;
            }

            $rows->push(array_combine(
                $headers,
                array_map(fn ($value) => trim((string) $value), $data)
            ));
            $rowCount++;
        }

        fclose($handle);

        logger()->debug('CSV parse summary', [
            'url' => $this->feedUrl,
            'delimiter' => $delimiter,
            'headers' => $headers,
            'row_count' => $rowCount,
        ]);

        return [
            'type' => 'csv',
            'items' => $rows,
            'namespaces' => [],
        ];
    }

    protected function isLikelyCsv(string $trimmed): bool
    {
        if (Str::startsWith($trimmed, '<')) {
            return false;
        }

        if ($this->lastContentType && Str::contains(Str::lower($this->lastContentType), ['csv', 'excel'])) {
            return true;
        }

        if ($this->feedUrl && Str::endsWith(Str::lower($this->feedUrl), ['.csv', '.txt'])) {
            return true;
        }

        if ($trimmed === '') {
            return false;
        }

        return str_contains($trimmed, ',') || str_contains($trimmed, ';') || str_contains($trimmed, "\t") || str_contains($trimmed, '|');
    }

    protected function maybeDecodeBody(string $body, ?string $encodingHeader): string
    {
        if (! $encodingHeader) {
            return $body;
        }

        $encodingHeader = Str::lower($encodingHeader);

        if (str_contains($encodingHeader, 'gzip')) {
            $decoded = @gzdecode($body);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        if (str_contains($encodingHeader, 'deflate')) {
            $decoded = @gzuncompress($body);
            if ($decoded === false) {
                $decoded = @gzinflate($body);
            }
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $body;
    }

    protected function extractFieldsFromSample(array $parsed): array
    {
        if ($parsed['items']->isEmpty()) {
            return [];
        }

        $item = $parsed['items']->first();

        if ($parsed['type'] === 'csv') {
            return array_keys($item);
        }

        return $this->extractFieldsFromXmlItem($item, $parsed['namespaces']);
    }

    protected function extractFieldsFromXmlItem(SimpleXMLElement $item, array $namespaces): array
    {
        $fields = [];

        foreach ($item->children() as $child) {
            $name = $child->getName();
            $value = trim((string) $child);

            if ($value !== '') {
                $fields[$name] = true;
            }
        }

        foreach ($namespaces as $prefix => $namespace) {
            foreach ($item->children($namespace) as $child) {
                $name = ($prefix ? "{$prefix}:" : '').$child->getName();
                $value = trim((string) $child);

                if ($value !== '') {
                    $fields[$name] = true;
                }
            }
        }

        return array_keys($fields);
    }

    protected function extractValue(string $type, array $namespaces, $item, string $field): string
    {
        if (! $field) {
            return '';
        }

        if ($type === 'csv') {
            return trim((string) ($item[$field] ?? ''));
        }

        return $this->extractXmlValue($namespaces, $item, $field);
    }

    protected function extractXmlValue(array $namespaces, SimpleXMLElement $item, string $field): string
    {
        $valueNode = null;

        if (str_contains($field, ':')) {
            [$prefix, $name] = explode(':', $field, 2);

            if (isset($namespaces[$prefix])) {
                $children = $item->children($namespaces[$prefix]);
                $valueNode = $children ? $children->{$name} ?? null : null;
            }
        } else {
            $valueNode = $item->{$field} ?? null;
        }

        return $valueNode === null ? '' : trim((string) $valueNode);
    }

    protected function maybeValue(string $type, array $namespaces, $item, string $field, ?array $mappingOverride = null): ?string
    {
        $mapping = $mappingOverride ?? $this->mapping;

        $value = $this->extractValue($type, $namespaces, $item, $mapping[$field] ?? '');

        return $value !== '' ? $value : null;
    }

    protected function findOrCreateFeed(int $teamId): ProductFeed
    {
        $query = ProductFeed::query()->where('team_id', $teamId);

        if ($this->feedUrl) {
            $query->where('feed_url', $this->feedUrl);
        } else {
            $query->whereNull('feed_url')->where('name', $this->resolveFeedName());
        }

        return $query->first() ?? new ProductFeed(['team_id' => $teamId]);
    }

    protected function extractPrice(?string $input): ?float
    {
        if (! $input) {
            return null;
        }

        if (preg_match('/([-+]?[0-9]*[.,]?[0-9]+)/', $input, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return null;
    }

    protected function suggestMappings(array $fields): void
    {
        $fieldSet = collect($fields);

        $this->mapping['sku'] = $this->pickField($fieldSet, ['g:id', 'id', 'item_group_id', 'sku']);
        $this->mapping['gtin'] = $this->pickField($fieldSet, ['g:gtin', 'gtin']);
        $this->mapping['title'] = $this->pickField($fieldSet, ['g:title', 'title', 'item_title']);
        $this->mapping['description'] = $this->pickField($fieldSet, ['g:description', 'description']);
        $this->mapping['url'] = $this->pickField($fieldSet, ['g:link', 'link', 'url']);
        $this->mapping['price'] = $this->pickField($fieldSet, ['g:price', 'price']);
    }

    protected function pickField(Collection $fields, array $options): string
    {
        foreach ($options as $option) {
            if ($fields->contains($option)) {
                return $option;
            }
        }

        return '';
    }

    protected function currentTeam()
    {
        $user = Auth::user();

        if (! $user || ! $user->currentTeam) {
            throw new \RuntimeException('A team is required to submit feeds.');
        }

        return $user->currentTeam;
    }

    protected function resetMessages(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        return view('livewire.manage-product-feeds');
    }
}
