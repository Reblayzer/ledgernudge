<?php

namespace App\Services\Stripe;

use App\Models\Event;
use App\Models\Invoice;
use App\Services\Stripe\Contracts\CheckoutGateway;
use Stripe\Checkout\Session;

/**
 * Creates a hosted Stripe Checkout Session for a single invoice and records the
 * resulting payment URL + ids on the invoice. The invoice id is stamped into
 * both the session and the PaymentIntent metadata so the webhook can reconcile
 * the payment back to this invoice deterministically.
 */
class StripeCheckout
{
    public function __construct(private CheckoutGateway $gateway) {}

    public function createForInvoice(Invoice $invoice): Session
    {
        $session = $this->gateway->createSession([
            'mode' => 'payment',
            'success_url' => config('app.url')."/dashboard?invoice={$invoice->id}&paid=1",
            'cancel_url' => config('app.url')."/dashboard?invoice={$invoice->id}&paid=0",
            'client_reference_id' => (string) $invoice->id,
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'payment_intent_data' => [
                'metadata' => ['invoice_id' => (string) $invoice->id],
            ],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $invoice->currency,
                    'unit_amount' => $invoice->amount_cents,
                    'product_data' => [
                        'name' => "Invoice {$invoice->number}",
                    ],
                ],
            ]],
        ]);

        $invoice->forceFill([
            'stripe_checkout_session_id' => $session->id,
            'stripe_payment_intent_id' => $session->payment_intent ?: $invoice->stripe_payment_intent_id,
            'payment_url' => $session->url,
        ])->save();

        Event::create([
            'debtor_id' => $invoice->debtor_id,
            'invoice_id' => $invoice->id,
            'type' => Event::PAYMENT_LINK_CREATED,
            'data' => [
                'checkout_session_id' => $session->id,
                'amount_cents' => $invoice->amount_cents,
            ],
        ]);

        return $session;
    }
}
