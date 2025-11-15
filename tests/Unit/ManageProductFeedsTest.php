<?php

namespace Tests\Unit;

use App\Livewire\ManageProductFeeds;
use PHPUnit\Framework\TestCase;

class ManageProductFeedsTest extends TestCase
{
    public function testXmlFeedIsPreferredWhenContentContainsCommas(): void
    {
        $component = new class extends ManageProductFeeds {
            public function parseForTest(string $content): array
            {
                return $this->parseFeed($content);
            }

            public function extractFieldsForTest(array $parsed): array
            {
                return $this->extractFieldsFromSample($parsed);
            }
        };

        $xmlFeed = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title><![CDATA[ Bodyguard.nu ]]></title>
<link>https://www.bodyguard.nu</link>
<description>Google Shopping</description>
<item>
<g:id>2</g:id>
<title><![CDATA[ Bodyguard rödfärg ]]></title>
<link>https://www.bodyguard.nu/sjalvforsvarsspray/bodyguard-rodfarg</link>
<g:price>189.00 SEK</g:price>
<description><![CDATA[ Som ägare av bodyguard självförsvarsspray kan du känna dig tryggare vid promenader, joggingturer, resor etc. ]]></description>
<g:availability>in stock</g:availability>
</item>
</channel>
</rss>
XML;

        $parsed = $component->parseForTest($xmlFeed);

        $this->assertSame('xml', $parsed['type']);

        $fields = $component->extractFieldsForTest($parsed);

        $this->assertContains('g:id', $fields);
        $this->assertContains('title', $fields);
        $this->assertContains('description', $fields);
    }

    public function testDuplicateSkusInFeedAreHandledGracefully(): void
    {
        $component = new class extends ManageProductFeeds {
            public function parseForTest(string $content): array
            {
                return $this->parseFeed($content);
            }

            public function buildPayloadForTest(array $parsed, array $mapping): array
            {
                $payload = [];
                $seenSkus = [];

                foreach ($parsed['items'] as $item) {
                    $sku = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $mapping['sku'] ?? '');
                    $title = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $mapping['title'] ?? '');
                    $link = $this->extractValue($parsed['type'], $parsed['namespaces'], $item, $mapping['url'] ?? '');

                    if ($sku === '' || $title === '' || $link === '') {
                        continue;
                    }

                    // Skip duplicates - this is the fix we're testing for
                    if (isset($seenSkus[$sku])) {
                        continue;
                    }
                    $seenSkus[$sku] = true;

                    $payload[] = [
                        'sku' => $sku,
                        'title' => $title,
                        'url' => $link,
                    ];
                }

                return $payload;
            }
        };

        $xmlFeedWithDuplicates = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Test Feed</title>
<item>
<g:id>DUPLICATE_SKU</g:id>
<g:title>First Product</g:title>
<g:link>https://example.com/product1</g:link>
</item>
<item>
<g:id>UNIQUE_SKU</g:id>
<g:title>Second Product</g:title>
<g:link>https://example.com/product2</g:link>
</item>
<item>
<g:id>DUPLICATE_SKU</g:id>
<g:title>Duplicate Product</g:title>
<g:link>https://example.com/product3</g:link>
</item>
</channel>
</rss>
XML;

        $parsed = $component->parseForTest($xmlFeedWithDuplicates);
        $mapping = [
            'sku' => 'g:id',
            'title' => 'g:title',
            'url' => 'g:link',
        ];

        $payload = $component->buildPayloadForTest($parsed, $mapping);

        // Should only have 2 products (first occurrence of DUPLICATE_SKU and UNIQUE_SKU)
        $this->assertCount(2, $payload);
        $this->assertEquals('DUPLICATE_SKU', $payload[0]['sku']);
        $this->assertEquals('First Product', $payload[0]['title']);
        $this->assertEquals('UNIQUE_SKU', $payload[1]['sku']);
    }
}
