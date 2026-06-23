<?php

namespace App\Services\Claude;

use Anthropic\Client;
use App\Services\Claude\Contracts\ClaudeMessenger;

/**
 * Production ClaudeMessenger backed by the official Anthropic PHP SDK. Drafts are
 * short, so output is capped tightly; thinking is left off (the system prompt
 * already constrains the model to emit only the message body).
 */
class AnthropicMessenger implements ClaudeMessenger
{
    private const MAX_TOKENS = 1024;

    public function __construct(private Client $client) {}

    public function complete(string $system, string $user, ?string $model = null): ClaudeCompletion
    {
        $model ??= (string) config('services.anthropic.model');

        $message = $this->client->messages->create(
            maxTokens: self::MAX_TOKENS,
            model: $model,
            system: $system,
            messages: [
                ['role' => 'user', 'content' => $user],
            ],
        );

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new ClaudeCompletion(
            text: trim($text),
            model: is_string($message->model) ? $message->model : ($message->model->value ?? $model),
            inputTokens: $message->usage->inputTokens,
            outputTokens: $message->usage->outputTokens,
        );
    }
}
