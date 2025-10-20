<?php

namespace App\Jobs;

use App\Models\ProductAiJob;

class GenerateProductUsps extends BaseProductAiJob
{
    protected function promptType(): string
    {
        return ProductAiJob::PROMPT_USPS;
    }
}
