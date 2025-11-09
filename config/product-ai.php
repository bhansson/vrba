<?php

use App\Models\ProductAiTemplate;

return [
    'defaults' => [
        'history_limit' => 10,
        'options' => [
            'max_tokens' => 3000,
        ],
        'description_excerpt_limit' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Context Variables
    |--------------------------------------------------------------------------
    |
    | Each template can opt-in to the variables below. They are exposed to the
    | UI as selectable tokens and resolved at runtime when the prompt is built.
    |
    */
    'context_variables' => [
        'title' => [
            'label' => 'Product Title',
            'attribute' => 'title',
            'default' => 'Untitled product',
            'clean' => 'single_line',
        ],
        'description' => [
            'label' => 'Full Description',
            'attribute' => 'description',
            'default' => 'N/A',
            'clean' => 'multiline',
        ],
        'sku' => [
            'label' => 'SKU',
            'attribute' => 'sku',
            'default' => 'N/A',
            'clean' => 'single_line',
        ],
        'brand' => [
            'label' => 'Brand',
            'attribute' => 'brand',
            'default' => 'N/A',
            'clean' => 'single_line',
        ],
        'url' => [
            'label' => 'Product URL',
            'attribute' => 'url',
            'default' => 'N/A',
            'clean' => 'single_line',
        ],
        'language' => [
            'label' => 'Catalog Language (ISO)',
            'attribute' => 'feed.language',
            'default' => 'en',
            'clean' => 'single_line',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Templates
    |--------------------------------------------------------------------------
    |
    | These ship with the application and are seeded during migration. Teams
    | can create additional templates at runtime without modifying code.
    |
    */
    'default_templates' => [
        ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY => [
            'name' => 'Summary',
            'description' => 'Generates a concise, high-converting summary (up to 60 words) highlighting why the product matters.',
            'system_prompt' => 'You are a product marketing assistant who writes short, high-converting product summaries. Always write every response using the language identified by the ISO code "{{ language }}". You MUST only respond with ONLY a valid plain text string. Keep formatting plain text.',
            'prompt' => <<<'PROMPT'
Create a concise, high-converting marketing summary (maximum 60 words) for the product below. Highlight what makes it stand out and keep the tone upbeat yet trustworthy.

Title: {{ title }}
Product description: {{ description }}
PROMPT,
            'context' => [
                ['key' => 'title'],
                ['key' => 'description'],
                ['key' => 'language'],
            ],
            'settings' => [
                'content_type' => 'text',
                'options' => [],
            ],
        ],

        ProductAiTemplate::SLUG_DESCRIPTION => [
            'name' => 'Description',
            'description' => 'Produces a detailed, conversion-focused product description between 100 and 500 words.',
            'system_prompt' => 'You are an ecommerce conversion copywriter who crafts persuasive, human-sounding product descriptions. Always write every response using the language identified by the ISO code "{{ language }}". You MUST only respond with ONLY a valid plain text string. Keep formatting plain text.',
            'prompt' => <<<'PROMPT'
Write a compelling product description between 100 and 500 words based on the details below. Focus on benefits and address likely objections.

Title: {{ title }}
Product description: {{ description }}
PROMPT,
            'context' => [
                ['key' => 'title'],
                ['key' => 'description'],
                ['key' => 'language'],
            ],
            'settings' => [
                'content_type' => 'text',
                'options' => [],
            ],
        ],

        ProductAiTemplate::SLUG_USPS => [
            'name' => 'USP',
            'description' => 'Summarises between 3 to 6 concrete unique selling points as a JSON array of short bullet-friendly statements.',
            'system_prompt' => 'You are a conversion-focused marketer skilled at extracting concrete unique selling points from product data. Always write every bullet in the language identified by the ISO code "{{ language }}". You MUST only respond with ONLY a valid JSON array of strings.',
            'prompt' => <<<'PROMPT'
List a minimum of 3 and maximum 6 concise unique selling points for the product below. Each USP must be fewer than 20 words and avoid generic phrases.

Title: {{ title }}
Product description: {{ description }}
PROMPT,
            'context' => [
                ['key' => 'title'],
                ['key' => 'description'],
                ['key' => 'language'],
            ],
            'settings' => [
                'content_type' => 'usps',
                'options' => [],
            ],
        ],

        ProductAiTemplate::SLUG_FAQ => [
            'name' => 'FAQ',
            'description' => 'Creates three pre-sale FAQ entries with customer-friendly questions and two-sentence answers in JSON format.',
            'system_prompt' => 'You draft helpful pre-sale FAQ entries for ecommerce products. Always write every question and answer in the language identified by the ISO code "{{ language }}". You MUST always respond with ONLY a valid JSON array containing objects with "question" and "answer" keys.',
            'prompt' => <<<'PROMPT'
Create exactly three customer-facing FAQ entries for the product below. For each entry provide a concise question and a two sentence answer.

Title: {{ title }}
Product description: {{ description }}
PROMPT,
            'context' => [
                ['key' => 'title'],
                ['key' => 'description'],
                ['key' => 'language'],
            ],
            'settings' => [
                'content_type' => 'faq',
                'options' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Action Definitions
    |--------------------------------------------------------------------------
    |
    | Named sets of templates the UI can surface together.
    |
    */
    'actions' => [
        'generate_summary' => [
            ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY,
            ProductAiTemplate::SLUG_DESCRIPTION,
            ProductAiTemplate::SLUG_USPS,
            ProductAiTemplate::SLUG_FAQ,
        ],
    ],
];
