<?php

namespace App\Services\Dunning;

use App\Models\Invoice;
use App\Models\Message;
use Illuminate\Support\Carbon;

/**
 * Builds the system + user prompts for a dunning draft from version-controlled
 * templates (resources/prompts/dunning) and the invoice's facts, debtor, tone
 * policy, and recent message history.
 */
class DunningPromptBuilder
{
    private const DEFAULT_TONE_POLICY = 'Professional, firm, and respectful.';

    private const HISTORY_LIMIT = 5;

    /**
     * @return array{0: string, 1: string} [system, user]
     */
    public function build(Invoice $invoice): array
    {
        $invoice->loadMissing('debtor');
        $debtor = $invoice->debtor;

        $replacements = [
            '{{tone_policy}}' => $debtor->tone_policy ?: self::DEFAULT_TONE_POLICY,
            '{{debtor_name}}' => $debtor->name,
            '{{company_suffix}}' => $debtor->company ? " at {$debtor->company}" : '',
            '{{invoice_number}}' => $invoice->number,
            '{{currency}}' => strtoupper($invoice->currency),
            '{{amount}}' => number_format($invoice->outstanding_cents / 100, 2),
            '{{due_date}}' => $invoice->due_date->toFormattedDateString(),
            '{{days_overdue}}' => (string) $this->daysOverdue($invoice->due_date),
            '{{history}}' => $this->history($invoice),
        ];

        $system = $this->template('system.md');
        $user = strtr($this->template('user.md'), $replacements);

        return [$system, $user];
    }

    private function daysOverdue(Carbon $dueDate): int
    {
        $today = Carbon::now()->startOfDay();

        return $dueDate->lt($today) ? $dueDate->diffInDays($today) : 0;
    }

    private function history(Invoice $invoice): string
    {
        $messages = $invoice->debtor->messages()
            ->latest()
            ->take(self::HISTORY_LIMIT)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return '(no prior messages)';
        }

        return $messages
            ->map(fn (Message $m) => '- ['.$m->direction->value.'/'.$m->channel->value.'] '.trim((string) $m->body))
            ->implode("\n");
    }

    private function template(string $file): string
    {
        return trim(file_get_contents(resource_path("prompts/dunning/{$file}")));
    }
}
