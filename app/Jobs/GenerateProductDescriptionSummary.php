<?php

namespace App\Jobs;

use App\Models\ProductAiJob;

class GenerateProductDescriptionSummary extends BaseProductAiJob
{
    protected function promptType(): string
    {
        return ProductAiJob::PROMPT_DESCRIPTION_SUMMARY;
    }
}
