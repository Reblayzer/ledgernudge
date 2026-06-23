<?php

namespace App\Services\Claude;

/**
 * The result of a single Claude completion: the generated text plus the model
 * and token usage, so callers can persist what was produced and what it cost.
 */
final readonly class ClaudeCompletion
{
    public function __construct(
        public string $text,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
