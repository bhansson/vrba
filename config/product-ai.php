<?php

use App\Jobs\GenerateProductDescription;
use App\Jobs\GenerateProductDescriptionSummary;
use App\Jobs\GenerateProductFaq;
use App\Jobs\GenerateProductUsps;
use App\Models\ProductAiDescription;
use App\Models\ProductAiDescriptionSummary;
use App\Models\ProductAiFaq;
use App\Models\ProductAiJob;
use App\Models\ProductAiUsp;

return [
    'defaults' => [
        'history_limit' => 10,
        'options' => [],
        'description_excerpt_limit' => 600,
    ],

    'actions' => [
        'generate_summary' => [
            ProductAiJob::PROMPT_DESCRIPTION_SUMMARY,
            ProductAiJob::PROMPT_DESCRIPTION,
            ProductAiJob::PROMPT_USPS,
            ProductAiJob::PROMPT_FAQ,
        ],
    ],

    'generations' => [
        ProductAiJob::PROMPT_DESCRIPTION_SUMMARY => [
            'label' => 'Description Summary',
            'job' => GenerateProductDescriptionSummary::class,
            'model' => ProductAiDescriptionSummary::class,
            'meta_key' => 'description_summary_record_id',
            'history_limit' => 10,
            'prompts' => [
                'system' => 'You are a product marketing assistant who writes short, high-converting product summaries.',
                'user' => <<<'PROMPT'
Create a concise, high-converting marketing summary (maximum 60 words) for the product below. Highlight what makes it stand out and keep the tone upbeat yet trustworthy. Mention the price only if a numeric value is provided.

Title: {{ title }}
Description: {{ description_excerpt }}
SKU: {{ sku }}
Price: {{ price }}
PROMPT,
                'description_excerpt_limit' => 400,
            ],
            'options' => [],
        ],

        ProductAiJob::PROMPT_DESCRIPTION => [
            'label' => 'Description',
            'job' => GenerateProductDescription::class,
            'model' => ProductAiDescription::class,
            'meta_key' => 'description_record_id',
            'history_limit' => 5,
            'prompts' => [
                'system' => 'You are an ecommerce conversion copywriter who crafts persuasive, human-sounding product descriptions.',
                'user' => <<<'PROMPT'
Write a compelling product description between 130 and 180 words based on the details below. Focus on benefits, address likely objections, and finish with a short call to action. Use short paragraphs and keep formatting plain text.

Title: {{ title }}
Current description: {{ description_excerpt }}
SKU: {{ sku }}
Price: {{ price }}
URL: {{ url }}
PROMPT,
                'description_excerpt_limit' => 1200,
            ],
            'options' => [],
        ],

        ProductAiJob::PROMPT_USPS => [
            'label' => 'Unique Selling Points',
            'job' => GenerateProductUsps::class,
            'model' => ProductAiUsp::class,
            'meta_key' => 'usp_record_id',
            'history_limit' => 5,
            'prompts' => [
                'system' => 'You are a conversion-focused marketer skilled at extracting concrete unique selling points from product data.',
                'user' => <<<'PROMPT'
List exactly four concise unique selling points for the product below. Each USP should be a single sentence fragment of fewer than 20 words. Avoid generic phrases like "high quality" or "great value" unless you have supporting detail.

Title: {{ title }}
Summary of features: {{ description_excerpt }}
SKU: {{ sku }}
Price: {{ price }}
PROMPT,
                'description_excerpt_limit' => 800,
            ],
            'options' => [],
        ],

        ProductAiJob::PROMPT_FAQ => [
            'label' => 'FAQ',
            'job' => GenerateProductFaq::class,
            'model' => ProductAiFaq::class,
            'meta_key' => 'faq_record_id',
            'history_limit' => 5,
            'prompts' => [
                'system' => 'You draft helpful pre-sale FAQ entries for ecommerce products.',
                'user' => <<<'PROMPT'
Create three customer-facing FAQ entries for the product below. For each entry, provide a concise question and a two-sentence answer. Address common concerns (fit, compatibility, materials, shipping, warranty, installation, etc.) when relevant. Use plain text with the format:

Q: Question 1
A: Answer 1

Q: Question 2
A: Answer 2

Q: Question 3
A: Answer 3

Title: {{ title }}
Product details: {{ description_excerpt }}
SKU: {{ sku }}
Price: {{ price }}
PROMPT,
                'description_excerpt_limit' => 1200,
            ],
            'options' => [],
        ],
    ],
];
