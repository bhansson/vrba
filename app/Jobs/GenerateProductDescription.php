<?php

namespace App\Jobs;

use App\Models\ProductAiJob;

class GenerateProductDescription extends BaseProductAiJob
{
    protected function promptType(): string
    {
        return ProductAiJob::PROMPT_DESCRIPTION;
    }
}
