<?php

namespace App\Services\Inbound;

use App\Enums\ReplyClassification;

final readonly class ReplyClassificationResult
{
    public function __construct(
        public ReplyClassification $category,
        public string $rationale,
        public ?float $confidence,
    ) {}
}
