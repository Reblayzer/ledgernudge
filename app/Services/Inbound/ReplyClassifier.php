<?php

namespace App\Services\Inbound;

use App\Enums\ReplyClassification;
use App\Services\Claude\Contracts\ClaudeMessenger;

/**
 * Classifies an inbound reply with Claude. Parsing is defensive: if the model's
 * reply isn't valid JSON or the category is unrecognised, the result falls back
 * to Unknown — which pauses the sequence. The bias is always toward stopping and
 * asking a human rather than guessing.
 */
class ReplyClassifier
{
    public function __construct(private ClaudeMessenger $messenger) {}

    public function classify(string $body): ReplyClassificationResult
    {
        $system = $this->template('system.md');
        $user = strtr($this->template('user.md'), ['{{body}}' => $body]);

        $completion = $this->messenger->complete($system, $user);
        $parsed = $this->parse($completion->text);

        return new ReplyClassificationResult(
            category: ReplyClassification::fromModel($parsed['category'] ?? null),
            rationale: (string) ($parsed['rationale'] ?? 'No rationale provided.'),
            confidence: isset($parsed['confidence']) ? (float) $parsed['confidence'] : null,
        );
    }

    /** @return array<string, mixed> */
    private function parse(string $text): array
    {
        // Tolerate stray prose or code fences around the JSON object.
        if (preg_match('/\{.*\}/s', $text, $matches) !== 1) {
            return [];
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function template(string $file): string
    {
        return trim(file_get_contents(resource_path("prompts/classify/{$file}")));
    }
}
