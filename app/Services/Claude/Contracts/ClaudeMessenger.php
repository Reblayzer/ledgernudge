<?php

namespace App\Services\Claude\Contracts;

use App\Services\Claude\ClaudeCompletion;

/**
 * Seam over the Anthropic API: given a system + user prompt, return the
 * completion. Lets the dunning logic be unit-tested with an in-memory fake
 * instead of calling Claude.
 */
interface ClaudeMessenger
{
    public function complete(string $system, string $user, ?string $model = null): ClaudeCompletion;
}
