<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Dunning\DunningDraftService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drafts the dunning message for one sequence step of an invoice (via Claude),
 * tagging it with the step. Re-checks at run time that the invoice is still
 * outstanding and the step hasn't already been drafted, so the scheduled worker
 * is safe to run repeatedly.
 */
class DraftDunningMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public int $step,
    ) {}

    public function handle(DunningDraftService $drafts): void
    {
        $invoice = $this->invoice->fresh();

        if (! $invoice || ! $invoice->status->isOutstanding()) {
            return;
        }

        if ($invoice->messages()->where('sequence_step', $this->step)->exists()) {
            return;
        }

        $message = $drafts->draftFor($invoice);
        $message->update(['sequence_step' => $this->step]);
    }
}
