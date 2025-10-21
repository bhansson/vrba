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
Create a concise, high-converting marketing summary (maximum 60 words) for the product below. Highlight what makes it stand out and keep the tone upbeat yet trustworthy.

Title: {{ title }}
GTIN: {{ gtin }}
Product description: {{ description_excerpt }}
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
            'history_limit' => 10,
            'prompts' => [
                'system' => 'You are an ecommerce conversion copywriter who crafts persuasive, human-sounding product descriptions.',
                'user' => <<<'PROMPT'
Write a compelling product description between 100 and 500 words based on the details below. Focus on benefits, address likely objections. Use short paragraphs and keep formatting plain text.

Title: {{ title }}
GTIN: {{ gtin }}
Product description: {{ description_excerpt }}
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
            'history_limit' => 10,
            'prompts' => [
                'system' => 'You are a conversion-focused marketer skilled at extracting concrete unique selling points from product data.',
                'user' => <<<'PROMPT'
List exactly four concise unique selling points for the product below. Each USP must be a single sentence fragment of fewer than 20 words. Avoid generic phrases like "high quality" or "great value" unless you have supporting detail. 
Respond with ONLY a valid JSON document matching this structure exactly:

[
  "USP 1",
  "USP 2",
  "USP 3",
  "USP 4"
]

Use double quotes for all strings, keep it on a single line if possible, and include nothing before or after the JSON.

Title: {{ title }}
GTIN: {{ gtin }}
Product description: {{ description_excerpt }}
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
            'history_limit' => 10,
            'prompts' => [
                'system' => 'You draft helpful pre-sale FAQ entries for ecommerce products.',
                'user' => <<<'PROMPT'
Create three customer-facing FAQ entries for the product below. For each entry, provide a concise question and a two-sentence answer. Address common concerns (fit, compatibility, materials, shipping, warranty, installation, etc.) when relevant. Respond with ONLY a valid JSON document matching this structure exactly:

[
  {
    "question": "Question 1",
    "answer": "Answer 1"
  },
  {
    "question": "Question 2",
    "answer": "Answer 2"
  },
  {
    "question": "Question 3",
    "answer": "Answer 3"
  }
]

Use double quotes for all strings, keep keys in the order shown, and include nothing before or after the JSON.

Title: {{ title }}
GTIN: {{ gtin }}
Product description: {{ description_excerpt }}
PROMPT,
                'description_excerpt_limit' => 1200,
            ],
            'options' => [],
        ],
    ],
];
