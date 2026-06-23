<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\MessageStatus;
use App\Models\Debtor;
use App\Models\Invoice;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The operator inbox: a list of debtors and a threaded view per debtor with the
 * append-only event log, invoice status, and the approve/edit step.
 */
class InboxController extends Controller
{
    private const STEPS = [0, 7, 14];

    public function index(): Response
    {
        $outstanding = [InvoiceStatus::Open->value, InvoiceStatus::Partial->value];

        $debtors = Debtor::query()
            ->withCount([
                'invoices as open_invoices_count' => fn ($q) => $q->whereIn('status', $outstanding),
                'messages as pending_drafts_count' => fn ($q) => $q->where('status', MessageStatus::PendingApproval->value),
            ])
            ->with(['invoices' => fn ($q) => $q->whereIn('status', $outstanding)])
            ->orderByDesc('paused_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Debtor $debtor) => [
                'id' => $debtor->id,
                'name' => $debtor->name,
                'company' => $debtor->company,
                'paused' => $debtor->isPaused(),
                'pause_reason' => $debtor->pause_reason,
                'outstanding_cents' => $debtor->invoices->sum('outstanding_cents'),
                'open_invoices_count' => $debtor->open_invoices_count,
                'pending_drafts_count' => $debtor->pending_drafts_count,
            ]);

        return Inertia::render('inbox/index', ['debtors' => $debtors]);
    }

    public function show(Debtor $debtor): Response
    {
        $debtor->load([
            'invoices' => fn ($q) => $q->orderBy('due_date'),
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'events' => fn ($q) => $q->latest(),
        ]);

        return Inertia::render('inbox/show', [
            'debtor' => [
                'id' => $debtor->id,
                'name' => $debtor->name,
                'company' => $debtor->company,
                'email' => $debtor->email,
                'phone' => $debtor->phone,
                'tone_policy' => $debtor->tone_policy,
                'paused' => $debtor->isPaused(),
                'pause_reason' => $debtor->pause_reason,
                'paused_at' => $debtor->paused_at?->toDateTimeString(),
            ],
            'invoices' => $debtor->invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'currency' => strtoupper($invoice->currency),
                'amount_cents' => $invoice->amount_cents,
                'amount_paid_cents' => $invoice->amount_paid_cents,
                'outstanding_cents' => $invoice->outstanding_cents,
                'status' => $invoice->status->value,
                'due_date' => $invoice->due_date->toDateString(),
                'payment_url' => $invoice->payment_url,
                'next_step' => $this->nextStepLabel($invoice, $debtor),
            ])->values(),
            'thread' => $debtor->messages->map(fn ($m) => [
                'id' => $m->id,
                'direction' => $m->direction->value,
                'channel' => $m->channel->value,
                'status' => $m->status->value,
                'body' => $m->body,
                'classification' => $m->classification,
                'sequence_step' => $m->sequence_step,
                'can_approve' => $m->status === MessageStatus::PendingApproval,
                'created_at' => $m->created_at?->toDateTimeString(),
            ])->values(),
            'events' => $debtor->events->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->type,
                'data' => $e->data,
                'created_at' => $e->created_at?->toDateTimeString(),
            ])->values(),
        ]);
    }

    private function nextStepLabel(Invoice $invoice, Debtor $debtor): string
    {
        if ($debtor->isPaused()) {
            return 'Paused';
        }

        if (! $invoice->status->isOutstanding()) {
            return 'Closed';
        }

        $drafted = $debtor->messages
            ->where('invoice_id', $invoice->id)
            ->pluck('sequence_step')
            ->filter(fn ($s) => $s !== null)
            ->all();

        $next = $this->firstUndraftedStep($drafted);

        if ($next === null) {
            return 'Sequence complete';
        }

        $dueOn = $invoice->due_date->copy()->addDays($next);

        return $dueOn->isPast()
            ? "Day {$next} (due now)"
            : "Day {$next} (on {$dueOn->toDateString()})";
    }

    /** @param array<int, int> $drafted */
    private function firstUndraftedStep(array $drafted): ?int
    {
        foreach (self::STEPS as $step) {
            if (! in_array($step, $drafted, true)) {
                return $step;
            }
        }

        return null;
    }
}
