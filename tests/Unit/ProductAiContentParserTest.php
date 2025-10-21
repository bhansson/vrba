<?php

namespace Tests\Unit;

use App\Support\ProductAiContentParser;
use PHPUnit\Framework\TestCase;

class ProductAiContentParserTest extends TestCase
{
    public function test_it_parses_usps_from_multiline_string(): void
    {
        $input = "- Fast setup\n* Durable frame\n2. Extended warranty";

        $result = ProductAiContentParser::parseUsps($input);

        $this->assertSame(
            ['Fast setup', 'Durable frame', 'Extended warranty'],
            $result
        );
    }

    public function test_it_parses_faq_from_structured_string(): void
    {
        $input = <<<FAQ
Q: How long is the warranty?
A: All purchases include a two-year warranty.

Q: Can I return the product?
A: Returns are accepted within 30 days.
FAQ;

        $result = ProductAiContentParser::parseFaq($input);

        $this->assertCount(2, $result);
        $this->assertSame('How long is the warranty?', $result[0]['question']);
        $this->assertSame('All purchases include a two-year warranty.', $result[0]['answer']);
        $this->assertSame('Can I return the product?', $result[1]['question']);
        $this->assertSame('Returns are accepted within 30 days.', $result[1]['answer']);
    }
}
