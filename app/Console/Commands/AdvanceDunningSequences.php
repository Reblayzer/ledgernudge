<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Jobs\DraftDunningMessage;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Walks past-due, still-outstanding invoices and enqueues a draft for the next
 * due sequence step (days overdue: 0 / 7 / 14), at most once per step. Drafts go
 * to a human for approval before anything is sent.
 */
class AdvanceDunningSequences extends Command
{
    protected $signature = 'dunning:advance';

    protected $description = 'Enqueue the next due dunning step for past-due invoices.';

    private const STEPS = [0, 7, 14];

    public function handle(): int
    {
        $today = Carbon::now()->startOfDay();
        $enqueued = 0;

        Invoice::query()
            ->whereIn('status', [InvoiceStatus::Open->value, InvoiceStatus::Partial->value])
            ->whereDate('due_date', '<=', $today)
            // Skip debtors whose sequence is paused (a dispute/stop/unknown reply).
            ->whereHas('debtor', fn ($q) => $q->whereNull('paused_at'))
            ->each(function (Invoice $invoice) use ($today, &$enqueued) {
                $daysOverdue = $invoice->due_date->startOfDay()->diffInDays($today);
                $dueStep = $this->dueStep($daysOverdue);

                if ($dueStep === null) {
                    return;
                }

                if ($invoice->messages()->where('sequence_step', $dueStep)->exists()) {
                    return;
                }

                DraftDunningMessage::dispatch($invoice, $dueStep);
                $enqueued++;
            });

        $this->info("Enqueued {$enqueued} dunning draft(s).");

        return self::SUCCESS;
    }

    private function dueStep(int $daysOverdue): ?int
    {
        $eligible = array_filter(self::STEPS, fn (int $step) => $step <= $daysOverdue);

        return $eligible === [] ? null : max($eligible);
    }
}
