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
}
