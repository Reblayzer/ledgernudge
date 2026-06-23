<?php

namespace App\Services\Stripe;

use App\Enums\InvoiceStatus;
use App\Models\Event;
use App\Models\Invoice;
use Stripe\Event as StripeEvent;

/**
 * Applies a verified Stripe event to the matching invoice. Payment amounts are
 * set absolutely (not incremented), so re-delivering an event — or receiving
 * both checkout.session.completed and payment_intent.succeeded for the same
 * payment — converges to the same state and logs at most one domain event.
 */
class WebhookReconciler
{
    public function handle(StripeEvent $event): void
    {
        $object = $event->data->object;

        match ($event->type) {
            'checkout.session.completed' => $this->onCheckoutCompleted($object),
            'payment_intent.succeeded' => $this->onPaymentIntentSucceeded($object),
            'payment_intent.payment_failed' => $this->onPaymentIntentFailed($object),
            default => null,
        };
    }

    private function onCheckoutCompleted(object $session): void
    {
        if (($session->payment_status ?? null) !== 'paid') {
            return;
        }

        $invoice = $this->resolveInvoice(
            $session->metadata->invoice_id ?? $session->client_reference_id ?? null,
            $session->payment_intent ?? null,
        );

        if ($invoice) {
            $this->recordPayment($invoice, (int) $session->amount_total, $session->payment_intent ?? null);
        }
    }

    private function onPaymentIntentSucceeded(object $intent): void
    {
        $invoice = $this->resolveInvoice($intent->metadata->invoice_id ?? null, $intent->id ?? null);

        if ($invoice) {
            $this->recordPayment($invoice, (int) $intent->amount_received, $intent->id ?? null);
        }
    }

    private function onPaymentIntentFailed(object $intent): void
    {
        $invoice = $this->resolveInvoice($intent->metadata->invoice_id ?? null, $intent->id ?? null);

        if ($invoice) {
            $this->recordFailure($invoice, $intent->id ?? null);
        }
    }

    private function resolveInvoice(?string $invoiceId, ?string $paymentIntentId): ?Invoice
    {
        if ($invoiceId !== null && is_numeric($invoiceId)) {
            $invoice = Invoice::find((int) $invoiceId);
            if ($invoice) {
                return $invoice;
            }
        }

        if ($paymentIntentId !== null) {
            return Invoice::where('stripe_payment_intent_id', $paymentIntentId)->first();
        }

        return null;
    }

    private function recordPayment(Invoice $invoice, int $amountReceivedCents, ?string $paymentIntentId): void
    {
        $invoice->amount_paid_cents = $amountReceivedCents;

        if ($paymentIntentId) {
            $invoice->stripe_payment_intent_id = $paymentIntentId;
        }

        if ($amountReceivedCents >= $invoice->amount_cents) {
            $invoice->status = InvoiceStatus::Paid;
            $invoice->paid_at ??= now();
        } elseif ($amountReceivedCents > 0) {
            $invoice->status = InvoiceStatus::Partial;
        }

        if (! $invoice->isDirty(['status', 'amount_paid_cents'])) {
            return; // Already reconciled to this state — no duplicate log.
        }

        $type = $invoice->status === InvoiceStatus::Paid
            ? Event::PAYMENT_SUCCEEDED
            : Event::PAYMENT_PARTIAL;

        $invoice->save();

        Event::create([
            'debtor_id' => $invoice->debtor_id,
            'invoice_id' => $invoice->id,
            'type' => $type,
            'data' => [
                'amount_received_cents' => $amountReceivedCents,
                'payment_intent' => $paymentIntentId,
            ],
        ]);
    }

    private function recordFailure(Invoice $invoice, ?string $paymentIntentId): void
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            return; // Never override a completed payment.
        }

        $invoice->status = InvoiceStatus::Failed;

        if ($paymentIntentId) {
            $invoice->stripe_payment_intent_id = $paymentIntentId;
        }

        if (! $invoice->isDirty('status')) {
            return;
        }

        $invoice->save();

        Event::create([
            'debtor_id' => $invoice->debtor_id,
            'invoice_id' => $invoice->id,
            'type' => Event::PAYMENT_FAILED,
            'data' => ['payment_intent' => $paymentIntentId],
        ]);
    }
}
